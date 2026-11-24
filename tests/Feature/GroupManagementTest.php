<?php

/**
 * Archivo: GroupManagementTest.php
 * Módulo: tests/Feature
 * Descripción: Comprueba confirmación escrita, destinos y errores de la eliminación de grupos y alumnos.
 * Autor: Axel Rebollo Zeferino
 * Creado: 24/11/2026
 * Modificado: 24/11/2026 · Equipo BigBitByte · Gestión de grupos
 */

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\ConPanelFalso;
use Tests\TestCase;

class GroupManagementTest extends TestCase
{
    use ConPanelFalso;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararPanel();
        $this->conSesionDelPanel();
        $this->prepararLecturas(['grupos' => $this->gruposDeEjemplo(1)]);
    }

    public function test_group_confirmation_shows_impact_and_requires_the_exact_word_before_calling_api(): void
    {
        $this->api->responder('GET', '/teacher/groups/1/deletion-preview', ['data' => $this->summary()]);
        $this->get('/grupos')->assertOk()->assertSee(route('panel.grupos.confirmar-eliminacion', 1), false);
        $this->get('/grupos/1/eliminar')->assertOk()->assertSee('Esta acción no se puede deshacer.')
            ->assertSee('Exámenes')->assertSee('ELIMINAR')->assertSee('name="_method" value="DELETE"', false);
        foreach (['', 'eliminar', 'BORRAR'] as $word) {
            $this->delete('/grupos/1', ['confirmacion' => $word])->assertSessionHasErrors('confirmacion');
        }
        $this->assertSame([], $this->api->llamadasA('DELETE', '/teacher/groups/1'));
        $this->api->responder('DELETE', '/teacher/groups/1', ['data' => ['accion' => 'grupo_eliminado', 'grupo_id' => 1]]);
        $this->delete('/grupos/1', ['confirmacion' => 'ELIMINAR'])->assertRedirect(route('panel.grupos'))
            ->assertSessionHas('exito', __('grupos.eliminacion.grupo_eliminado'));
        $this->assertCount(1, $this->api->llamadasA('DELETE', '/teacher/groups/1'));
    }

    public function test_student_confirmation_targets_only_the_selected_group_and_keeps_identity_explicit(): void
    {
        $this->api->responder('GET', '/teacher/groups/1/students', ['data' => [['id' => 14, 'nombre' => 'Alumno de prueba', 'fecha_union' => '2026-11-01', 'intentos_en_grupo' => 2, 'intentos_en_curso' => 0]]]);
        $this->get('/grupos/1/alumnos')->assertOk()->assertSee(route('panel.grupos.alumnos.confirmar-eliminacion', ['grupo' => 1, 'alumno' => 14]), false);
        $this->get('/grupos/1/alumnos/14/eliminar')->assertOk()->assertSee('Alumno de prueba')->assertSee('otros grupos se conservan');
        $this->get('/grupos/1/alumnos/99/eliminar')->assertNotFound();
        $this->get('/grupos/99/alumnos/14/eliminar')->assertNotFound();
        $this->delete('/grupos/1/alumnos/14', ['confirmacion' => 'no'])->assertSessionHasErrors('confirmacion');
        $this->assertSame([], $this->api->llamadasA('DELETE', '/teacher/groups/1/students/14'));
        $this->api->responder('DELETE', '/teacher/groups/1/students/14', ['data' => ['accion' => 'alumno_eliminado']]);
        $this->delete('/grupos/1/alumnos/14', ['confirmacion' => 'ELIMINAR'])->assertRedirect(route('panel.grupos.alumnos', 1))
            ->assertSessionHas('exito', __('grupos.eliminacion.alumno_eliminado'));
    }

    public function test_an_active_attempt_blocks_the_confirmation_and_backend_errors_are_visible(): void
    {
        $this->api->responder('GET', '/teacher/groups/1/deletion-preview', ['data' => $this->summary() + []]);
        $summary = $this->summary();
        $summary['intentos_en_curso'] = 1;
        $this->api->responder('GET', '/teacher/groups/1/deletion-preview', ['data' => $summary]);
        $this->get('/grupos/1/eliminar')->assertOk()->assertSee('Hay un examen en curso.')->assertDontSee('name="confirmacion"', false);
        $this->api->responder('DELETE', '/teacher/groups/1', ['message' => 'Hay un examen en curso. Espera a que termine antes de retirar al alumno o al grupo.'], 409);
        $this->from('/grupos/1/eliminar')->delete('/grupos/1', ['confirmacion' => 'ELIMINAR'])
            ->assertRedirect('/grupos/1/eliminar')->assertSessionHas('error', __('grupos.eliminacion.en_curso'));
        $this->api->responder('GET', '/teacher/groups/99/deletion-preview', ['message' => 'No disponible.'], 404);
        $this->get('/grupos/99/eliminar')->assertNotFound();
    }

    private function summary(): array
    {
        return ['id' => 1, 'materia' => 'Historia', 'folio_codigo' => 'FOLIO123', 'activo' => true,
            'alumnos' => 3, 'examenes' => 2, 'intentos' => 4, 'evaluaciones' => 1, 'intentos_en_curso' => 0];
    }
}
