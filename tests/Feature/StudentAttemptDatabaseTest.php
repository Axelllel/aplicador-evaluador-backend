<?php

namespace Tests\Feature;

use App\Services\CredencialAlumnoService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StudentAttemptDatabaseTest extends TestCase
{
    private const TEACHER = '11111111-1111-4111-8111-111111111111';

    private PDO $admin;

    private static bool $prepared = false;

    protected function setUp(): void
    {
        parent::setUp();
        $port = getenv('ATTEMPT_TEST_PG_PORT');
        if (! $port) {
            $this->markTestSkipped('Requiere PostgreSQL desechable y ATTEMPT_TEST_PG_PORT.');
        }
        $root = new PDO('pgsql:host=127.0.0.1;port='.(int) $port.';dbname=postgres', 'postgres');
        if (! self::$prepared) {
            $root->exec('DROP DATABASE IF EXISTS bbb_attempt_testing');
            $root->exec("CREATE DATABASE bbb_attempt_testing ENCODING 'UTF8' TEMPLATE template0");
        }
        $this->admin = new PDO('pgsql:host=127.0.0.1;port='.(int) $port.';dbname=bbb_attempt_testing', 'postgres');
        if (! self::$prepared) {
            $this->admin->exec("CREATE SCHEMA auth; CREATE TABLE auth.users(id uuid PRIMARY KEY); DO $$ BEGIN IF NOT EXISTS(SELECT 1 FROM pg_roles WHERE rolname='anon') THEN CREATE ROLE anon; END IF; IF NOT EXISTS(SELECT 1 FROM pg_roles WHERE rolname='authenticated') THEN CREATE ROLE authenticated; END IF; END $$");
            $this->admin->exec(str_replace('CREATE EXTENSION IF NOT EXISTS pgcrypto;', '', file_get_contents(base_path('supabase/migrations/20261025191750_estructura_base_datos.sql'))));
            foreach (['20261025194447_seguridad_roles.sql', '20261026211000_fn_registrar_respuesta.sql',
                '20261026213000_restringir_acceso_directo.sql', '20261026214000_rls_docente_grupo_pregunta.sql',
                '20261026214500_rls_fase2_tablas_restantes.sql', '20261030010000_flujo_examen_evaluacion.sql',
                '20261109150000_credencial_alumno_pin.sql', '20261120200000_inicio_intento_movil_pin.sql'] as $file) {
                $sql = file_get_contents(base_path('supabase/migrations/'.$file));
                if ($file === '20261025194447_seguridad_roles.sql' && $this->admin->query("SELECT 1 FROM pg_roles WHERE rolname='laravel_app'")->fetchColumn()) {
                    $sql = str_replace('CREATE ROLE laravel_app', 'ALTER ROLE laravel_app', $sql);
                }
                $this->admin->exec($sql);
            }
            self::$prepared = true;
        }
        config(['database.default' => 'attempt_testing', 'database.connections.attempt_testing' => [
            'driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => (int) $port,
            'database' => 'bbb_attempt_testing', 'username' => 'postgres', 'password' => '',
            'charset' => 'utf8', 'prefix' => '', 'sslmode' => 'disable',
        ], 'services.student_pin.pepper' => 'test-only-pepper']);
        DB::purge('attempt_testing');
        DB::statement('SET ROLE laravel_app');
    }

    protected function tearDown(): void
    {
        if (isset($this->admin)) {
            DB::disconnect('attempt_testing');
        }
        parent::tearDown();
    }

    private function fixture(): void
    {
        $this->admin->exec('TRUNCATE auth.users, public.paquete, public.alumno RESTART IDENTITY CASCADE');
        $teacher = self::TEACHER;
        $this->admin->exec("INSERT INTO auth.users VALUES('$teacher');
INSERT INTO public.paquete(id,nombre,max_grupos,max_preguntas,max_alumnos,precio) VALUES(1,'Prueba',10,100,30,10);
INSERT INTO public.docente(id,nombre,correo,paquete_id,estado_pago) VALUES('$teacher','Docente','docente@example.test',1,'activo');
INSERT INTO public.pago(docente_id,paquete_id,monto,estado,fecha_fin_vigencia) VALUES('$teacher',1,10,'confirmado',now()+interval '1 day');
INSERT INTO public.grupo(id,docente_id,materia,folio_codigo) VALUES(1,'$teacher','Curso','FOLIO123'),(2,'$teacher','Otro','OTRO123');
INSERT INTO public.alumno(id,nombre) VALUES(14,'Alumno'),(15,'Otro');
INSERT INTO public.alumno_grupo(alumno_id,grupo_id) VALUES(14,1),(15,1);
INSERT INTO public.examen(id,grupo_id,nombre,fecha_apertura,fecha_cierre,orden,escala,plataforma_permitida,publicado) VALUES(1,1,'Examen',now()-interval '1 hour',now()+interval '1 day','fijo','0-10','ambas',true),(2,2,'Otro examen',now()-interval '1 hour',now()+interval '1 day','fijo','0-10','ambas',true);
INSERT INTO public.apartado(id,examen_id,tipo,cantidad_preguntas) VALUES(1,1,'abierta',1);
INSERT INTO public.pregunta(id,docente_id,tipo,enunciado,respuesta_modelo) VALUES(1,'$teacher','abierta','Define variable.','Un valor modificable.');
INSERT INTO public.examen_pregunta(id,apartado_id,pregunta_id,tiempo_segundos,ponderacion,respuesta_modelo_snapshot) VALUES(1,1,1,60,100,'Un valor modificable.');");
        $statement = $this->admin->prepare('INSERT INTO public.credencial_alumno(alumno_id,codigo_hash) VALUES(14,?)');
        $statement->execute([app(CredencialAlumnoService::class)->hashCodigo('ABCD27')]);
    }

    private function credentials(): array
    {
        return ['folio_codigo' => 'folio123', 'codigo_acceso' => 'abcd27'];
    }

    public function test_start_without_cookies_persists_the_pin_owner_and_token_can_answer_and_finish(): void
    {
        $this->fixture();
        $response = $this->postJson('/api/v1/student/exams/1/start', $this->credentials() + ['alumno_id' => 15, 'plataforma' => 'web'])
            ->assertCreated()->assertJsonPath('data.examen_id', 1)->assertJsonPath('data.reanudado', false)
            ->assertJsonMissingPath('data.pase')->assertJsonMissingPath('data.codigo_acceso');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertFalse($response->headers->has('Set-Cookie'));
        $token = $response->json('data.token_sesion');
        $this->assertSame(14, (int) $this->admin->query('SELECT alumno_id FROM public.intento_examen')->fetchColumn());
        $this->assertSame(2, (int) $this->admin->query('SELECT count(*) FROM public.alumno')->fetchColumn());

        $this->postJson('/api/v1/student/attempts/questions', ['token_sesion' => $token])
            ->assertOk()->assertJsonPath('data.preguntas.0.enunciado', 'Define variable.')
            ->assertJsonMissingPath('data.preguntas.0.respuesta_modelo')->assertDontSee('Un valor modificable.');
        $this->postJson('/api/v1/student/attempts/answers', ['token_sesion' => $token, 'examen_pregunta_id' => 1, 'respuesta_texto' => 'Un dato que cambia.'])
            ->assertOk();
        $this->assertSame('Un dato que cambia.', $this->admin->query('SELECT respuesta_texto FROM public.respuesta_alumno')->fetchColumn());
        $this->postJson('/api/v1/student/attempts/finish', ['token_sesion' => $token])
            ->assertOk()->assertJsonPath('data.estado', 'finalizado');
        $this->assertSame('finalizado', $this->admin->query('SELECT estado FROM public.intento_examen')->fetchColumn());
    }

    public function test_retry_returns_same_attempt_token_and_start_time_without_losing_answers(): void
    {
        $this->fixture();
        $first = $this->postJson('/api/v1/student/exams/1/start', $this->credentials())->assertCreated()->json('data');
        $this->postJson('/api/v1/student/attempts/answers', ['token_sesion' => $first['token_sesion'], 'examen_pregunta_id' => 1, 'respuesta_texto' => 'Guardada.'])->assertOk();
        $this->postJson('/api/v1/student/exams/1/start', $this->credentials())
            ->assertOk()->assertJsonPath('data.reanudado', true)
            ->assertJsonPath('data.intento_id', $first['intento_id'])
            ->assertJsonPath('data.token_sesion', $first['token_sesion'])
            ->assertJsonPath('data.fecha_inicio', $first['fecha_inicio']);
        $this->assertSame(1, (int) $this->admin->query('SELECT count(*) FROM public.intento_examen')->fetchColumn());
        $this->assertSame('Guardada.', $this->admin->query('SELECT respuesta_texto FROM public.respuesta_alumno')->fetchColumn());
    }

    #[DataProvider('closedStates')]
    public function test_closed_attempt_cannot_be_reopened_and_returns_409(string $state): void
    {
        $this->fixture();
        $this->postJson('/api/v1/student/exams/1/start', $this->credentials())->assertCreated();
        $this->admin->exec("UPDATE public.intento_examen SET estado='$state'");
        $this->postJson('/api/v1/student/exams/1/start', $this->credentials())
            ->assertConflict()->assertJsonPath('code', 'ATTEMPT_CLOSED')->assertJsonMissingPath('data.token_sesion');
        $this->assertSame($state, $this->admin->query('SELECT estado FROM public.intento_examen')->fetchColumn());
    }

    public static function closedStates(): array
    {
        return [['finalizado'], ['anulado']];
    }

    public function test_invalid_or_revoked_pin_cannot_start_or_recover_an_attempt_and_returns_401(): void
    {
        $this->fixture();
        $this->postJson('/api/v1/student/exams/1/start', array_replace($this->credentials(), ['codigo_acceso' => 'ZZZZ99']))
            ->assertUnauthorized()->assertJsonPath('code', 'INVALID_CREDENTIAL');
        $this->postJson('/api/v1/student/exams/1/start', $this->credentials())->assertCreated();
        $this->admin->exec('UPDATE public.credencial_alumno SET revocado_en=now()');
        $this->postJson('/api/v1/student/exams/1/start', $this->credentials())
            ->assertUnauthorized()->assertJsonMissingPath('data.token_sesion');
    }

    #[DataProvider('unavailableExams')]
    public function test_exam_restrictions_apply_to_start_and_resume_with_404(string $change): void
    {
        $this->fixture();
        $this->admin->exec($change);
        $this->postJson('/api/v1/student/exams/1/start', $this->credentials())
            ->assertNotFound()->assertJsonPath('code', 'EXAM_UNAVAILABLE');
        $this->assertSame(0, (int) $this->admin->query('SELECT count(*) FROM public.intento_examen')->fetchColumn());
    }

    public static function unavailableExams(): array
    {
        return [
            'web only' => ["UPDATE public.examen SET plataforma_permitida='web'"],
            'draft' => ['UPDATE public.examen SET publicado=false'],
            'inactive group' => ['UPDATE public.grupo SET activo=false'],
            'no enrollment' => ['DELETE FROM public.alumno_grupo WHERE alumno_id=14'],
            'other group' => ['UPDATE public.examen SET grupo_id=2 WHERE id=1'],
            'not open' => ["UPDATE public.examen SET fecha_apertura=now()+interval '1 hour'"],
            'closed' => ["UPDATE public.examen SET fecha_cierre=now()-interval '1 minute'"],
            'expired plan' => ["UPDATE public.pago SET fecha_inicio_vigencia=now()-interval '2 days',fecha_fin_vigencia=now()-interval '1 day'"],
            'suspended teacher' => ["UPDATE public.docente SET estado_pago='pendiente'"],
        ];
    }

    public function test_mismatched_folio_unknown_exam_and_removed_membership_do_not_disclose_attempt_tokens(): void
    {
        $this->fixture();
        $this->postJson('/api/v1/student/exams/1/start', array_replace($this->credentials(), ['folio_codigo' => 'OTRO123']))->assertNotFound();
        $this->postJson('/api/v1/student/exams/999/start', $this->credentials())->assertNotFound();
        $this->postJson('/api/v1/student/exams/1/start', $this->credentials())->assertCreated();
        $this->admin->exec('DELETE FROM public.alumno_grupo WHERE alumno_id=14');
        $this->postJson('/api/v1/student/exams/1/start', $this->credentials())
            ->assertNotFound()->assertJsonMissingPath('data.token_sesion');
    }

    public function test_legacy_encrypted_pass_still_starts_an_attempt_without_a_cookie(): void
    {
        $this->fixture();
        $pass = Crypt::encryptString(json_encode(['kind' => 'bbb-exam-grant-v1', 'exam' => 1, 'student' => 14, 'exp' => time() + 1800]));
        $this->postJson('/api/v1/student/attempts/start', ['pase' => $pass, 'plataforma' => 'movil'])
            ->assertCreated()->assertJsonStructure(['data' => ['intento_id', 'token_sesion', 'fecha_inicio']]);
        $this->postJson('/api/v1/student/exams/1/start', $this->credentials())->assertOk()->assertJsonPath('data.reanudado', true);
    }

    public function test_public_database_roles_cannot_execute_the_function_and_migration_is_repeatable(): void
    {
        $this->fixture();
        foreach (['anon', 'authenticated'] as $role) {
            $this->admin->exec('SET ROLE '.$role);
            try {
                $this->admin->query("SELECT * FROM public.fn_iniciar_intento_movil('FOLIO123',repeat('a',64),1)");
                $this->fail('El cliente no debe invocar la función directamente.');
            } catch (PDOException $exception) {
                $this->assertSame('42501', $exception->getCode());
            } finally {
                $this->admin->exec('RESET ROLE');
            }
        }
        $this->admin->exec(file_get_contents(base_path('supabase/migrations/20261120200000_inicio_intento_movil_pin.sql')));
        $this->postJson('/api/v1/student/exams/1/start', $this->credentials())->assertCreated();
    }
}
