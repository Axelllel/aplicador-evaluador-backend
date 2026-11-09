<?php

/**
 * Archivo: PaginaNoEncontradaTest.php
 * Módulo: tests/Feature
 * Descripción: Verifica que una dirección inexistente muestre la página 404 en español, sin
 *              códigos ni detalles técnicos (Manual COD-WEB, sección 6.18).
 * Autor: Leonardo Ballestero López
 * Creado: 28/10/2026
 * Modificado: 28/10/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class PaginaNoEncontradaTest extends TestCase
{
    public function test_una_direccion_inexistente_muestra_la_pagina_en_espanol(): void
    {
        $this->get('/no-existe-esta-pagina')
            ->assertNotFound()
            ->assertSee('lang="es"', false)
            ->assertSee('No encontramos la página')
            ->assertDontSee('NOT FOUND');
    }
}
