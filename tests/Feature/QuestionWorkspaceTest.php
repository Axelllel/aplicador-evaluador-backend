<?php

/**
 * Archivo: QuestionWorkspaceTest.php
 * Módulo: tests/Feature
 * Descripción: Verifica el contrato de preguntas estructuradas, compatibilidad y búsqueda paginada.
 * Autor: Axel Rebollo Zeferino
 * Creado: 19/11/2026
 * Modificado: 19/11/2026 · Cobertura del editor de preguntas
 */

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ConPanelFalso;
use Tests\TestCase;

class QuestionWorkspaceTest extends TestCase
{
    use ConPanelFalso;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararPanel();
        $this->conSesionDelPanel();
        $this->api->responder('POST', '/teacher/questions', ['data' => ['id' => 25]], 201);
    }

    public function test_multiple_choice_uses_the_selected_option_as_the_model_answer(): void
    {
        $this->post('/banco-de-preguntas', [
            'tipo' => 'opcion_multiple', 'enunciado' => '¿Qué es una variable?',
            'editor_estructurado' => '1', 'opciones_lista' => ['  Un valor que puede cambiar  ', 'Un ciclo'], 'correcta' => '0',
        ])->assertRedirect(route('panel.banco-de-preguntas'))->assertSessionHasNoErrors();

        $this->assertSame([
            'tipo' => 'opcion_multiple', 'enunciado' => '¿Qué es una variable?',
            'opciones' => ['Un valor que puede cambiar', 'Un ciclo'], 'respuesta_modelo' => 'Un valor que puede cambiar',
        ], $this->api->llamadasA('POST', '/teacher/questions')[0]['datos']);
    }

    public function test_matching_pairs_preserve_punctuation_without_delimiter_parsing(): void
    {
        $this->post('/banco-de-preguntas', [
            'tipo' => 'relacion_columnas', 'enunciado' => 'Relaciona los operadores.', 'editor_estructurado' => '1',
            'pares_lista' => [['izquierda' => 'a | b', 'derecha' => 'Operador OR'], ['izquierda' => 'a & b', 'derecha' => 'Operador AND']],
        ])->assertSessionHasNoErrors();

        $data = $this->api->llamadasA('POST', '/teacher/questions')[0]['datos'];
        $this->assertSame([['izquierda' => 'a | b', 'derecha' => 'Operador OR'], ['izquierda' => 'a & b', 'derecha' => 'Operador AND']], $data['opciones']);
        $this->assertSame("a | b = Operador OR\na & b = Operador AND", $data['respuesta_modelo']);
    }

    #[DataProvider('invalidOptions')]
    public function test_invalid_structured_questions_never_reach_the_api(array $changes, string $field): void
    {
        $input = array_replace([
            'tipo' => 'opcion_multiple', 'enunciado' => 'Elige la respuesta.', 'editor_estructurado' => '1',
            'opciones_lista' => ['Uno', 'Dos'], 'correcta' => 0,
        ], $changes);
        $this->from('/banco-de-preguntas')->post('/banco-de-preguntas', $input)
            ->assertRedirect('/banco-de-preguntas')->assertSessionHasErrors($field)
            ->assertSessionHasInput('enunciado', 'Elige la respuesta.');
        $this->assertSame([], $this->api->llamadasA('POST', '/teacher/questions'));
    }

    public static function invalidOptions(): array
    {
        return [
            'selection outside list' => [['correcta' => 9], 'correcta'],
            'missing selection' => [['correcta' => null], 'correcta'],
            'duplicates' => [['opciones_lista' => ['Uno', 'Uno']], 'opciones_lista'],
            'blank option' => [['opciones_lista' => ['', 'Dos']], 'opciones_lista.0'],
            'too many options' => [['opciones_lista' => range(1, 11)], 'opciones_lista'],
            'non-list indexes' => [['opciones_lista' => [1 => 'Uno', 2 => 'Dos']], 'opciones_lista'],
            'missing pair side' => [['tipo' => 'relacion_columnas', 'pares_lista' => [['izquierda' => 'A', 'derecha' => ''], ['izquierda' => 'B', 'derecha' => 'Dos']]], 'pares_lista.0.derecha'],
        ];
    }

    public function test_plain_text_form_remains_compatible_when_javascript_is_unavailable(): void
    {
        $this->post('/banco-de-preguntas', [
            'tipo' => 'opcion_multiple', 'enunciado' => 'Pregunta sin JavaScript', 'opciones' => "Uno\nDos", 'respuesta_modelo' => 'Dos',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Dos', $this->api->llamadasA('POST', '/teacher/questions')[0]['datos']['respuesta_modelo']);
    }

    public function test_search_filters_the_entire_bank_before_pagination_and_escapes_the_content(): void
    {
        $questions = $this->preguntasDeEjemplo(40);
        $questions[39]['enunciado'] = 'Palabra única <script>alert(1)</script>';
        $this->prepararLecturas(['preguntas' => $questions]);
        $this->get('/banco-de-preguntas?q=Palabra%20%C3%BAnica&page=9')
            ->assertOk()->assertViewHas('busqueda', 'Palabra única')
            ->assertViewHas('preguntas', fn ($questions): bool => $questions->total() === 1 && $questions->currentPage() === 1)
            ->assertSee('Palabra única &lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_open_questions_do_not_require_options_and_preserve_the_reference(): void
    {
        $this->post('/banco-de-preguntas', [
            'tipo' => 'abierta', 'enunciado' => 'Explica el concepto de algoritmo.',
            'editor_estructurado' => '1', 'respuesta_modelo' => 'Una secuencia finita de pasos.',
        ])->assertSessionHasNoErrors();
        $this->assertSame(['tipo' => 'abierta', 'enunciado' => 'Explica el concepto de algoritmo.', 'respuesta_modelo' => 'Una secuencia finita de pasos.'], $this->api->llamadasA('POST', '/teacher/questions')[0]['datos']);
    }
}
