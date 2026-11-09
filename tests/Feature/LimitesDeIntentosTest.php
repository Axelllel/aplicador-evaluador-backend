<?php

/**
 * Archivo: LimitesDeIntentosTest.php
 * Módulo: tests/Feature
 * Descripción: Verifica que cada límite de intentos (throttle) del portal del alumno y de la API tenga su
 *              propio contador: sin prefijo, todas las rutas con «throttle:N,1» comparten un mismo contador
 *              por IP y un alumno que responde preguntas se quedaba sin poder entregar el examen.
 * Autor: Leonardo Ballestero López
 * Creado: 09/11/2026
 * Modificado: 09/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class LimitesDeIntentosTest extends TestCase
{
    public function test_los_intentos_de_una_ruta_no_gastan_el_limite_de_otra(): void
    {
        // Cinco consultas de folio (el límite es 10) no deben acercar al alumno al límite de ingreso (5).
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/alumno/validar-folio', [])->assertStatus(422);
        }

        // Con un contador compartido esta petición ya sería la sexta y devolvería 429.
        $this->postJson('/alumno/ingresar', [])->assertStatus(422);
    }

    public function test_una_ruta_si_se_bloquea_al_pasar_su_propio_limite(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/alumno/ingresar', [])->assertStatus(422);
        }

        $this->postJson('/alumno/ingresar', [])->assertStatus(429);
    }

    public function test_el_limite_del_login_de_la_api_no_depende_del_de_unirse_al_grupo(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/grupos/unirse', [])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', [])->assertStatus(422);
    }
}
