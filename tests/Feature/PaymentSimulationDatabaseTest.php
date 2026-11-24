<?php

/**
 * Archivo: PaymentSimulationDatabaseTest.php
 * Módulo: tests/Feature
 * Descripción: Ejecuta migraciones y compras en PostgreSQL desechable, con RLS y permisos reales.
 * Autor: Axel Rebollo Zeferino
 * Creado: 20/11/2026
 * Modificado: 22/11/2026 · Equipo BigBitByte · Revisión de integración
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\VerifySupabaseToken;
use App\Services\CotizacionPlanService;
use App\Services\SesionPanelService;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PDO;
use PDOException;
use Tests\TestCase;

class PaymentSimulationDatabaseTest extends TestCase
{
    private const TEACHER = '11111111-1111-4111-8111-111111111111';

    private const OTHER = '22222222-2222-4222-8222-222222222222';

    private const OPERATION = '33333333-3333-4333-8333-333333333333';

    private PDO $db;

    private static bool $prepared = false;

    protected function setUp(): void
    {
        parent::setUp();
        $port = getenv('PAYMENT_TEST_PG_PORT');
        if (! $port) {
            $this->markTestSkipped('Requiere PostgreSQL local desechable y PAYMENT_TEST_PG_PORT.');
        }
        $admin = new PDO('pgsql:host=127.0.0.1;port='.(int) $port.';dbname=postgres', 'postgres');
        if (! self::$prepared) {
            $admin->exec('DROP DATABASE IF EXISTS bbb_payment_testing');
            $admin->exec("CREATE DATABASE bbb_payment_testing ENCODING 'UTF8' TEMPLATE template0");
        }
        $this->db = new PDO('pgsql:host=127.0.0.1;port='.(int) $port.';dbname=bbb_payment_testing', 'postgres');
        if (! self::$prepared) {
            $this->db->exec(<<<'SQL'
CREATE SCHEMA auth;
CREATE TABLE auth.users(id uuid PRIMARY KEY);
DO $$ BEGIN
IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname='anon') THEN CREATE ROLE anon; END IF;
IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname='authenticated') THEN CREATE ROLE authenticated; END IF;
END $$;
SQL);
            $base = file_get_contents(base_path('supabase/migrations/20261025191750_estructura_base_datos.sql'));
            $this->db->exec(str_replace('CREATE EXTENSION IF NOT EXISTS pgcrypto;', '', $base));
            foreach (['20261025194447_seguridad_roles.sql', '20261026213000_restringir_acceso_directo.sql',
                '20261026214000_rls_docente_grupo_pregunta.sql', '20261026214500_rls_fase2_tablas_restantes.sql',
                '20261109051000_pagos_referencias_externas.sql', '20261120120000_confirmar_pago_simulado.sql', '20261123050000_revocacion_sesiones_docente.sql'] as $migration) {
                $sql = file_get_contents(base_path('supabase/migrations/'.$migration));
                if ($migration === '20261025194447_seguridad_roles.sql' && $this->db->query("SELECT 1 FROM pg_roles WHERE rolname='laravel_app'")->fetchColumn()) {
                    $sql = str_replace('CREATE ROLE laravel_app', 'ALTER ROLE laravel_app', $sql);
                }
                $this->db->exec($sql);
            }
            self::$prepared = true;
        }
        $this->db->exec('TRUNCATE auth.users, public.docente, public.paquete, public.pago CASCADE');
        $this->db->exec('ALTER TABLE public.pago DROP CONSTRAINT IF EXISTS fail_payment');
        foreach ([self::TEACHER, self::OTHER] as $id) {
            $this->db->exec("INSERT INTO auth.users VALUES ('$id'); INSERT INTO public.docente(id,nombre,correo) VALUES ('$id','Prueba','$id@example.test')");
        }
        $this->db->exec('SET ROLE laravel_app');
        $this->identity(self::TEACHER);
    }

    public function test_purchase_persists_payment_and_activates_the_selected_capacity_with_server_price(): void
    {
        $receipt = $this->pay();
        $this->assertEquals(181, $receipt['monto']);
        $this->assertSame(3, $receipt['grupos']);
        $row = $this->db->query('SELECT d.estado_pago, p.max_grupos,p.max_alumnos,p.max_preguntas,p.precio FROM public.docente d JOIN public.paquete p ON p.id=d.paquete_id')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('activo', $row['estado_pago']);
        $this->assertEquals([3, 25, 150], [$row['max_grupos'], $row['max_alumnos'], $row['max_preguntas']]);
        $this->assertSame(1, (int) $this->db->query("SELECT count(*) FROM public.pago WHERE estado='confirmado' AND fecha_fin_vigencia > now() AND proveedor_pago='simulador_bigbitbyte'")->fetchColumn());
    }

    public function test_retry_is_idempotent_and_a_changed_operation_is_rejected(): void
    {
        $first = $this->pay();
        $again = $this->pay();
        $this->assertSame($first['pago_id'], $again['pago_id']);
        $this->assertTrue($again['reutilizado']);
        $this->assertSame(1, (int) $this->db->query('SELECT count(*) FROM public.pago')->fetchColumn());
        $this->expectSqlState('P1003', fn () => $this->pay(groups: 4));
    }

    public function test_spei_saves_a_generated_reference_and_only_allowed_metadata(): void
    {
        $receipt = $this->pay(method: 'spei');
        $row = $this->db->query('SELECT metodo_pago,clave_rastreo_spei,detalle_simulacion FROM public.pago')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('spei', $row['metodo_pago']);
        $this->assertSame($receipt['referencia'], $row['clave_rastreo_spei']);
        $this->assertArrayNotHasKey('cvv', json_decode($row['detalle_simulacion'], true));
        $this->assertArrayNotHasKey('numero_tarjeta', json_decode($row['detalle_simulacion'], true));
    }

    public function test_payment_failure_rolls_back_package_and_activation(): void
    {
        $this->db->exec('RESET ROLE; ALTER TABLE public.pago ADD CONSTRAINT fail_payment CHECK (monto < 0); SET ROLE laravel_app');
        $this->expectSqlState('23514', fn () => $this->pay());
        $this->assertSame(0, (int) $this->db->query('SELECT count(*) FROM public.paquete')->fetchColumn());
        $this->assertSame('inactivo', $this->db->query('SELECT estado_pago FROM public.docente')->fetchColumn());
    }

    public function test_missing_identity_and_direct_confirmation_are_denied(): void
    {
        $this->identity('');
        $this->expectSqlState('P1001', fn () => $this->pay());
        $this->identity(self::TEACHER);
        $this->expectSqlState('42501', fn () => $this->db->exec("UPDATE public.docente SET estado_pago='activo'"));
        $this->db->exec('RESET ROLE; SET ROLE authenticated');
        $this->expectSqlState('42501', fn () => $this->pay());
    }

    public function test_another_teacher_cannot_reuse_a_receipt_or_see_its_payment(): void
    {
        $this->pay();
        $this->identity(self::OTHER);
        $this->assertSame(0, (int) $this->db->query('SELECT count(*) FROM public.pago')->fetchColumn());
        $this->expectSqlState('P1003', fn () => $this->pay());
        $this->assertSame('inactivo', $this->db->query('SELECT estado_pago FROM public.docente')->fetchColumn());
    }

    public function test_existing_usage_cannot_be_silently_reduced(): void
    {
        $this->db->exec('RESET ROLE');
        for ($i = 1; $i <= 4; $i++) {
            $this->db->exec("INSERT INTO public.grupo(docente_id,materia,folio_codigo) VALUES ('".self::TEACHER."','Grupo','FOLIO$i')");
        }
        $this->db->exec('SET ROLE laravel_app');
        $this->expectSqlState('P1004', fn () => $this->pay());
        $this->assertSame(0, (int) $this->db->query('SELECT count(*) FROM public.pago')->fetchColumn());
    }

    public function test_sql_price_matches_the_quote_for_every_supported_capacity(): void
    {
        $quote = new CotizacionPlanService;
        foreach (range(1, 10) as $groups) {
            foreach (CotizacionPlanService::ALUMNOS_POR_GRUPO as $students) {
                foreach (CotizacionPlanService::PREGUNTAS as $questions) {
                    $result = $this->pay(groups: $groups, students: $students, questions: $questions, operation: sprintf('44444444-4444-4444-8444-%012d', $groups * 10000 + $students * 100 + $questions));
                    $this->assertSame($quote->centavos($groups, $students, $questions), (int) round($result['monto'] * 100));
                }
            }
        }
    }

    public function test_api_commits_real_payment_and_reports_the_current_plan(): void
    {
        config(['database.default' => 'payment_testing', 'services.payment_simulation.enabled' => true,
            'database.connections.payment_testing' => ['driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => (int) getenv('PAYMENT_TEST_PG_PORT'),
                'database' => 'bbb_payment_testing', 'username' => 'postgres', 'password' => '', 'charset' => 'utf8', 'prefix' => '', 'sslmode' => 'disable']]);
        DB::purge('payment_testing');
        DB::statement('SET ROLE laravel_app');
        DB::selectOne("SELECT set_config('app.docente_id', ?, false)", [self::TEACHER]);
        $this->withoutMiddleware(VerifySupabaseToken::class);
        $this->actingAs(new GenericUser(['id' => self::TEACHER]));
        $this->postJson('/api/v1/teacher/payments/simulate', ['operacion_id' => self::OPERATION,
            'grupos' => 3, 'alumnos' => 25, 'preguntas' => 150, 'metodo_pago' => 'tarjeta',
            'titular' => 'Prueba', 'correo_facturacion' => 'prueba@example.test', 'numero_tarjeta' => '4111111111111111',
            'cvv' => '123', 'vencimiento' => '12/39', 'monto' => '0.01'])->assertCreated()->assertJsonPath('data.grupos', 3);
        $this->getJson('/api/v1/teacher/payments')->assertOk()->assertJsonPath('data.0.es_plan_actual', true)
            ->assertJsonPath('data.0.proveedor_pago', 'simulador_bigbitbyte');
        $this->assertSame(1, (int) $this->db->query('SELECT count(*) FROM public.pago')->fetchColumn());
        $details = json_decode($this->db->query('SELECT detalle_simulacion FROM public.pago')->fetchColumn(), true);
        $this->assertSame('1111', $details['ultimos_cuatro']);
        $this->assertArrayNotHasKey('numero_tarjeta', $details);
        $this->assertArrayNotHasKey('cvv', $details);
        Http::assertNothingSent();
        DB::disconnect('payment_testing');
    }

    public function test_web_activation_persists_the_selected_plan_and_displays_it_as_current(): void
    {
        $this->withoutVite();
        config(['database.default' => 'payment_testing', 'services.payment_simulation.enabled' => true,
            'services.supabase.url' => 'https://auth.example.test', 'services.supabase.key' => 'fake-public-key',
            'database.connections.payment_testing' => ['driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => (int) getenv('PAYMENT_TEST_PG_PORT'),
                'database' => 'bbb_payment_testing', 'username' => 'postgres', 'password' => '', 'charset' => 'utf8', 'prefix' => '', 'sslmode' => 'disable']]);
        DB::purge('payment_testing');
        DB::statement('SET ROLE laravel_app');
        Http::preventStrayRequests();
        Http::fake(['auth.example.test/auth/v1/user' => Http::response(['id' => self::TEACHER])]);
        $this->withSession([SesionPanelService::CLAVE => SesionPanelService::datosParaSesion([
            'access_token' => 'fake-token', 'refresh_token' => 'fake-refresh', 'expires_in' => 3600,
            'docente' => ['id' => self::TEACHER, 'nombre' => 'Prueba', 'correo' => 'prueba@example.test'],
        ])]);

        $this->post(route('panel.suscripcion.planes.pago.simular'), ['operacion_id' => self::OPERATION,
            'grupos' => 3, 'alumnos' => 25, 'preguntas' => 150, 'metodo_pago' => 'tarjeta',
            'titular' => 'Prueba', 'correo_facturacion' => 'prueba@example.test', 'numero_tarjeta' => '4242424242424242',
            'cvv' => '123', 'vencimiento' => '12/39'])->assertRedirect(route('panel.suscripcion'))->assertSessionHasNoErrors()
            ->assertSessionHas('pago_confirmado.grupos', 3);

        $row = $this->db->query('SELECT d.estado_pago,p.max_grupos,p.max_alumnos,p.max_preguntas FROM public.docente d JOIN public.paquete p ON p.id=d.paquete_id')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(['estado_pago' => 'activo', 'max_grupos' => 3, 'max_alumnos' => 25, 'max_preguntas' => 150], $row);
        $this->assertSame(1, (int) $this->db->query("SELECT count(*) FROM public.pago WHERE estado='confirmado'")->fetchColumn());
        $this->get(route('panel.suscripcion'))->assertOk()->assertSee('Plan activado exitosamente')->assertSee('3 grupos activos, 25 alumnos por grupo y 150 preguntas en el banco.')
            ->assertViewHas('vigente', fn (array $plan): bool => $plan['plan']['max_grupos'] === 3);
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer fake-token'));
        DB::disconnect('payment_testing');
    }

    public function test_migration_is_repeatable_and_also_installs_missing_legacy_payment_columns(): void
    {
        $this->db->exec('RESET ROLE');
        $this->db->exec('ALTER TABLE public.pago DROP COLUMN metodo_pago CASCADE, DROP COLUMN proveedor_pago CASCADE, DROP COLUMN referencia_externa CASCADE, DROP COLUMN clave_rastreo_spei CASCADE, DROP COLUMN detalle_simulacion CASCADE');
        $sql = file_get_contents(base_path('supabase/migrations/20261120120000_confirmar_pago_simulado.sql'));
        $this->db->exec($sql);
        $this->db->exec($sql);
        $this->db->exec('SET ROLE laravel_app');
        $receipt = $this->pay();
        $this->assertSame(3, $receipt['grupos']);
        $this->assertSame(1, (int) $this->db->query('SELECT count(*) FROM public.pago')->fetchColumn());
    }

    private function identity(string $id): void
    {
        $statement = $this->db->prepare("SELECT set_config('app.docente_id', ?, false)");
        $statement->execute([$id]);
    }

    private function pay(string $method = 'tarjeta', int $groups = 3, int $students = 25, int $questions = 150, string $operation = self::OPERATION): array
    {
        $statement = $this->db->prepare('SELECT public.fn_confirmar_pago_simulado(?::uuid,?::integer,?::integer,?::integer,?::text,?::jsonb)');
        $statement->execute([$operation, $groups, $students, $questions, $method, json_encode(['titular' => 'Prueba', 'correo_facturacion' => 'prueba@example.test', 'ultimos_cuatro' => '4242', 'banco' => 'Banco de prueba A', 'cvv' => '123', 'numero_tarjeta' => '4242424242424242'])]);

        return json_decode($statement->fetchColumn(), true);
    }

    private function expectSqlState(string $state, callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected SQLSTATE '.$state);
        } catch (PDOException $exception) {
            $this->assertSame($state, $exception->getCode());
        }
    }
}
