<?php

/**
 * Archivo: ExamCompositionWebTest.php
 * Módulo: tests/Feature
 * Descripción: Valida el flujo web sin creación manual de apartados y su contrato con la API.
 * Autor: Axel Rebollo Zeferino
 * Creado: 20/11/2026
 * Modificado: 20/11/2026 · Composición automática del examen
 */

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\ConPanelFalso;
use Tests\TestCase;

class ExamCompositionWebTest extends TestCase
{
    use ConPanelFalso;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararPanel();
        $this->conSesionDelPanel();
        $this->prepararLecturas();
        $this->api->responder('GET', '/teacher/exams/1/sections', ['data' => []]);
    }

    public function test_a_draft_with_no_sections_can_receive_questions_directly(): void
    {
        $this->get('/examenes/1/apartados')->assertOk()
            ->assertSee('name="pregunta_id"', false)->assertSee('data-exam-composer', false)
            ->assertDontSee('Nuevo apartado')->assertDontSee('name="apartado_id"', false)
            ->assertSee('Falta asignar 100.00 %');
    }

    public function test_the_add_form_sends_only_the_question_time_and_weight_to_the_automatic_endpoint(): void
    {
        $this->api->responder('POST', '/teacher/exams/1/questions', ['data' => ['examen_pregunta_id' => 5]], 201);
        $this->post('/examenes/1/preguntas', ['pregunta_id' => 3, 'tiempo_segundos' => 90, 'ponderacion' => '25.50'])
            ->assertSessionHasNoErrors()->assertRedirect(route('panel.examenes.apartados', ['examen' => 1]));
        $this->assertSame(['pregunta_id' => 3, 'tiempo_segundos' => 90, 'ponderacion' => 25.5], $this->api->llamadasA('POST', '/teacher/exams/1/questions')[0]['datos']);
    }

    public function test_publication_explicitly_requests_automatic_counts(): void
    {
        $this->api->responder('POST', '/teacher/exams/1/publish', ['data' => ['id' => 1, 'publicado' => true]]);
        $this->post('/examenes/1/publicar')->assertSessionHasNoErrors();
        $this->assertSame(['composicion_automatica' => true], $this->api->llamadasA('POST', '/teacher/exams/1/publish')[0]['datos']);
    }

    public function test_invalid_weight_retains_the_selected_question_and_does_not_call_the_api(): void
    {
        $this->from('/examenes/1/apartados')->post('/examenes/1/preguntas', ['pregunta_id' => 3, 'tiempo_segundos' => 90, 'ponderacion' => '25.555'])
            ->assertSessionHasErrors('ponderacion')->assertSessionHasInput('pregunta_id', 3);
        $this->assertSame([], $this->api->llamadasA('POST', '/teacher/exams/1/questions'));
    }
}
