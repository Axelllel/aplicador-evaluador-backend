<?php

/**
 * Archivo: AiAssistanceTest.php
 * Módulo: tests/Feature
 * Descripción: Gestiona y verifica el módulo AiAssistanceTest.
 * Autor: Axel Rebollo Zeferino
 * Creado: 22/11/2026
 * Modificado: 22/11/2026 · Equipo BigBitByte · Integración de requisitos pendientes
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\IaException;
use App\Services\ContratoIa;
use App\Services\GroqClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ConPanelFalso;
use Tests\TestCase;

class AiAssistanceTest extends TestCase
{
    use ConPanelFalso;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['ai.enabled' => true, 'ai.key' => 'fake-server-key']);
        $this->prepararPanel();
        $this->conSesionDelPanel();
        $this->prepararLecturas(['preguntas' => []]);
    }

    public function test_missing_key_and_oversized_input_fail_before_any_provider_request(): void
    {
        $client = new GroqClient;
        config(['ai.key' => null]);
        try {
            $client->body('Sistema', [], [], 100);
            $this->fail('La falta de clave debe ser explícita.');
        } catch (IaException $exception) {
            $this->assertStringContainsString('GROQ_API_KEY', $exception->getMessage());
        }
        config(['ai.key' => 'fake-server-key']);
        try {
            $client->body('Sistema', ['contenido' => str_repeat('x', 20000)], [], 100);
            $this->fail('El contenido excesivo debe rechazarse.');
        } catch (IaException $exception) {
            $this->assertSame(422, $exception->httpStatus);
        }
        Http::assertNothingSent();
    }

    #[DataProvider('providerErrors')]
    public function test_provider_failures_never_retry_or_return_a_fabricated_result(int $status, array $body, int $expected): void
    {
        Http::fake(['api.groq.com/*' => Http::response($body, $status)]);
        try {
            (new GroqClient)->complete([]);
            $this->fail('Se esperaba un fallo explícito del proveedor.');
        } catch (IaException $exception) {
            $this->assertSame($expected, $exception->httpStatus);
            $this->assertStringNotContainsString('fake-server-key', $exception->getMessage());
        }
        Http::assertSentCount(1);
    }

    public static function providerErrors(): array
    {
        return [
            'quota' => [429, ['error' => ['message' => 'private']], 429],
            'key' => [401, [], 503],
            'provider down' => [503, [], 503],
            'truncated' => [200, ['choices' => [['finish_reason' => 'length', 'message' => ['content' => '{}']]]], 502],
            'refusal' => [200, ['choices' => [['finish_reason' => 'stop', 'message' => ['refusal' => 'No', 'content' => '{}']]]], 502],
            'invalid json' => [200, ['choices' => [['finish_reason' => 'stop', 'message' => ['content' => '{invalid']]]], 502],
        ];
    }

    public function test_connection_failure_is_reported_without_retry(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            throw new ConnectionException('private timeout');
        });
        try {
            (new GroqClient)->complete([]);
            $this->fail('No debe simular una respuesta de IA.');
        } catch (IaException $exception) {
            $this->assertSame(503, $exception->httpStatus);
        }
        $this->assertSame(1, $calls);
    }

    #[DataProvider('invalidGrades')]
    public function test_invalid_grades_are_rejected(array $changes): void
    {
        $this->expectException(IaException::class);
        (new ContratoIa)->grade(array_replace($this->proposal(), $changes));
    }

    public static function invalidGrades(): array
    {
        return array_map(fn (array $changes): array => [$changes], [['puntaje' => 101], ['puntaje' => -1], ['puntaje' => '90'], ['puntaje' => null],
            ['requiere_revision' => 'false'], ['observaciones' => 'incorrecto'], ['justificacion' => '']]);
    }

    public function test_valid_zero_and_ungradable_answers_keep_distinct_meaning(): void
    {
        $contract = new ContratoIa;
        $this->assertSame(0.0, $contract->grade(array_replace($this->proposal(), ['puntaje' => 0]))['puntaje']);
        $this->assertNull($contract->grade(array_replace($this->proposal(), ['puntaje' => null, 'requiere_revision' => true]))['puntaje']);
    }

    public function test_invalid_multiple_choice_reference_and_duplicate_prompts_are_rejected(): void
    {
        $contract = new ContratoIa;
        $question = ['enunciado' => '¿Cuál es el resultado?', 'respuesta_modelo' => 'Tres', 'opciones' => ['Uno', 'Dos'], 'pares' => []];
        foreach ([[$question], [array_replace($question, ['respuesta_modelo' => 'Uno']), array_replace($question, ['respuesta_modelo' => 'Dos'])]] as $questions) {
            try {
                $contract->questions(['preguntas' => $questions], 'opcion_multiple', count($questions));
                $this->fail('Las alternativas o los enunciados no son válidos.');
            } catch (IaException $exception) {
                $this->assertSame(502, $exception->httpStatus);
            }
        }
    }

    public function test_generation_previews_escaped_drafts_without_inserting_questions_and_editor_preserves_choices(): void
    {
        $draft = ['tipo' => 'opcion_multiple', 'enunciado' => 'Pregunta <script>alert(1)</script>', 'respuesta_modelo' => 'Dos', 'opciones' => ['Uno', 'Dos']];
        $this->api->responder('POST', '/teacher/ai/questions', ['data' => ['preguntas' => [$draft]]]);
        $this->post(route('panel.ia.generar'), $this->generation())->assertRedirect(route('panel.banco-de-preguntas').'#generar-con-ia')
            ->assertSessionHasNoErrors();
        $this->get('/banco-de-preguntas')->assertOk()->assertSee('Propuestas generadas')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->assertSame([], $this->api->llamadasA('POST', '/teacher/questions'));
        $this->post(route('panel.ia.borrador', ['indice' => 0]))->assertRedirect(route('panel.banco-de-preguntas').'#nueva-pregunta')
            ->assertSessionHasInput('opciones_lista', ['Uno', 'Dos'])->assertSessionHasInput('correcta', 1);
        $this->get('/banco-de-preguntas')->assertOk()->assertSee('Pregunta &lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_drafts_from_another_teacher_or_expired_session_cannot_be_loaded(): void
    {
        foreach ([['docente' => 'other', 'expira' => time() + 100], ['docente' => '94902bd0-fc37-4728-a6e3-6d34b804f100', 'expira' => time() - 1]] as $drafts) {
            $this->withSession(['ia_borradores' => $drafts + ['preguntas' => []]])
                ->post(route('panel.ia.borrador', ['indice' => 0]))->assertNotFound();
        }
    }

    public function test_validation_and_provider_errors_are_visible_and_keep_the_prompt(): void
    {
        $this->post(route('panel.ia.generar'), array_replace($this->generation(), ['cantidad' => 6]))
            ->assertSessionHasErrors(['cantidad'], errorBag: 'ia');
        $this->api->responder('POST', '/teacher/ai/questions', ['message' => 'Cuota de IA alcanzada.'], 429);
        $this->post(route('panel.ia.generar'), $this->generation())->assertSessionHas('ia_error', 'Cuota de IA alcanzada.')
            ->assertSessionHasInput('tema', 'Álgebra');
        $this->get('/banco-de-preguntas')->assertOk()->assertSee('Cuota de IA alcanzada.')->assertSee('Álgebra');
    }

    public function test_grading_form_uses_a_teacher_review_and_does_not_save_the_suggested_score(): void
    {
        $this->prepareAnswer($this->proposal() + ['proveedor' => 'groq', 'modelo' => 'modelo-prueba']);
        $this->get(route('panel.resultados.intento', ['examen' => 2, 'intento' => 22]))
            ->assertOk()->assertSee('Puntaje sugerido: 85 de 100')->assertSee('value="85"', false)
            ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertSee('Esta es una sugerencia.');
        $this->assertSame([], $this->api->llamadasA('PATCH', '/teacher/answers/201/grade'));
    }

    public function test_web_suggestion_checks_the_answer_belongs_to_the_selected_exam(): void
    {
        $this->prepareAnswer();
        $this->post(route('panel.ia.sugerir', ['examen' => 99, 'intento' => 22, 'respuesta' => 201]))->assertNotFound();
        $this->assertSame([], $this->api->llamadasA('POST', '/teacher/ai/answers/201'));
        $this->api->responder('POST', '/teacher/ai/answers/201', ['message' => 'IA no disponible.'], 503);
        $this->post(route('panel.ia.sugerir', ['examen' => 2, 'intento' => 22, 'respuesta' => 201]))
            ->assertSessionHas('ia_respuesta', 201)->assertSessionHas('ia_error', 'IA no disponible.');
        $this->get(route('panel.resultados.intento', ['examen' => 2, 'intento' => 22]))->assertOk()->assertSee('IA no disponible.');
    }

    private function generation(): array
    {
        return ['operacion_id' => '33333333-3333-4333-8333-333333333333', 'tema' => 'Álgebra', 'tipo' => 'opcion_multiple', 'cantidad' => 1, 'nivel' => 'intermedio'];
    }

    private function proposal(): array
    {
        return ['puntaje' => 85, 'justificacion' => 'Concepto correcto <script>alert(1)</script>', 'requiere_revision' => false, 'observaciones' => []];
    }

    private function prepareAnswer(?array $proposal = null): void
    {
        $exam = $this->examenesDeEjemplo(2)[0];
        $this->prepararResultados($exam, [['intento_id' => 22, 'alumno_id' => 1, 'nombre' => 'Prueba', 'estado' => 'finalizado', 'calificadas' => 0, 'total_preguntas' => 1, 'calificacion' => null, 'escala' => '0-10', 'completo' => false]]);
        $this->api->responder('GET', '/teacher/attempts/22/answers', ['data' => ['intento' => ['id' => 22, 'examen_id' => 2, 'estado' => 'finalizado'],
            'respuestas' => [['id' => 201, 'tipo' => 'abierta', 'enunciado' => 'Define variable.', 'respuesta_texto' => 'Un valor que cambia.', 'respuesta_modelo_snapshot' => 'Un valor modificable.', 'calificacion_final' => null, 'revisada_manual' => false, 'ponderacion' => 100, 'puntaje_ia' => $proposal['puntaje'] ?? null, 'retroalimentacion_ia' => $proposal]]]])
            ->responder('GET', '/teacher/attempts/22/incidents', ['data' => []]);
    }
}
