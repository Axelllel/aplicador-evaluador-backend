<?php

/**
 * Archivo: ExampleTest.php
 * Módulo: tests/Feature
 * Descripción: Verifica que la raíz del sitio ofrece los dos accesos por perfil.
 * Autor: Leonardo Ballestero López
 * Creado: 27/10/2026
 */

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_la_raiz_ofrece_los_accesos_de_docente_y_alumno(): void
    {
        $this->withoutVite();

        $this->get('/')
            ->assertOk()
            ->assertSee('Selecciona tu perfil')
            ->assertSee('Docente')
            ->assertSee('Alumno')
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('href="'.route('student.entry').'"', false);

        $this->get('/login')->assertOk()->assertSee('Inicia sesión en tu espacio docente');
        $this->get('/alumno/ingreso')->assertOk()->assertSee('Folio del grupo');
    }
}
