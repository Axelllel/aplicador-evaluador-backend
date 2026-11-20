<?php

/**
 * Archivo: AutomaticExamCompositionTest.php
 * Módulo: tests/Feature
 * Descripción: Prueba composición automática, permisos, atomicidad y publicación sin cuotas manuales.
 * Autor: Axel Rebollo Zeferino
 * Creado: 20/11/2026
 * Modificado: 20/11/2026 · Flujo simplificado de composición
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\VerifySupabaseToken;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AutomaticExamCompositionTest extends TestCase
{
    private const TEACHER = '11111111-1111-4111-8111-111111111111';

    private const OTHER = '22222222-2222-4222-8222-222222222222';

    private const ADD = '/api/v1/teacher/exams/1/questions';

    private const PUBLISH = '/api/v1/teacher/exams/1/publish';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        // SQLite exercises controller persistence; PostgreSQL's existing shared lock remains in production.
        DB::connection()->getPdo()->sqliteCreateFunction('hashtextextended', fn ($key, $seed) => 1);
        DB::connection()->getPdo()->sqliteCreateFunction('pg_advisory_xact_lock', fn ($key) => null);
        Schema::create('docente', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->integer('paquete_id');
            $table->string('estado_pago');
        });
        Schema::create('grupo', function (Blueprint $table): void {
            $table->id();
            $table->string('docente_id');
            $table->boolean('activo');
        });
        Schema::create('examen', function (Blueprint $table): void {
            $table->id();
            $table->integer('grupo_id');
            $table->string('nombre');
            $table->boolean('publicado')->default(false);
            $table->dateTime('fecha_apertura');
            $table->dateTime('fecha_cierre');
        });
        Schema::create('pregunta', function (Blueprint $table): void {
            $table->id();
            $table->string('docente_id');
            $table->string('tipo');
            $table->text('enunciado');
            $table->text('opciones')->nullable();
            $table->text('respuesta_modelo')->nullable();
        });
        Schema::create('apartado', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('examen_id')->constrained('examen');
            $table->string('tipo');
            $table->integer('cantidad_preguntas');
        });
        Schema::create('examen_pregunta', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('apartado_id')->constrained('apartado');
            $table->foreignId('pregunta_id')->constrained('pregunta');
            $table->integer('tiempo_segundos');
            $table->decimal('ponderacion', 5, 2);
            $table->text('respuesta_modelo_snapshot')->nullable();
            $table->unique(['apartado_id', 'pregunta_id']);
        });
        Schema::create('intento_examen', function (Blueprint $table): void {
            $table->id();
            $table->integer('examen_id');
        });
        Schema::create('pago', function (Blueprint $table): void {
            $table->id();
            $table->string('docente_id');
            $table->integer('paquete_id');
            $table->string('estado');
            $table->dateTime('fecha_inicio_vigencia');
            $table->dateTime('fecha_fin_vigencia');
        });
        DB::table('docente')->insert(['id' => self::TEACHER, 'paquete_id' => 1, 'estado_pago' => 'activo']);
        DB::table('pago')->insert(['docente_id' => self::TEACHER, 'paquete_id' => 1, 'estado' => 'confirmado', 'fecha_inicio_vigencia' => '2020-01-01 00:00:00', 'fecha_fin_vigencia' => '2099-01-01 00:00:00']);
        DB::table('grupo')->insert(['id' => 1, 'docente_id' => self::TEACHER, 'activo' => true]);
        DB::table('examen')->insert(['id' => 1, 'grupo_id' => 1, 'nombre' => 'Historia', 'publicado' => false, 'fecha_apertura' => '2020-01-01 00:00:00', 'fecha_cierre' => '2099-01-01 00:00:00']);
        foreach ([1 => 'abierta', 2 => 'opcion_multiple', 3 => 'abierta'] as $id => $type) {
            DB::table('pregunta')->insert(['id' => $id, 'docente_id' => self::TEACHER, 'tipo' => $type, 'enunciado' => 'Pregunta '.$id, 'opciones' => $type === 'opcion_multiple' ? '["A","B"]' : null, 'respuesta_modelo' => 'Referencia '.$id]);
        }
        $this->withoutMiddleware(VerifySupabaseToken::class);
        $this->actingAs(new GenericUser(['id' => self::TEACHER]));
    }

    public function test_question_type_is_inferred_and_capacity_grows_without_manual_sections(): void
    {
        $this->postJson(self::ADD, $this->question(1, 40) + ['apartado_id' => 900, 'tipo' => 'opcion_multiple'])
            ->assertCreated()->assertJsonPath('data.tipo', 'abierta');
        $this->postJson(self::ADD, $this->question(3, 60))->assertCreated();
        $this->assertDatabaseCount('apartado', 1);
        $this->assertDatabaseHas('apartado', ['examen_id' => 1, 'tipo' => 'abierta', 'cantidad_preguntas' => 2]);
        $this->assertDatabaseHas('examen_pregunta', ['pregunta_id' => 1, 'ponderacion' => 40, 'respuesta_modelo_snapshot' => 'Referencia 1']);
    }

    public function test_different_types_go_to_their_own_internal_sections(): void
    {
        $this->postJson(self::ADD, $this->question(1, 50))->assertCreated();
        $this->postJson(self::ADD, $this->question(2, 50))->assertCreated();
        $this->assertDatabaseCount('apartado', 2);
        $this->assertDatabaseHas('apartado', ['tipo' => 'abierta', 'cantidad_preguntas' => 1]);
        $this->assertDatabaseHas('apartado', ['tipo' => 'opcion_multiple', 'cantidad_preguntas' => 1]);
    }

    public function test_empty_manual_reservations_are_replaced_by_real_question_counts(): void
    {
        DB::table('apartado')->insert(['id' => 5, 'examen_id' => 1, 'tipo' => 'opcion_multiple', 'cantidad_preguntas' => 3]);
        $this->postJson(self::ADD, $this->question(1, 100))->assertCreated();
        $this->assertDatabaseMissing('apartado', ['id' => 5]);
        $this->assertDatabaseHas('apartado', ['tipo' => 'abierta', 'cantidad_preguntas' => 1]);
    }

    public function test_duplicate_and_foreign_questions_do_not_modify_the_composition(): void
    {
        $this->postJson(self::ADD, $this->question(1, 50))->assertCreated();
        $this->postJson(self::ADD, $this->question(1, 50))->assertConflict();
        DB::table('pregunta')->where('id', 2)->update(['docente_id' => self::OTHER]);
        $this->postJson(self::ADD, $this->question(2, 50))->assertNotFound();
        $this->assertDatabaseCount('examen_pregunta', 1);
        $this->assertDatabaseCount('apartado', 1);
    }

    public function test_foreign_exams_published_exams_attempts_and_inactive_groups_are_protected(): void
    {
        DB::table('grupo')->where('id', 1)->update(['docente_id' => self::OTHER]);
        $this->postJson(self::ADD, $this->question(1, 100))->assertNotFound();
        DB::table('grupo')->where('id', 1)->update(['docente_id' => self::TEACHER]);
        DB::table('examen')->where('id', 1)->update(['publicado' => true]);
        $this->postJson(self::ADD, $this->question(1, 100))->assertConflict();
        DB::table('examen')->where('id', 1)->update(['publicado' => false]);
        DB::table('intento_examen')->insert(['examen_id' => 1]);
        $this->postJson(self::ADD, $this->question(1, 100))->assertConflict();
        DB::table('intento_examen')->delete();
        DB::table('grupo')->where('id', 1)->update(['activo' => false]);
        $this->postJson(self::ADD, $this->question(1, 100))->assertForbidden();
        $this->assertDatabaseCount('examen_pregunta', 0);
    }

    public function test_expired_subscription_cannot_change_the_exam(): void
    {
        DB::table('pago')->update(['fecha_fin_vigencia' => '2020-01-02 00:00:00']);
        $this->postJson(self::ADD, $this->question(1, 100))->assertForbidden();
        $this->assertDatabaseCount('apartado', 0);
    }

    public function test_insert_failure_rolls_back_the_automatic_section(): void
    {
        DB::unprepared("CREATE TRIGGER fail_question BEFORE INSERT ON examen_pregunta BEGIN SELECT RAISE(ABORT, 'forced failure'); END;");
        $this->withoutExceptionHandling();
        try {
            $this->postJson(self::ADD, $this->question(1, 100));
            $this->fail('The forced insert error must escape the transaction.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('forced failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('apartado', 0);
        $this->assertDatabaseCount('examen_pregunta', 0);
    }

    public function test_publication_still_requires_exactly_one_hundred_percent(): void
    {
        $this->postJson(self::ADD, $this->question(1, '99.99'))->assertCreated();
        $this->postJson(self::PUBLISH, ['composicion_automatica' => true])->assertUnprocessable();
        $this->assertDatabaseHas('examen', ['id' => 1, 'publicado' => false]);
        DB::table('examen_pregunta')->update(['ponderacion' => '100.00']);
        $this->postJson(self::PUBLISH, ['composicion_automatica' => true])->assertOk()->assertJsonPath('data.preguntas', 1);
        $this->assertDatabaseHas('examen', ['id' => 1, 'publicado' => true]);
    }

    public function test_publication_normalizes_old_drafts_only_after_all_checks_pass(): void
    {
        DB::table('apartado')->insert(['id' => 7, 'examen_id' => 1, 'tipo' => 'abierta', 'cantidad_preguntas' => 3]);
        DB::table('apartado')->insert(['id' => 8, 'examen_id' => 1, 'tipo' => 'opcion_multiple', 'cantidad_preguntas' => 2]);
        DB::table('examen_pregunta')->insert(['apartado_id' => 7, 'pregunta_id' => 1, 'ponderacion' => 50, 'tiempo_segundos' => 90, 'respuesta_modelo_snapshot' => 'Referencia 1']);
        $this->postJson(self::PUBLISH, ['composicion_automatica' => true])->assertUnprocessable();
        $this->assertDatabaseHas('apartado', ['id' => 7, 'cantidad_preguntas' => 3]);
        $this->assertDatabaseHas('apartado', ['id' => 8]);
        DB::table('examen_pregunta')->update(['ponderacion' => 100]);
        // Legacy clients keep their explicit capacity checks.
        $this->postJson(self::PUBLISH)->assertUnprocessable();
        $this->postJson(self::PUBLISH, ['composicion_automatica' => true])->assertOk()->assertJsonPath('data.apartados', 1);
        $this->assertDatabaseHas('apartado', ['id' => 7, 'cantidad_preguntas' => 1]);
        $this->assertDatabaseMissing('apartado', ['id' => 8]);
    }

    public function test_automatic_publication_does_not_reorganize_an_exam_with_attempts(): void
    {
        $this->postJson(self::ADD, $this->question(1, 100))->assertCreated();
        DB::table('apartado')->where('examen_id', 1)->update(['cantidad_preguntas' => 3]);
        DB::table('intento_examen')->insert(['examen_id' => 1]);
        $this->postJson(self::PUBLISH, ['composicion_automatica' => true])->assertConflict();
        $this->assertDatabaseHas('apartado', ['examen_id' => 1, 'cantidad_preguntas' => 3]);
        $this->assertDatabaseHas('examen', ['id' => 1, 'publicado' => false]);
    }

    public function test_invalid_weight_and_missing_question_never_create_sections(): void
    {
        $this->postJson(self::ADD, $this->question(1, '100.01'))->assertUnprocessable()->assertJsonValidationErrors('ponderacion');
        $this->postJson(self::ADD, $this->question(1, '25.001'))->assertUnprocessable()->assertJsonValidationErrors('ponderacion');
        $this->postJson(self::ADD, $this->question(999, 100))->assertNotFound();
        $this->assertDatabaseCount('apartado', 0);
    }

    private function question(int $id, int|string $weight): array
    {
        return ['pregunta_id' => $id, 'tiempo_segundos' => 90, 'ponderacion' => $weight];
    }
}
