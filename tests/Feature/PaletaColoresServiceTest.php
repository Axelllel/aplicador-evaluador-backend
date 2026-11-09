<?php

/**
 * Archivo: PaletaColoresServiceTest.php
 * Módulo: tests/Feature
 * Descripción: Verifica el cálculo de contraste y que la paleta se lea de resources/css/app.css
 *              (Manual COD-WEB, sección 6.14).
 * Autor: Leonardo Ballestero López
 * Creado: 27/10/2026
 * Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\PaletaColoresService;
use Tests\TestCase;

class PaletaColoresServiceTest extends TestCase
{
    public function test_la_relacion_de_contraste_entre_negro_y_blanco_es_21(): void
    {
        $this->assertEqualsWithDelta(
            21.0,
            app(PaletaColoresService::class)->relacionDeContraste('#000000', '#FFFFFF'),
            0.001,
        );
    }

    public function test_la_paleta_incluye_los_diez_tokens_de_la_tabla_12_en_orden(): void
    {
        $tokens = array_column(app(PaletaColoresService::class)->colores(), 'token');

        $this->assertSame(
            ['primario', 'acento', 'acento-claro', 'borde', 'texto-suave', 'white', 'superficie', 'exito', 'aviso', 'error'],
            array_slice($tokens, 0, 10),
        );
    }

    public function test_los_hexadecimales_coinciden_con_app_css(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        foreach (app(PaletaColoresService::class)->colores() as $color) {
            if ($color['token'] === 'white') {
                continue;
            }

            $this->assertStringContainsString(
                '--color-'.$color['token'].': '.strtolower($color['hex']).';',
                strtolower($css),
            );
        }
    }

    public function test_los_hallazgos_son_los_tres_pares_conocidos(): void
    {
        $claves = array_column(app(PaletaColoresService::class)->hallazgos(), 'clave');

        $this->assertSame(['borde_componentes', 'acento_en_tarjeta', 'acento_claro_como_texto'], $claves);
    }

    public function test_el_texto_principal_cumple_el_minimo_de_4_5_a_1(): void
    {
        $pares = collect(app(PaletaColoresService::class)->pares())->keyBy('clave');

        $this->assertTrue($pares['texto_principal']['cumple']);
        $this->assertTrue($pares['barras_y_botones']['cumple']);
        $this->assertTrue($pares['enlace_sobre_blanco']['cumple']);
    }
}
