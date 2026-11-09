<?php

/**
 * Archivo: ExampleTest.php
 * Módulo: tests/Feature
 * Descripción: Verifica que la raíz del sitio redirige al inicio de sesión.
 * Autor: Leonardo Ballestero López
 * Creado: 27/10/2026
 * Modificado: 27/10/2026 · Leonardo Ballestero López · GET / ahora redirige a /login
 */

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_la_raiz_redirige_al_inicio_de_sesion(): void
    {
        $this->get('/')->assertRedirect('/login');
    }
}
