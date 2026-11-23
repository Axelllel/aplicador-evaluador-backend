<?php

/**
 * Archivo: StudentAttemptDatabaseTest.php
 * Módulo: tests/Feature
 * Descripción: Gestiona y verifica el módulo StudentAttemptDatabaseTest.
 * Autor: Axel Rebollo Zeferino
 * Creado: 22/11/2026
 * Modificado: 22/11/2026 · Equipo BigBitByte · Integración de requisitos pendientes
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\VerifySupabaseToken;
use App\Models\Docente;
use App\Services\CredencialAlumnoService;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
                '20261109100000_examenes_publicados_portal_alumno.sql', '20261109120000_revision_y_resultados_portal_alumno.sql',
                '20261109150000_credencial_alumno_pin.sql', '20261120200000_inicio_intento_movil_pin.sql', '20261120130000_asistencia_ia_groq.sql', '20261123010000_control_tiempo_intentos.sql', '20261123020000_recuperacion_pin_controlada.sql', '20261123030000_calificacion_en_segundo_plano.sql', '20261123040000_administracion_controlada.sql', '20261123050000_revocacion_sesiones_docente.sql'] as $file) {
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

    public function test_logout_revokes_the_access_token_and_its_refresh_session_without_affecting_a_new_login(): void
    {
        $this->fixture();
        config(['services.supabase.url' => 'https://auth.example.test', 'services.supabase.key' => 'test-key', 'services.supabase.mfa_phone_enabled' => false]);
        $this->app->forgetInstance(Factory::class);
        Http::clearResolvedInstance(Factory::class);
        Http::fake(['auth.example.test/auth/v1/user' => Http::response(['id' => self::TEACHER]), 'auth.example.test/auth/v1/logout*' => Http::response([], 204)]);
        $session = '22222222-2222-4222-8222-222222222222';
        $token = fn (string $id, int $offset = 0) => 'eyJhbGciOiJIUzI1NiJ9.'.rtrim(strtr(base64_encode(json_encode(['exp' => time() + 3600 + $offset, 'session_id' => $id, 'aal' => 'aal1'])), '+/', '-_'), '=').'.verified-by-provider';
        $first = $token($session);
        $this->withToken($first)->getJson('/api/v1/teacher/groups')->assertOk();
        $this->withToken($first)->postJson('/api/v1/auth/logout')->assertOk()->assertJsonPath('data.token_revocado', true)->assertJsonPath('data.sesion_proveedor_revocada', true);
        $this->withToken($first)->getJson('/api/v1/teacher/groups')->assertUnauthorized()->assertJsonPath('code', 'TOKEN_REVOKED');
        $this->withToken($token($session, 10))->getJson('/api/v1/teacher/groups')->assertUnauthorized();
        $this->withToken($token('33333333-3333-4333-8333-333333333333'))->getJson('/api/v1/teacher/groups')->assertOk();
        $this->withToken($first)->postJson('/api/v1/auth/logout')->assertOk();
        $row = $this->admin->query('SELECT token_hash,session_id FROM public.sesion_docente_revocada ORDER BY revocada_en DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(hash('sha256', $first), $row['token_hash']);
        $this->assertSame($session, $row['session_id']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/logout?scope=local') && $request->hasHeader('Authorization', 'Bearer '.$first));
    }

    public function test_logout_remains_locally_revoked_when_the_auth_provider_cannot_revoke_refresh(): void
    {
        $this->fixture();
        config(['services.supabase.url' => 'https://auth.example.test', 'services.supabase.key' => 'test-key']);
        $this->app->forgetInstance(Factory::class);
        Http::clearResolvedInstance(Factory::class);
        Http::fake(['auth.example.test/auth/v1/user' => Http::response(['id' => self::TEACHER]), 'auth.example.test/auth/v1/logout*' => Http::response([], 503)]);
        $this->withToken('verified-logout-fallback')->postJson('/api/v1/auth/logout')->assertOk()->assertJsonPath('data.token_revocado', true)->assertJsonPath('data.sesion_proveedor_revocada', false);
        $this->withToken('verified-logout-fallback')->getJson('/api/v1/teacher/groups')->assertUnauthorized();
        $this->withToken('')->postJson('/api/v1/auth/logout')->assertUnauthorized();
    }

    private function fixture(): void
    {
        $this->admin->exec('TRUNCATE auth.users, public.paquete, public.alumno, public.codigo_alumno_reservado RESTART IDENTITY CASCADE');
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

        $this->postJson('/api/v2/student/attempts/questions', ['token_sesion' => $token])
            ->assertOk()->assertJsonPath('data.version_contrato', 2)->assertJsonMissingPath('data.preguntas.0.enunciado')
            ->assertJsonMissingPath('data.preguntas.0.respuesta_modelo')->assertDontSee('Un valor modificable.');
        $empty = $this->postJson('/api/v2/student/attempts/questions', ['token_sesion' => $token])->assertOk();
        $this->assertInstanceOf(\stdClass::class, json_decode($empty->getContent())->data->respuestas);
        $this->postJson('/api/v2/student/attempts/open-question', ['token_sesion' => $token, 'examen_pregunta_id' => 1])->assertOk();
        $this->postJson('/api/v2/student/attempts/answers', ['token_sesion' => $token, 'examen_pregunta_id' => 1, 'respuesta_texto' => 'Un dato que cambia.'])
            ->assertOk();
        $this->assertSame('Un dato que cambia.', $this->admin->query('SELECT respuesta_texto FROM public.respuesta_alumno')->fetchColumn());
        $this->postJson('/api/v2/student/attempts/finish', ['token_sesion' => $token])
            ->assertOk()->assertJsonPath('data.estado', 'finalizado');
        $this->assertSame('finalizado', $this->admin->query('SELECT estado FROM public.intento_examen')->fetchColumn());
    }

    public function test_retry_returns_same_attempt_token_and_start_time_without_losing_answers(): void
    {
        $this->fixture();
        $first = $this->postJson('/api/v1/student/exams/1/start', $this->credentials())->assertCreated()->json('data');
        $this->postJson('/api/v2/student/attempts/open-question', ['token_sesion' => $first['token_sesion'], 'examen_pregunta_id' => 1])->assertOk();
        $this->postJson('/api/v2/student/attempts/answers', ['token_sesion' => $first['token_sesion'], 'examen_pregunta_id' => 1, 'respuesta_texto' => 'Guardada.'])->assertOk();
        $this->postJson('/api/v1/student/exams/1/start', $this->credentials())
            ->assertOk()->assertJsonPath('data.reanudado', true)
            ->assertJsonPath('data.intento_id', $first['intento_id'])
            ->assertJsonPath('data.token_sesion', $first['token_sesion'])
            ->assertJsonPath('data.fecha_inicio', $first['fecha_inicio']);
        $this->assertSame(1, (int) $this->admin->query('SELECT count(*) FROM public.intento_examen')->fetchColumn());
        $this->assertSame('Guardada.', $this->admin->query('SELECT respuesta_texto FROM public.respuesta_alumno')->fetchColumn());

        $this->postJson('/api/v2/student/attempts/questions', ['token_sesion' => $first['token_sesion']])
            ->assertOk()->assertJsonPath('data.nombre', 'Examen')
            ->assertJsonPath('data.respuestas.1', 'Guardada.')
            ->assertJsonPath('data.preguntas.0.examen_pregunta_id', 1)
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonMissingPath('data.preguntas.0.respuesta_modelo_snapshot');
    }

    public function test_resume_only_returns_the_current_attempt_answers_and_preserves_text_and_question_order(): void
    {
        $this->fixture();
        $this->admin->exec("UPDATE public.examen SET orden='aleatorio' WHERE id=1;
INSERT INTO public.pregunta(id,docente_id,tipo,enunciado,respuesta_modelo,opciones) VALUES
(2,'".self::TEACHER."','opcion_multiple','Elige una opción.','A','{\"A\":\"Uno\",\"B\":\"Dos\"}'),
(3,'".self::TEACHER."','programacion','Escribe código.','Referencia reservada.',null);
INSERT INTO public.apartado(id,examen_id,tipo,cantidad_preguntas) VALUES(2,1,'opcion_multiple',1),(3,1,'programacion',1);
INSERT INTO public.examen_pregunta(id,apartado_id,pregunta_id,tiempo_segundos,ponderacion,respuesta_modelo_snapshot) VALUES(2,2,2,60,25,'A'),(3,3,3,60,25,'Referencia reservada.');");
        $first = $this->postJson('/api/v1/student/exams/1/start', $this->credentials())->assertCreated()->json('data');
        $before = $this->postJson('/api/v2/student/attempts/questions', ['token_sesion' => $first['token_sesion']])->assertOk()->json('data.preguntas');
        $code = "print(\"México\");\n// Código guardado sin cambios";
        foreach ([2 => 'B', 3 => $code] as $question => $answer) {
            $this->postJson('/api/v2/student/attempts/open-question', ['token_sesion' => $first['token_sesion'], 'examen_pregunta_id' => $question])->assertOk();
            $this->postJson('/api/v2/student/attempts/answers', ['token_sesion' => $first['token_sesion'], 'examen_pregunta_id' => $question, 'respuesta_texto' => $answer])->assertOk();
        }
        $other = $this->admin->query("INSERT INTO public.intento_examen(examen_id,alumno_id,numero_intento,estado) VALUES(1,15,1,'en_progreso') RETURNING id")->fetchColumn();
        $statement = $this->admin->prepare('INSERT INTO public.respuesta_alumno(intento_id,examen_pregunta_id,respuesta_texto) VALUES(?,1,?)');
        $statement->execute([$other, 'Respuesta privada de otro alumno.']);

        $this->postJson('/api/v1/student/exams/1/start', $this->credentials())->assertOk()->assertJsonPath('data.reanudado', true);
        $response = $this->postJson('/api/v2/student/attempts/questions', ['token_sesion' => $first['token_sesion']])
            ->assertOk()->assertJsonPath('data.respuestas.2', 'B')->assertJsonPath('data.respuestas.3', $code)
            ->assertJsonMissingPath('data.respuestas.1')
            ->assertDontSee('Respuesta privada de otro alumno.')->assertDontSee('Referencia reservada.');

        $this->assertSame($before, $response->json('data.preguntas'));
        $this->assertCount(2, $response->json('data.respuestas'));
    }

    #[DataProvider('unavailableQuestionSessions')]
    public function test_saved_answers_are_not_returned_when_the_session_is_no_longer_available(string $change): void
    {
        $this->fixture();
        $first = $this->postJson('/api/v1/student/exams/1/start', $this->credentials())->assertCreated()->json('data');
        $this->postJson('/api/v2/student/attempts/open-question', ['token_sesion' => $first['token_sesion'], 'examen_pregunta_id' => 1])->assertOk();
        $this->postJson('/api/v2/student/attempts/answers', ['token_sesion' => $first['token_sesion'], 'examen_pregunta_id' => 1, 'respuesta_texto' => 'Respuesta reservada.'])->assertOk();
        $this->admin->exec($change);

        $this->postJson('/api/v2/student/attempts/questions', ['token_sesion' => $first['token_sesion']])
            ->assertStatus(str_contains($change, "estado='finalizado'") || str_contains($change, 'fecha_cierre=') ? 409 : 403)->assertDontSee('Respuesta reservada.');
    }

    public static function unavailableQuestionSessions(): array
    {
        return [
            'finished' => ["UPDATE public.intento_examen SET estado='finalizado'"],
            'annulled' => ["UPDATE public.intento_examen SET estado='anulado'"],
            'not enrolled' => ['DELETE FROM public.alumno_grupo WHERE alumno_id=14'],
            'inactive group' => ['UPDATE public.grupo SET activo=false'],
            'draft' => ['UPDATE public.examen SET publicado=false'],
            'out of schedule' => ["UPDATE public.examen SET fecha_cierre=now()-interval '1 minute'"],
            'unknown token' => ['UPDATE public.intento_examen SET token_sesion=gen_random_uuid()'],
        ];
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
        $this->admin->exec(file_get_contents(base_path('supabase/migrations/20261123020000_recuperacion_pin_controlada.sql')));
        $this->postJson('/api/v1/student/exams/1/start', $this->credentials())->assertCreated();
    }

    public function test_question_access_is_required_and_reopening_does_not_reset_its_deadline(): void
    {
        $this->fixture();
        $token = $this->postJson('/api/v1/student/exams/1/start', $this->credentials())->assertCreated()->json('data.token_sesion');
        $this->postJson('/api/v2/student/attempts/answers', ['token_sesion' => $token, 'examen_pregunta_id' => 1, 'respuesta_texto' => 'Sin abrir'])
            ->assertConflict()->assertJsonPath('code', 'QUESTION_NOT_OPEN');
        $first = $this->postJson('/api/v2/student/attempts/open-question', ['token_sesion' => $token, 'examen_pregunta_id' => 1])
            ->assertOk()->assertJsonPath('data.puede_responder', true)->json('data');
        $this->postJson('/api/v2/student/attempts/open-question', ['token_sesion' => $token, 'examen_pregunta_id' => 1])
            ->assertOk()->assertJsonPath('data.primer_acceso', $first['primer_acceso'])
            ->assertJsonPath('data.pregunta_fecha_limite', $first['pregunta_fecha_limite']);
        $this->postJson('/api/v2/student/attempts/open-question', ['token_sesion' => $token, 'examen_pregunta_id' => 999])
            ->assertForbidden();
    }

    public function test_late_answer_is_rejected_and_previous_answer_survives_even_with_fake_client_time(): void
    {
        $this->fixture();
        $token = $this->postJson('/api/v1/student/exams/1/start', $this->credentials())->assertCreated()->json('data.token_sesion');
        $body = ['token_sesion' => $token, 'examen_pregunta_id' => 1];
        $this->postJson('/api/v2/student/attempts/open-question', $body)->assertOk();
        $this->postJson('/api/v2/student/attempts/answers', $body + ['respuesta_texto' => 'Confirmada'])->assertOk();
        $this->admin->exec("UPDATE public.tiempo_pregunta_intento SET primer_acceso=clock_timestamp()-interval '2 minutes',fecha_limite=clock_timestamp()-interval '1 second'");
        $this->postJson('/api/v2/student/attempts/answers', $body + ['respuesta_texto' => 'Tardía', 'fecha_cliente' => '2000-01-01'])
            ->assertConflict()->assertJsonPath('code', 'QUESTION_EXPIRED');
        $this->assertSame('Confirmada', $this->admin->query('SELECT respuesta_texto FROM public.respuesta_alumno')->fetchColumn());
    }

    public function test_global_expiry_is_persisted_without_browser_and_closure_is_idempotent(): void
    {
        $this->fixture();
        $token = $this->postJson('/api/v1/student/exams/1/start', $this->credentials())->assertCreated()->json('data.token_sesion');
        $this->admin->exec("UPDATE public.intento_examen SET fecha_inicio=clock_timestamp()-interval '2 minutes'");
        $this->artisan('examenes:cerrar-vencidos')->assertSuccessful();
        $first = $this->postJson('/api/v2/student/attempts/finish', ['token_sesion' => $token])->assertOk()->json('data.fecha_entrega');
        $this->postJson('/api/v2/student/attempts/finish', ['token_sesion' => $token])->assertOk()->assertJsonPath('data.fecha_entrega', $first);
        $this->assertSame(1, (int) $this->admin->query('SELECT count(*) FROM public.cierre_intento')->fetchColumn());
        $this->assertSame('tiempo_agotado', $this->admin->query('SELECT motivo FROM public.cierre_intento')->fetchColumn());
        $this->artisan('examenes:cerrar-vencidos')->expectsOutput('Intentos finalizados: 0')->assertSuccessful();
    }

    public function test_reading_time_closes_at_exam_closure_and_returns_no_questions(): void
    {
        $this->fixture();
        $token = $this->postJson('/api/v1/student/exams/1/start', $this->credentials())->assertCreated()->json('data.token_sesion');
        $this->admin->exec("UPDATE public.examen SET fecha_cierre=clock_timestamp()-interval '1 second' WHERE id=1");
        $this->postJson('/api/v2/student/attempts/questions', ['token_sesion' => $token])
            ->assertConflict()->assertJsonPath('code', 'ATTEMPT_CLOSED')->assertJsonMissingPath('data.preguntas');
        $this->assertSame('finalizado', $this->admin->query('SELECT estado FROM public.intento_examen')->fetchColumn());
        $this->assertSame('cierre_examen', $this->admin->query('SELECT motivo FROM public.cierre_intento')->fetchColumn());
    }

    public function test_pin_recovery_requires_independent_operators_preserves_answers_and_revokes_old_secrets(): void
    {
        $this->fixture();
        $first = $this->postJson('/api/v1/student/exams/1/start', $this->credentials())->assertCreated()->json('data');
        $this->postJson('/api/v2/student/attempts/open-question', ['token_sesion' => $first['token_sesion'], 'examen_pregunta_id' => 1])->assertOk();
        $this->postJson('/api/v2/student/attempts/answers', ['token_sesion' => $first['token_sesion'], 'examen_pregunta_id' => 1, 'respuesta_texto' => 'Se conserva'])->assertOk();
        $other = '22222222-2222-4222-8222-222222222222';
        $this->admin->exec("INSERT INTO auth.users VALUES('$other'); INSERT INTO public.docente(id,nombre,correo) VALUES('$other','Autorizador','soporte@example.test'); INSERT INTO public.operador_soporte(usuario_id,funcion) VALUES('".self::TEACHER."','verificador'),('$other','autorizador')");
        $this->withoutMiddleware(VerifySupabaseToken::class);
        $this->actingAs((new Docente)->forceFill(['id' => self::TEACHER]));
        $case = $this->postJson('/api/v1/support/pin-recovery', ['alumno_id' => 14, 'folio_caso' => 'SOP-0001',
            'referencia_identidad' => 'Expediente institucional validado', 'referencia_canal' => 'Contacto institucional validado',
            'motivo' => 'Pérdida del código personal', 'identidad_verificada' => true])->assertCreated()->json('data.caso_id');
        $this->postJson('/api/v1/support/pin-recovery/'.$case.'/authorize')->assertForbidden();
        $this->actingAs((new Docente)->forceFill(['id' => $other]));
        $pin = $this->postJson('/api/v1/support/pin-recovery/'.$case.'/authorize')->assertOk()
            ->assertJsonPath('data.alumno_id', 14)->assertHeader('Cache-Control', 'no-store, private')->json('data.codigo_acceso');
        $this->assertMatchesRegularExpression('/^[A-Z]{4}[0-9]{2}$/', $pin);
        $this->postJson('/api/v1/support/pin-recovery/'.$case.'/authorize')->assertConflict();
        $this->postJson('/api/v2/student/attempts/questions', ['token_sesion' => $first['token_sesion']])->assertForbidden();
        $this->postJson('/api/v1/student/exams/1/start', $this->credentials())->assertUnauthorized();
        $this->postJson('/api/v1/student/exams/1/start', ['folio_codigo' => 'FOLIO123', 'codigo_acceso' => $pin])
            ->assertOk()->assertJsonPath('data.intento_id', $first['intento_id'])->assertJsonPath('data.reanudado', true);
        $this->assertSame('Se conserva', $this->admin->query('SELECT respuesta_texto FROM public.respuesta_alumno')->fetchColumn());
        $this->assertSame(2, (int) $this->admin->query('SELECT count(*) FROM public.evento_credencial_alumno')->fetchColumn());
        $this->withSession(['bbb.student' => ['alumno_id' => 14, 'credencial_version' => 1]])
            ->postJson('/alumno/api/tiempo')->assertUnauthorized()->assertJsonPath('code', 'SESSION_REVOKED')->assertSessionMissing('bbb.student');
    }

    public function test_a_teacher_without_support_authorization_cannot_create_a_recovery_case(): void
    {
        $this->fixture();
        $this->withoutMiddleware(VerifySupabaseToken::class);
        $this->actingAs((new Docente)->forceFill(['id' => self::TEACHER]));
        $this->postJson('/api/v1/support/pin-recovery', ['alumno_id' => 14, 'folio_caso' => 'SOP-0002',
            'referencia_identidad' => 'Solo nombre', 'referencia_canal' => 'Sin verificar', 'motivo' => 'Solicitud inválida', 'identidad_verificada' => true])->assertForbidden();
        $this->assertSame(0, (int) $this->admin->query('SELECT count(*) FROM public.recuperacion_pin')->fetchColumn());
    }

    public function test_submission_enqueues_once_and_worker_reservations_cannot_be_stolen_or_acknowledged_with_another_secret(): void
    {
        $this->fixture();
        $token = $this->postJson('/api/v1/student/exams/1/start', $this->credentials())->assertCreated()->json('data.token_sesion');
        $this->postJson('/api/v2/student/attempts/open-question', ['token_sesion' => $token, 'examen_pregunta_id' => 1])->assertOk();
        $this->postJson('/api/v2/student/attempts/answers', ['token_sesion' => $token, 'examen_pregunta_id' => 1, 'respuesta_texto' => 'Variable'])->assertOk();
        $this->postJson('/api/v2/student/attempts/finish', ['token_sesion' => $token])->assertOk();
        $this->postJson('/api/v2/student/attempts/finish', ['token_sesion' => $token])->assertOk();
        $this->assertSame(2, (int) $this->admin->query('SELECT count(*) FROM public.trabajo_calificacion')->fetchColumn());
        $first = DB::selectOne('SELECT * FROM public.fn_tomar_trabajo_calificacion(false)');
        $this->assertSame('objetivas', $first->tipo);
        $this->assertNull(DB::selectOne('SELECT * FROM public.fn_tomar_trabajo_calificacion(false)'));
        DB::selectOne("SELECT public.fn_resolver_trabajo_calificacion(?::bigint,'00000000-0000-4000-8000-000000000000',NULL)", [$first->trabajo_id]);
        $this->assertSame('procesando', $this->admin->query("SELECT estado FROM public.trabajo_calificacion WHERE tipo='objetivas'")->fetchColumn());
        DB::selectOne('SELECT public.fn_resolver_trabajo_calificacion(?::bigint,?::uuid,NULL)', [$first->trabajo_id, $first->reserva]);
        $this->assertSame('completo', $this->admin->query("SELECT estado FROM public.trabajo_calificacion WHERE tipo='objetivas'")->fetchColumn());
    }

    public function test_public_roles_cannot_access_new_secrets_or_worker_and_support_functions(): void
    {
        $this->fixture();
        foreach (['anon', 'authenticated'] as $role) {
            foreach (['SELECT public.fn_tomar_trabajo_calificacion(true)', "SELECT public.fn_autorizar_recuperacion_pin(1,repeat('a',64))", 'SELECT codigo_hash FROM public.codigo_alumno_reservado', 'SELECT referencia_identidad FROM public.recuperacion_pin'] as $query) {
                $this->admin->exec('SET ROLE '.$role);
                try {
                    $this->admin->query($query);
                    $this->fail('Acceso público indebido.');
                } catch (PDOException $exception) {
                    $this->assertSame('42501', $exception->getCode());
                } finally {
                    $this->admin->exec('RESET ROLE');
                }
            }
        }
    }

    public function test_background_worker_saves_an_estimate_without_promoting_it_to_a_final_grade(): void
    {
        $this->fixture();
        config(['ai.enabled' => true, 'ai.automatic_grading' => true, 'ai.key' => 'test-only-key']);
        Http::preventStrayRequests();
        Http::fake(['api.groq.com/*' => function () {
            $this->assertSame(0, DB::transactionLevel());

            return Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode([
                'puntaje' => 85, 'justificacion' => 'Concepto correcto.', 'requiere_revision' => false, 'observaciones' => [],
            ])]]], 'usage' => ['total_tokens' => 250]]);
        }]);
        $token = $this->postJson('/api/v1/student/exams/1/start', $this->credentials())->assertCreated()->json('data.token_sesion');
        $this->postJson('/api/v2/student/attempts/open-question', ['token_sesion' => $token, 'examen_pregunta_id' => 1])->assertOk();
        $this->postJson('/api/v2/student/attempts/answers', ['token_sesion' => $token, 'examen_pregunta_id' => 1, 'respuesta_texto' => 'Valor modificable'])->assertOk();
        $this->postJson('/api/v2/student/attempts/finish', ['token_sesion' => $token])->assertOk();
        $this->artisan('examenes:procesar-calificaciones')->assertSuccessful();
        $this->artisan('examenes:procesar-calificaciones')->assertSuccessful();
        Http::assertSentCount(1);
        $row = $this->admin->query('SELECT puntaje_ia,calificacion_final FROM public.respuesta_alumno')->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals(85, $row['puntaje_ia']);
        $this->assertNull($row['calificacion_final']);
        $this->postJson('/api/v2/student/attempts/result', ['token_sesion' => $token])->assertForbidden();
        $this->admin->exec("UPDATE public.examen SET resultado_visible='aproximado' WHERE id=1");
        $this->postJson('/api/v2/student/attempts/result', ['token_sesion' => $token])
            ->assertOk()->assertJsonPath('data.calificacion', 8.5)->assertJsonPath('data.estado', 'aproximado');
        $this->admin->exec("UPDATE public.examen SET resultado_visible='final' WHERE id=1");
        $this->postJson('/api/v2/student/attempts/result', ['token_sesion' => $token])->assertForbidden();
    }

    public function test_teacher_analytics_and_exports_are_scoped_to_the_authenticated_owner_and_missing_answers_count_zero(): void
    {
        $this->fixture();
        $this->admin->exec("INSERT INTO public.intento_examen(examen_id,alumno_id,estado,fecha_entrega) VALUES(1,14,'finalizado',clock_timestamp())");
        config(['services.supabase.url' => 'https://auth.example.test', 'services.supabase.key' => 'test-public-key']);
        Http::preventStrayRequests();
        Http::fake(['auth.example.test/*' => Http::response(['id' => self::TEACHER])]);
        $this->withToken('test-token');
        $this->getJson('/api/v1/teacher/exams/1/results')->assertOk()->assertJsonPath('data.resultados.0.calificacion', 0)->assertJsonPath('data.resultados.0.completo', true);
        $this->getJson('/api/v1/teacher/groups/1/analytics')->assertOk()->assertJsonPath('data.resumen_porcentaje.promedio', 0);
        $this->get('/api/v1/teacher/exams/1/results/export/xlsx')->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $other = '22222222-2222-4222-8222-222222222222';
        $this->admin->exec("INSERT INTO auth.users VALUES('$other'); INSERT INTO public.docente(id,nombre,correo) VALUES('$other','Otro docente','otro@example.test')");
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['auth.example.test/*' => Http::response(['id' => $other])]);
        $this->getJson('/api/v1/teacher/groups/1/analytics')->assertNotFound();
        $this->getJson('/api/v1/teacher/exams/1/results/export/pdf')->assertNotFound()->assertDontSee('Alumno');
    }

    public function test_the_server_deadline_accepts_minus_one_second_and_rejects_exactly_zero_and_plus_one(): void
    {
        foreach ([-1 => true, 0 => false, 1 => false] as $seconds => $expected) {
            $query = $this->admin->prepare("SELECT public.fn_antes_del_limite('2026-11-22T12:00:00Z'::timestamptz + ?::int * interval '1 second','2026-11-22T12:00:00Z'::timestamptz)");
            $query->execute([$seconds]);
            $this->assertSame($expected, $query->fetchColumn());
        }
    }

    public function test_admin_permissions_cannot_be_acquired_from_a_url_and_suspension_cannot_be_bypassed_by_billing(): void
    {
        $this->fixture();
        config(['services.supabase.url' => 'https://auth.example.test', 'services.supabase.key' => 'test-public-key']);
        Http::preventStrayRequests();
        Http::fake(['auth.example.test/*' => Http::response(['id' => self::TEACHER])]);
        $this->withToken('test-token')->getJson('/api/v1/admin/teachers?rol=super_admin')->assertForbidden();
        $other = '22222222-2222-4222-8222-222222222222';
        $this->admin->exec("INSERT INTO auth.users VALUES('$other'); INSERT INTO public.docente(id,nombre,correo) VALUES('$other','Administrador','admin@example.test'); INSERT INTO public.administrador_sistema VALUES('$other',true)");
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['auth.example.test/*' => Http::response(['id' => $other])]);
        $this->getJson('/api/v1/admin/teachers')->assertOk()->assertJsonCount(2, 'data');
        $this->patchJson('/api/v1/admin/teachers/'.self::TEACHER, ['suspender' => true, 'motivo' => 'Suspensión de prueba autorizada'])->assertOk();
        $this->assertSame('inactivo', $this->admin->query("SELECT estado_pago FROM public.docente WHERE id='".self::TEACHER."'")->fetchColumn());
        $this->assertSame(1, (int) $this->admin->query('SELECT count(*) FROM public.evento_administracion')->fetchColumn());
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['auth.example.test/*' => Http::response(['id' => self::TEACHER])]);
        $this->getJson('/api/v1/teacher/groups')->assertForbidden()->assertJsonPath('code', 'ACCOUNT_SUSPENDED');
        $this->postJson('/api/v1/teacher/payments/simulate', [])->assertForbidden();
    }

    public function test_mfa_verification_upgrades_the_session_and_only_aal2_can_read_teacher_data(): void
    {
        $this->fixture();
        $factor = '22222222-2222-4222-8222-222222222222';
        $challenge = '33333333-3333-4333-8333-333333333333';
        $aal1 = 'e30.'.rtrim(strtr(base64_encode(json_encode(['aal' => 'aal1'])), '+/', '-_'), '=').'.signature';
        $aal2 = 'e30.'.rtrim(strtr(base64_encode(json_encode(['aal' => 'aal2'])), '+/', '-_'), '=').'.signature';
        config(['services.supabase.url' => 'https://auth.example.test', 'services.supabase.key' => 'test-public-key', 'services.supabase.mfa_phone_enabled' => true]);
        $user = ['id' => self::TEACHER, 'factors' => [['id' => $factor, 'factor_type' => 'phone', 'status' => 'verified', 'phone' => '+525512345678']]];
        Http::preventStrayRequests();
        Http::fake([
            'auth.example.test/auth/v1/user' => Http::response($user),
            'auth.example.test/auth/v1/factors/'.$factor.'/challenge' => Http::response(['id' => $challenge, 'type' => 'phone']),
            'auth.example.test/auth/v1/factors/'.$factor.'/verify' => Http::response(['access_token' => $aal2, 'refresh_token' => 'new-refresh', 'expires_in' => 3600, 'user' => $user]),
        ]);
        $this->withToken($aal1)->getJson('/api/v1/teacher/groups')->assertForbidden()->assertJsonPath('code', 'MFA_REQUIRED');
        $this->getJson('/api/v1/auth/mfa')->assertOk()->assertJsonPath('data.factores.0.telefono_final', '5678')->assertDontSee('+525512345678');
        $this->postJson('/api/v1/auth/mfa/'.$factor.'/challenge')->assertOk()->assertJsonPath('data.id', $challenge);
        $this->postJson('/api/v1/auth/mfa/'.$factor.'/verify', ['challenge_id' => $challenge, 'codigo' => '123456'])
            ->assertOk()->assertJsonPath('data.access_token', $aal2)->assertHeader('Cache-Control', 'no-store, private');
        $this->withToken($aal2)->getJson('/api/v1/teacher/groups')->assertOk();
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/verify') && $request['challenge_id'] === $challenge && $request['code'] === '123456');
    }

    public function test_web_reentry_resumes_the_original_attempt_and_saved_answers_with_the_same_question_clock(): void
    {
        $this->fixture();
        $this->postJson('/alumno/ingresar', $this->credentials())->assertOk()->assertSessionHas('bbb.student.credencial_version', 1);
        $id = $this->postJson('/alumno/examen/iniciar', ['examen_id' => 1])->assertCreated()->json('data.intento_id');
        $this->postJson('/alumno/api/preguntas')->assertOk()->assertJsonPath('data.version_contrato', 2)->assertJsonMissingPath('data.preguntas.0.enunciado');
        $first = $this->postJson('/alumno/api/abrir-pregunta', ['examen_pregunta_id' => 1])->assertOk()->assertJsonPath('data.pregunta.enunciado', 'Define variable.')->json('data.primer_acceso');
        $this->postJson('/alumno/api/respuesta', ['examen_pregunta_id' => 1, 'respuesta_texto' => 'Respuesta web guardada'])->assertOk();
        $this->post('/alumno/salir')->assertRedirect();
        $this->postJson('/alumno/ingresar', $this->credentials())->assertOk();
        $this->postJson('/alumno/examen/iniciar', ['examen_id' => 1])->assertOk()->assertJsonPath('data.reanudado', true)->assertJsonPath('data.intento_id', $id);
        $this->postJson('/alumno/api/preguntas')->assertOk()->assertJsonPath('data.respuestas.1', 'Respuesta web guardada');
        $this->postJson('/alumno/api/abrir-pregunta', ['examen_pregunta_id' => 1])->assertOk()->assertJsonPath('data.primer_acceso', $first);
    }

    public function test_legacy_pass_cannot_recover_access_after_the_pin_is_replaced(): void
    {
        $this->fixture();
        $pass = Crypt::encryptString(json_encode(['kind' => 'bbb-exam-grant-v1', 'exam' => 1, 'student' => 14, 'exp' => time() + 1800]));
        $this->admin->exec('UPDATE public.credencial_alumno SET version=2');
        $this->postJson('/api/v1/student/attempts/start', ['pase' => $pass, 'plataforma' => 'movil'])->assertForbidden()->assertJsonPath('code', 'GRANT_REVOKED')->assertJsonMissingPath('data.token_sesion');
        $this->assertSame(0, (int) $this->admin->query('SELECT count(*) FROM public.intento_examen')->fetchColumn());
    }

    public function test_the_combined_migration_is_repeatable_and_keeps_existing_answers(): void
    {
        $this->fixture();
        $token = $this->postJson('/api/v1/student/exams/1/start', $this->credentials())->assertCreated()->json('data.token_sesion');
        $this->postJson('/api/v2/student/attempts/open-question', ['token_sesion' => $token, 'examen_pregunta_id' => 1])->assertOk();
        $this->postJson('/api/v2/student/attempts/answers', ['token_sesion' => $token, 'examen_pregunta_id' => 1, 'respuesta_texto' => 'Respuesta anterior a la migración'])->assertOk();
        $this->admin->exec(file_get_contents(base_path('scripts/completar-requisitos.sql')));
        $this->postJson('/api/v1/student/exams/1/start', $this->credentials())->assertOk()->assertJsonPath('data.reanudado', true);
        $this->postJson('/api/v2/student/attempts/questions', ['token_sesion' => $token])->assertOk()->assertJsonPath('data.respuestas.1', 'Respuesta anterior a la migración');
        $this->assertSame(2, (int) $this->admin->query('SELECT count(*) FROM public.alumno')->fetchColumn());
    }

    public function test_pin_authentication_holds_the_credential_until_its_version_has_been_returned(): void
    {
        $this->fixture();
        $dsn = $this->admin->query('SELECT current_database()')->fetchColumn();
        $port = (int) getenv('ATTEMPT_TEST_PG_PORT');
        $first = new PDO("pgsql:host=127.0.0.1;port=$port;dbname=$dsn", 'postgres');
        $second = new PDO("pgsql:host=127.0.0.1;port=$port;dbname=$dsn", 'postgres');
        $first->beginTransaction();
        try {
            $query = $first->prepare("SELECT credencial_version FROM public.fn_ingresar_alumno_sesion_pin('FOLIO123',?)");
            $query->execute([app(CredencialAlumnoService::class)->hashCodigo('ABCD27')]);
            $this->assertSame(1, (int) $query->fetchColumn());
            $second->exec("SET lock_timeout='50ms'");
            try {
                $second->exec('UPDATE public.credencial_alumno SET version=2 WHERE alumno_id=14');
                $this->fail('La revocación no debe adelantarse a la validación y devolución de la versión.');
            } catch (PDOException $exception) {
                $this->assertSame('55P03', $exception->getCode());
            }
        } finally {
            $first->rollBack();
        }
    }

    #[DataProvider('proctoringEvents')]
    public function test_proctoring_records_supported_events_without_annulling_and_refuses_events_after_submission(string $type): void
    {
        $this->fixture();
        $token = $this->postJson('/api/v1/student/exams/1/start', $this->credentials())->assertCreated()->json('data.token_sesion');
        $body = ['token_sesion' => $token, 'tipo_alerta' => $type, 'nivel_severidad' => 'media', 'evidencia_url' => 'https://example.test/evidencia'];
        $this->postJson('/api/v2/student/attempts/incidents', $body)->assertCreated();
        $this->assertSame($type, $this->admin->query('SELECT tipo_alerta FROM public.incidencia_proctoring')->fetchColumn());
        $this->assertSame('en_progreso', $this->admin->query('SELECT estado FROM public.intento_examen')->fetchColumn());
        $this->postJson('/api/v2/student/attempts/incidents', array_replace($body, ['tipo_alerta' => 'desconocido']))->assertUnprocessable();
        $this->postJson('/api/v2/student/attempts/finish', ['token_sesion' => $token])->assertOk();
        $this->postJson('/api/v2/student/attempts/incidents', $body)->assertForbidden();
        $this->assertSame(1, (int) $this->admin->query('SELECT count(*) FROM public.incidencia_proctoring')->fetchColumn());
    }

    public static function proctoringEvents(): array
    {
        return [['cambio_pestana'], ['rostro_no_detectado'], ['multiples_rostros'], ['camara_desactivada'], ['movimiento']];
    }

    public function test_existing_mobile_v1_keeps_full_questions_and_can_save_without_using_the_new_navigation(): void
    {
        $this->fixture();
        $token = $this->postJson('/api/v1/student/exams/1/start', $this->credentials())->assertCreated()->json('data.token_sesion');
        $this->postJson('/api/v1/student/attempts/questions', ['token_sesion' => $token])->assertOk()
            ->assertJsonPath('data.preguntas.0.enunciado', 'Define variable.')->assertJsonMissingPath('data.preguntas.0.respuesta_modelo');
        $this->postJson('/api/v1/student/attempts/answers', ['token_sesion' => $token, 'examen_pregunta_id' => 1, 'respuesta_texto' => 'Guardada desde Flutter v1'])->assertOk();
        $this->postJson('/api/v1/student/attempts/questions', ['token_sesion' => $token])->assertOk()->assertJsonPath('data.respuestas.1', 'Guardada desde Flutter v1');
        // Al activar explícitamente v2 no se puede usar el guardado v1 para eludir el tiempo de pregunta.
        $this->postJson('/api/v2/student/attempts/questions', ['token_sesion' => $token])->assertOk()->assertJsonMissingPath('data.preguntas.0.enunciado');
        $this->postJson('/api/v1/student/attempts/questions', ['token_sesion' => $token])->assertConflict()->assertJsonPath('code', 'UPGRADE_REQUIRED');
        $this->postJson('/api/v1/student/attempts/answers', ['token_sesion' => $token, 'examen_pregunta_id' => 1, 'respuesta_texto' => 'Intento de eludir el control'])->assertConflict()->assertJsonPath('code', 'QUESTION_NOT_OPEN');
    }
}
