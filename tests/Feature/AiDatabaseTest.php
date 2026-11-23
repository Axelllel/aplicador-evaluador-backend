<?php

/**
 * Archivo: AiDatabaseTest.php
 * Módulo: tests/Feature
 * Descripción: Pruebas de las funciones de IA en la base de datos.
 * Autor: Axel Rebollo Zeferino
 * Creado: 20/11/2026
 * Modificado: 23/11/2026 · Leonardo Ballestero López · Cabecera de archivo
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\SesionPanelService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PDO;
use PDOException;
use Tests\TestCase;

class AiDatabaseTest extends TestCase
{
    private const TEACHER = '11111111-1111-4111-8111-111111111111';

    private const OTHER = '22222222-2222-4222-8222-222222222222';

    private const OPERATION = '33333333-3333-4333-8333-333333333333';

    private PDO $admin;

    private static bool $prepared = false;

    protected function setUp(): void
    {
        parent::setUp();
        $port = getenv('AI_TEST_PG_PORT');
        if (! $port) {
            $this->markTestSkipped('Requiere PostgreSQL desechable y AI_TEST_PG_PORT.');
        }
        $root = new PDO('pgsql:host=127.0.0.1;port='.(int) $port.';dbname=postgres', 'postgres');
        if (! self::$prepared) {
            $root->exec('DROP DATABASE IF EXISTS bbb_ai_testing');
            $root->exec("CREATE DATABASE bbb_ai_testing ENCODING 'UTF8' TEMPLATE template0");
        }
        $this->admin = new PDO('pgsql:host=127.0.0.1;port='.(int) $port.';dbname=bbb_ai_testing', 'postgres');
        if (! self::$prepared) {
            $this->admin->exec("CREATE SCHEMA auth; CREATE TABLE auth.users(id uuid PRIMARY KEY); DO $$ BEGIN IF NOT EXISTS(SELECT 1 FROM pg_roles WHERE rolname='anon') THEN CREATE ROLE anon; END IF; IF NOT EXISTS(SELECT 1 FROM pg_roles WHERE rolname='authenticated') THEN CREATE ROLE authenticated; END IF; END $$");
            $this->admin->exec(str_replace('CREATE EXTENSION IF NOT EXISTS pgcrypto;', '', file_get_contents(base_path('supabase/migrations/20261025191750_estructura_base_datos.sql'))));
            foreach (['20261025194447_seguridad_roles.sql', '20261026213000_restringir_acceso_directo.sql',
                '20261026214000_rls_docente_grupo_pregunta.sql', '20261026214500_rls_fase2_tablas_restantes.sql',
                '20261030010000_flujo_examen_evaluacion.sql', '20261120130000_asistencia_ia_groq.sql'] as $file) {
                $sql = file_get_contents(base_path('supabase/migrations/'.$file));
                if ($file === '20261025194447_seguridad_roles.sql' && $this->admin->query("SELECT 1 FROM pg_roles WHERE rolname='laravel_app'")->fetchColumn()) {
                    $sql = str_replace('CREATE ROLE laravel_app', 'ALTER ROLE laravel_app', $sql);
                }
                $this->admin->exec($sql);
            }
            self::$prepared = true;
        }
        $this->admin->exec('TRUNCATE auth.users,public.paquete,public.alumno CASCADE');
        $this->admin->exec("INSERT INTO auth.users VALUES ('".self::TEACHER."'),('".self::OTHER."'); INSERT INTO public.paquete(id,nombre,max_grupos,max_preguntas,max_alumnos,precio) VALUES(1,'Prueba',10,100,30,10)");
        foreach ([self::TEACHER, self::OTHER] as $teacher) {
            $this->admin->exec("INSERT INTO public.docente(id,nombre,correo,paquete_id,estado_pago) VALUES('$teacher','Docente privado','$teacher@example.test',1,'activo'); INSERT INTO public.pago(docente_id,paquete_id,monto,estado,fecha_fin_vigencia) VALUES('$teacher',1,10,'confirmado',now()+interval '30 days')");
        }
        $this->admin->exec("INSERT INTO public.grupo(id,docente_id,materia,folio_codigo) VALUES(1,'".self::TEACHER."','Curso','FOLIO-PRIVADO');
INSERT INTO public.alumno(id,nombre) VALUES(14,'Alumno privado');
INSERT INTO public.alumno_grupo(alumno_id,grupo_id) VALUES(14,1);
INSERT INTO public.examen(id,grupo_id,nombre,fecha_apertura,fecha_cierre,orden,escala,plataforma_permitida,publicado) VALUES(1,1,'Examen',now()-interval '1 day',now()+interval '1 day','fijo','0-10','ambas',true);
INSERT INTO public.apartado(id,examen_id,tipo,cantidad_preguntas) VALUES(1,1,'abierta',1);
INSERT INTO public.pregunta(id,docente_id,tipo,enunciado,respuesta_modelo) VALUES(1,'".self::TEACHER."','abierta','Define variable.','Un valor modificable.');
INSERT INTO public.examen_pregunta(id,apartado_id,pregunta_id,tiempo_segundos,ponderacion,respuesta_modelo_snapshot) VALUES(1,1,1,60,100,'Un valor modificable.');
INSERT INTO public.intento_examen(id,examen_id,alumno_id,estado,fecha_entrega) VALUES(1,1,14,'finalizado',now());
INSERT INTO public.respuesta_alumno(id,intento_id,examen_pregunta_id,respuesta_texto) VALUES(1,1,1,'Un valor que cambia.');");
        config(['database.default' => 'ai_testing', 'database.connections.ai_testing' => ['driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => (int) $port,
            'database' => 'bbb_ai_testing', 'username' => 'postgres', 'password' => '', 'charset' => 'utf8', 'prefix' => '', 'sslmode' => 'disable'],
            'services.supabase.url' => 'https://auth.example.test', 'services.supabase.key' => 'fake-public-key',
            'ai.enabled' => true, 'ai.key' => 'fake-private-key', 'ai.teacher_daily_requests' => 20,
            'ai.global_daily_requests' => 100, 'ai.global_daily_tokens' => 150000, 'ai.global_minute_requests' => 50]);
        DB::purge('ai_testing');
        DB::statement('SET ROLE laravel_app');
        Http::preventStrayRequests();
        $this->provider();
        $this->withToken('fake-token');
    }

    protected function tearDown(): void
    {
        if (isset($this->admin)) {
            DB::disconnect('ai_testing');
        }
        parent::tearDown();
    }

    public function test_proposal_persists_without_final_grade_then_teacher_saves_and_audits_the_final_grade(): void
    {
        $this->postJson('/api/v1/teacher/ai/answers/1')->assertOk()->assertJsonPath('data.puntaje', 85)->assertJsonPath('data.reutilizado', false);
        $row = $this->admin->query('SELECT puntaje_ia,calificacion_final,revisada_manual,tokens_consumidos FROM public.respuesta_alumno WHERE id=1')->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals(85, $row['puntaje_ia']);
        $this->assertNull($row['calificacion_final']);
        $this->assertFalse($row['revisada_manual']);
        $this->assertSame(250, $row['tokens_consumidos']);
        $this->assertSame(0, (int) $this->admin->query('SELECT count(*) FROM public.historial_calificacion')->fetchColumn());
        $this->postJson('/api/v1/teacher/ai/answers/1')->assertOk()->assertJsonPath('data.reutilizado', true);
        $this->assertGroqCalls(1);
        $this->getJson('/api/v1/teacher/attempts/1/answers')->assertOk()->assertJsonPath('data.respuestas.0.retroalimentacion_ia.proveedor', 'groq');
        $this->getJson('/api/v1/teacher/exams/1/results')->assertOk()->assertJsonPath('data.resultados.0.calificacion', null);
        $this->patchJson('/api/v1/teacher/answers/1/grade', ['calificacion' => 90, 'motivo' => 'Revisión docente.'])->assertOk();
        $this->assertEquals(90, $this->admin->query('SELECT calificacion_final FROM public.respuesta_alumno WHERE id=1')->fetchColumn());
        $this->assertSame(1, (int) $this->admin->query('SELECT count(*) FROM public.historial_calificacion')->fetchColumn());
        $this->postJson('/api/v1/teacher/ai/answers/1')->assertNotFound();
        $this->getJson('/api/v1/teacher/exams/1/results')->assertOk()->assertJsonPath('data.resultados.0.calificacion', 9);
        $this->assertGroqCalls(1);
    }

    public function test_generation_is_idempotent_private_and_does_not_insert_into_the_bank(): void
    {
        $this->provider(output: $this->questionsOutput());
        $this->postJson('/api/v1/teacher/ai/questions', $this->generation())->assertOk()->assertJsonPath('data.preguntas.0.respuesta_modelo', 'Dos');
        $this->postJson('/api/v1/teacher/ai/questions', $this->generation())->assertOk()->assertJsonPath('data.reutilizado', true);
        $this->postJson('/api/v1/teacher/ai/questions', array_replace($this->generation(), ['tema' => 'Cambio de tema']))->assertConflict();
        $this->assertGroqCalls(1);
        $this->assertSame(1, (int) $this->admin->query('SELECT count(*) FROM public.pregunta')->fetchColumn());
        $this->provider(self::OTHER, $this->questionsOutput());
        $this->postJson('/api/v1/teacher/ai/questions', $this->generation())->assertConflict();
        $this->admin->exec('SET ROLE laravel_app');
        $this->admin->exec("SELECT set_config('app.docente_id','".self::OTHER."',false)");
        $this->assertSame(0, (int) $this->admin->query('SELECT count(*) FROM public.solicitud_ia')->fetchColumn());
        $this->admin->exec('RESET ROLE');
    }

    public function test_web_actions_use_the_real_api_with_short_database_context_and_keep_drafts_in_session(): void
    {
        $this->withSession([SesionPanelService::CLAVE => SesionPanelService::datosParaSesion([
            'access_token' => 'fake-token', 'refresh_token' => 'fake-refresh', 'expires_in' => 3600,
            'docente' => ['id' => self::TEACHER, 'nombre' => 'Docente privado', 'correo' => 'private@example.test'],
        ])]);
        $this->provider(output: $this->questionsOutput());
        $this->post(route('panel.ia.generar'), $this->generation())->assertRedirect(route('panel.banco-de-preguntas').'#generar-con-ia')
            ->assertSessionHas('ia_borradores.preguntas.0.respuesta_modelo', 'Dos');
        $this->assertGroqCalls(1);
        $this->provider();
        $this->post(route('panel.ia.sugerir', ['examen' => 1, 'intento' => 1, 'respuesta' => 1]))
            ->assertRedirect(route('panel.resultados.intento', ['examen' => 1, 'intento' => 1]).'#respuesta-1')->assertSessionMissing('ia_error');
        $this->assertGroqCalls(1);
        $this->assertNull($this->admin->query('SELECT calificacion_final FROM public.respuesta_alumno WHERE id=1')->fetchColumn());
    }

    public function test_invalid_output_does_not_write_a_score_and_three_attempts_stop_further_provider_calls(): void
    {
        $this->provider(output: array_replace($this->proposal(), ['puntaje' => 200]));
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/teacher/ai/answers/1')->assertStatus(502);
        }
        $this->postJson('/api/v1/teacher/ai/answers/1')->assertStatus(429);
        $this->assertGroqCalls(3);
        $row = $this->admin->query('SELECT puntaje_ia,calificacion_final FROM public.respuesta_alumno WHERE id=1')->fetch(PDO::FETCH_ASSOC);
        $this->assertNull($row['puntaje_ia']);
        $this->assertNull($row['calificacion_final']);
        $this->assertSame(3, (int) $this->admin->query('SELECT intentos FROM public.solicitud_ia')->fetchColumn());
    }

    public function test_durable_request_and_token_quotas_stop_calls_before_provider_access(): void
    {
        $this->provider(output: $this->questionsOutput());
        config(['ai.teacher_daily_requests' => 1]);
        $this->postJson('/api/v1/teacher/ai/questions', $this->generation())->assertOk();
        $this->postJson('/api/v1/teacher/ai/questions', array_replace($this->generation(), ['operacion_id' => '44444444-4444-4444-8444-444444444444']))->assertStatus(429);
        $this->assertGroqCalls(1);
        $this->assertSame(250, (int) $this->admin->query('SELECT presupuesto_consumido FROM public.solicitud_ia')->fetchColumn());
        config(['ai.teacher_daily_requests' => 20, 'ai.global_daily_tokens' => 1]);
        $this->postJson('/api/v1/teacher/ai/answers/1')->assertStatus(429);
        $this->assertGroqCalls(1);
    }

    public function test_other_teachers_unfinished_attempts_multiple_choice_and_expired_plans_cannot_use_grading_ai(): void
    {
        $this->provider(self::OTHER);
        $this->postJson('/api/v1/teacher/ai/answers/1')->assertNotFound();
        $this->provider();
        $this->admin->exec("UPDATE public.intento_examen SET estado='en_progreso'");
        $this->postJson('/api/v1/teacher/ai/answers/1')->assertNotFound();
        $this->admin->exec("UPDATE public.intento_examen SET estado='finalizado'; UPDATE public.pregunta SET tipo='opcion_multiple'");
        $this->postJson('/api/v1/teacher/ai/answers/1')->assertNotFound();
        $this->admin->exec("UPDATE public.pregunta SET tipo='abierta'; UPDATE public.pago SET fecha_fin_vigencia=now()-interval '1 hour',fecha_inicio_vigencia=now()-interval '2 hours'");
        $this->postJson('/api/v1/teacher/ai/answers/1')->assertForbidden();
        $this->assertGroqCalls(0);
    }

    public function test_stale_snapshot_and_a_concurrent_manual_grade_are_preserved_without_storing_the_proposal(): void
    {
        $this->provider(during: function (): void {
            $this->admin->exec("UPDATE public.examen_pregunta SET respuesta_modelo_snapshot='Nuevo criterio.'; UPDATE public.respuesta_alumno SET calificacion_final=91,revisada_manual=true");
        });
        $this->postJson('/api/v1/teacher/ai/answers/1')->assertConflict();
        $row = $this->admin->query('SELECT puntaje_ia,calificacion_final FROM public.respuesta_alumno WHERE id=1')->fetch(PDO::FETCH_ASSOC);
        $this->assertNull($row['puntaje_ia']);
        $this->assertEquals(91, $row['calificacion_final']);
    }

    public function test_completed_proposal_is_not_displayed_if_its_original_content_changes(): void
    {
        $this->postJson('/api/v1/teacher/ai/answers/1')->assertOk();
        $this->admin->exec("UPDATE public.respuesta_alumno SET respuesta_texto='Respuesta corregida.'");
        $this->getJson('/api/v1/teacher/attempts/1/answers')->assertOk()->assertJsonPath('data.respuestas.0.retroalimentacion_ia', null)->assertJsonPath('data.respuestas.0.puntaje_ia', null);
    }

    public function test_missing_key_or_migration_is_actionable_and_does_not_call_groq(): void
    {
        config(['ai.key' => null]);
        $this->postJson('/api/v1/teacher/ai/answers/1')->assertStatus(503)->assertJsonFragment(['message' => 'La IA aún no está configurada. Agrega GROQ_API_KEY en el servidor.']);
        config(['ai.key' => 'fake-private-key']);
        $this->admin->exec('DROP FUNCTION public.fn_ia_reservar(uuid,text,text,text,bigint,integer,integer,integer,integer,integer)');
        try {
            $this->postJson('/api/v1/teacher/ai/answers/1')->assertStatus(503)->assertJsonFragment(['message' => 'Falta habilitar la migración de IA en la base de datos. No se realizó la consulta.']);
            $this->assertGroqCalls(0);
        } finally {
            $this->admin->exec(file_get_contents(base_path('supabase/migrations/20261120130000_asistencia_ia_groq.sql')));
        }
    }

    public function test_rls_blocks_direct_proposal_writes_and_public_function_execution_and_migration_is_repeatable(): void
    {
        $this->admin->exec('SET ROLE laravel_app');
        $this->expectState('42501', fn () => $this->admin->exec('UPDATE public.solicitud_ia SET intentos=0'));
        $this->admin->exec('RESET ROLE; SET ROLE authenticated');
        $this->expectState('42501', fn () => $this->admin->query("SELECT public.fn_ia_reservar('".self::OPERATION."','preguntas',repeat('a',64),'modelo',null,100,20,100,10000,6)"));
        $this->admin->exec('RESET ROLE');
        $this->admin->exec(file_get_contents(base_path('supabase/migrations/20261120130000_asistencia_ia_groq.sql')));
        $this->postJson('/api/v1/teacher/ai/answers/1')->assertOk();
    }

    public function test_missing_or_invalid_authentication_never_reaches_the_provider(): void
    {
        $this->withToken('')->postJson('/api/v1/teacher/ai/answers/1')->assertUnauthorized();
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['auth.example.test/*' => Http::response([], 401)]);
        $this->withToken('bad-token')->postJson('/api/v1/teacher/ai/answers/1')->assertUnauthorized();
        $this->assertGroqCalls(0);
    }

    private function provider(string $teacher = self::TEACHER, ?array $output = null, ?callable $during = null): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['auth.example.test/*' => Http::response(['id' => $teacher]), 'api.groq.com/*' => function (Request $request) use ($output, $during) {
            $this->assertSame(0, DB::transactionLevel(), 'La espera de Groq no debe mantener transacciones abiertas.');
            $this->assertEmpty(DB::selectOne("SELECT current_setting('app.docente_id',true) AS id")->id);
            $body = $request->data();
            $this->assertSame('json_schema', $body['response_format']['type']);
            $this->assertTrue($body['response_format']['json_schema']['strict']);
            $this->assertSame(['Bearer fake-private-key'], $request->header('Authorization'));
            $content = $body['messages'][1]['content'];
            foreach (['Alumno privado', 'Docente privado', 'FOLIO-PRIVADO', self::TEACHER, 'alumno_id'] as $private) {
                $this->assertStringNotContainsString($private, $content);
            }
            if ($during) {
                $during();
            }

            return Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode($output ?? $this->proposal())]]], 'usage' => ['total_tokens' => 250]]);
        }]);
    }

    private function assertGroqCalls(int $count): void
    {
        $this->assertCount($count, Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'api.groq.com/')));
    }

    private function proposal(): array
    {
        return ['puntaje' => 85, 'justificacion' => 'Identifica un valor modificable.', 'requiere_revision' => false, 'observaciones' => []];
    }

    private function questionsOutput(): array
    {
        return ['preguntas' => [['enunciado' => '¿Cuánto es uno más uno?', 'respuesta_modelo' => 'Dos', 'opciones' => ['Uno', 'Dos'], 'pares' => []]]];
    }

    private function generation(): array
    {
        return ['operacion_id' => self::OPERATION, 'tema' => 'Aritmética', 'tipo' => 'opcion_multiple', 'nivel' => 'basico', 'cantidad' => 1];
    }

    private function expectState(string $state, callable $operation): void
    {
        try {
            $operation();
            $this->fail('Se esperaba SQLSTATE '.$state);
        } catch (PDOException $exception) {
            $this->assertSame($state, $exception->getCode());
        }
    }
}
