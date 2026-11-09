<?php

/**
 * Archivo: CabecerasDeSeguridadTest.php
 * Módulo: tests/Feature
 * Descripción: Verifica las cabeceras de seguridad HTTP en el panel, el portal del alumno y la API,
 *              y que HSTS solo se envíe por HTTPS.
 * Autor: Leonardo Ballestero López
 * Creado: 09/11/2026
 * Modificado: 09/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class CabecerasDeSeguridadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_las_paginas_publicas_llevan_las_cabeceras_de_seguridad(): void
    {
        foreach (['/login', '/alumno/ingreso'] as $ruta) {
            $this->get($ruta)
                ->assertOk()
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');
        }
    }

    public function test_las_respuestas_de_la_api_tambien_llevan_nosniff(): void
    {
        $this->getJson('/api/v1/teacher/groups')
            ->assertUnauthorized()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_hsts_solo_se_envia_por_https(): void
    {
        $this->get('http://localhost/login')->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/login')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
