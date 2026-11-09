<?php

/**
 * Archivo: CodigoQrService.php
 * Módulo: app/Services
 * Descripción: Genera el código QR del grupo como SVG a partir de su folio
 *              (Manual COD-WEB, sección 6.17, Tabla 15).
 * Autor: Leonardo Ballestero López
 * Creado: 27/10/2026
 * Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Services;

use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Genera el código QR del grupo en formato SVG.
 */
class CodigoQrService
{
    public const TAMANO_EN_PIXELES = 256;

    /**
     * Devuelve el QR del folio como SVG de 256 × 256 px listo para incrustar.
     */
    public function generarSvg(string $folio): string
    {
        $svg = (string) QrCode::format('svg')
            ->size(self::TAMANO_EN_PIXELES)
            ->margin(2)
            ->errorCorrection('M')
            ->generate($folio);

        // La declaración XML no es válida dentro de HTML; se quita para incrustar el SVG.
        $svg = (string) preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg);

        $descripcion = e(__('panel.qr.descripcion', ['folio' => $folio]));

        return (string) preg_replace('/^<svg /', '<svg role="img" aria-label="'.$descripcion.'" ', $svg, 1);
    }

    /**
     * Devuelve el SVG como URI de datos para el botón de descarga.
     */
    public function comoUriDeDescarga(string $svg): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
