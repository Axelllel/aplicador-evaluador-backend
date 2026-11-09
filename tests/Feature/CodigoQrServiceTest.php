<?php

/**
 * Archivo: CodigoQrServiceTest.php
 * Módulo: tests/Feature
 * Descripción: Verifica que el QR del grupo se genere como SVG de 256 × 256 px
 *              (Manual COD-WEB, sección 6.17, Tabla 15).
 * Autor: Leonardo Ballestero López
 * Creado: 27/10/2026
 * Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\CodigoQrService;
use Tests\TestCase;

class CodigoQrServiceTest extends TestCase
{
    public function test_el_qr_se_renderiza_como_svg(): void
    {
        $svg = app(CodigoQrService::class)->generarSvg('ABC-1234');

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);
        $this->assertStringNotContainsString('<?xml', $svg);
    }

    public function test_el_qr_mide_256_por_256_px(): void
    {
        $svg = app(CodigoQrService::class)->generarSvg('ABC-1234');

        $this->assertMatchesRegularExpression('/width="256"/', $svg);
        $this->assertMatchesRegularExpression('/height="256"/', $svg);
    }

    public function test_el_qr_lleva_texto_alternativo_con_el_folio(): void
    {
        $svg = app(CodigoQrService::class)->generarSvg('ABC-1234');

        $this->assertStringContainsString('role="img"', $svg);
        $this->assertStringContainsString('aria-label="Código QR del grupo con folio ABC-1234"', $svg);
    }

    public function test_folios_distintos_generan_codigos_distintos(): void
    {
        $servicio = app(CodigoQrService::class);

        $this->assertNotSame($servicio->generarSvg('ABC-1234'), $servicio->generarSvg('XYZ-9876'));
    }

    public function test_el_uri_de_descarga_es_un_svg_en_base64(): void
    {
        $servicio = app(CodigoQrService::class);
        $svg = $servicio->generarSvg('ABC-1234');

        $this->assertSame('data:image/svg+xml;base64,'.base64_encode($svg), $servicio->comoUriDeDescarga($svg));
    }
}
