<?php

/**
 * Archivo: PaletaColoresService.php
 * Módulo: app/Services
 * Descripción: Lee la paleta de identidad desde resources/css/app.css (única fuente de los colores)
 *              y calcula las relaciones de contraste para la guía de estilo
 *              (Manual COD-WEB, secciones 6.14 y 6.15).
 * Autor: Leonardo Ballestero López
 * Creado: 27/10/2026
 * Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Services;

/**
 * Lee la paleta de app.css y calcula sus relaciones de contraste.
 */
class PaletaColoresService
{
    public const MINIMO_TEXTO = 4.5;

    public const MINIMO_COMPONENTE = 3.0;

    /** El blanco no se declara en @theme porque es el color base de Tailwind. */
    private const HEX_BLANCO = '#FFFFFF';

    /** Orden de la Tabla 12 del Manual COD-WEB. */
    private const ORDEN = [
        'primario',
        'acento',
        'acento-claro',
        'borde',
        'texto-suave',
        'white',
        'superficie',
        'exito',
        'aviso',
        'error',
        // Se agregó para la serie secundaria de las gráficas (Manual de Calidad, sección 3.10).
        'acento-medio',
    ];

    /**
     * Pares de uso: [color del elemento, fondo, clave de uso, relación mínima].
     * Los tres últimos son los hallazgos que la guía lista aparte.
     */
    private const PARES = [
        ['primario', 'white', 'texto_principal', self::MINIMO_TEXTO],
        ['primario', 'superficie', 'texto_en_tarjeta', self::MINIMO_TEXTO],
        ['white', 'primario', 'barras_y_botones', self::MINIMO_TEXTO],
        ['texto-suave', 'white', 'texto_secundario', self::MINIMO_TEXTO],
        ['texto-suave', 'superficie', 'texto_secundario_en_tarjeta', self::MINIMO_TEXTO],
        ['acento', 'white', 'enlace_sobre_blanco', self::MINIMO_TEXTO],
        ['error', 'white', 'error_sobre_blanco', self::MINIMO_TEXTO],
        ['error', 'superficie', 'error_en_tarjeta', self::MINIMO_TEXTO],
        ['exito', 'white', 'exito_sobre_blanco', self::MINIMO_TEXTO],
        ['exito', 'superficie', 'exito_en_tarjeta', self::MINIMO_TEXTO],
        ['aviso', 'white', 'aviso_sobre_blanco', self::MINIMO_TEXTO],
        ['aviso', 'superficie', 'aviso_en_tarjeta', self::MINIMO_TEXTO],
        ['white', 'exito', 'banner_exito', self::MINIMO_TEXTO],
        ['white', 'aviso', 'banner_advertencia', self::MINIMO_TEXTO],
        ['white', 'error', 'banner_error', self::MINIMO_TEXTO],
        ['borde', 'white', 'borde_componentes', self::MINIMO_COMPONENTE],
        ['acento', 'superficie', 'acento_en_tarjeta', self::MINIMO_TEXTO],
        ['acento-claro', 'white', 'acento_claro_como_texto', self::MINIMO_TEXTO],
    ];

    private const HALLAZGOS = ['borde_componentes', 'acento_en_tarjeta', 'acento_claro_como_texto'];

    /** @var array<string, string>|null */
    private ?array $hexPorToken = null;

    /**
     * Devuelve los colores de la paleta con su relación de contraste sobre blanco
     * y con texto blanco encima.
     *
     * @return list<array{token: string, hex: string, sobre_blanco: float, texto_blanco: float}>
     */
    public function colores(): array
    {
        $colores = [];

        foreach ($this->leerHexPorToken() as $token => $hex) {
            $colores[] = [
                'token' => $token,
                'hex' => strtoupper($hex),
                'sobre_blanco' => round($this->relacionDeContraste($hex, self::HEX_BLANCO), 2),
                'texto_blanco' => round($this->relacionDeContraste(self::HEX_BLANCO, $hex), 2),
            ];
        }

        return $colores;
    }

    /**
     * Devuelve todos los pares de uso con su relación de contraste y su resultado.
     *
     * @return list<array{clave: string, texto: string, fondo: string, hex_texto: string, hex_fondo: string,
     *     relacion: float, minimo: float, cumple: bool}>
     */
    public function pares(): array
    {
        $hexPorToken = $this->leerHexPorToken();
        $pares = [];

        foreach (self::PARES as [$texto, $fondo, $clave, $minimo]) {
            if (! isset($hexPorToken[$texto], $hexPorToken[$fondo])) {
                continue;
            }

            $relacion = $this->relacionDeContraste($hexPorToken[$texto], $hexPorToken[$fondo]);

            $pares[] = [
                'clave' => $clave,
                'texto' => $texto,
                'fondo' => $fondo,
                'hex_texto' => strtoupper($hexPorToken[$texto]),
                'hex_fondo' => strtoupper($hexPorToken[$fondo]),
                'relacion' => round($relacion, 2),
                'minimo' => $minimo,
                'cumple' => $relacion >= $minimo,
            ];
        }

        return $pares;
    }

    /**
     * Devuelve solo los pares que la guía lista como hallazgos de contraste.
     *
     * @return list<array{clave: string, texto: string, fondo: string, hex_texto: string, hex_fondo: string,
     *     relacion: float, minimo: float, cumple: bool}>
     */
    public function hallazgos(): array
    {
        return array_values(array_filter(
            $this->pares(),
            fn (array $par): bool => in_array($par['clave'], self::HALLAZGOS, true),
        ));
    }

    /**
     * Calcula la relación de contraste WCAG entre dos colores hexadecimales.
     */
    public function relacionDeContraste(string $hexA, string $hexB): float
    {
        $luminanciaA = $this->calcularLuminancia($hexA);
        $luminanciaB = $this->calcularLuminancia($hexB);

        $mayor = max($luminanciaA, $luminanciaB);
        $menor = min($luminanciaA, $luminanciaB);

        return ($mayor + 0.05) / ($menor + 0.05);
    }

    /**
     * Lee los tokens --color-* de app.css y los ordena según la Tabla 12.
     *
     * @return array<string, string>
     */
    private function leerHexPorToken(): array
    {
        if ($this->hexPorToken !== null) {
            return $this->hexPorToken;
        }

        $css = (string) file_get_contents(resource_path('css/app.css'));
        preg_match_all('/--color-([a-z-]+)\s*:\s*(#[0-9a-fA-F]{3,6})\s*;/', $css, $coincidencias, PREG_SET_ORDER);

        $declarados = ['white' => self::HEX_BLANCO];

        foreach ($coincidencias as $coincidencia) {
            $declarados[$coincidencia[1]] = $coincidencia[2];
        }

        $ordenados = [];

        foreach (self::ORDEN as $token) {
            if (isset($declarados[$token])) {
                $ordenados[$token] = $declarados[$token];
            }
        }

        // Los tokens nuevos que agregue la auditoría aparecen al final de la lista.
        $this->hexPorToken = $ordenados + $declarados;

        return $this->hexPorToken;
    }

    private function calcularLuminancia(string $hex): float
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        $canales = [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];

        $lineales = array_map(
            fn (float $canal): float => $canal <= 0.03928 ? $canal / 12.92 : (($canal + 0.055) / 1.055) ** 2.4,
            $canales,
        );

        return 0.2126 * $lineales[0] + 0.7152 * $lineales[1] + 0.0722 * $lineales[2];
    }
}
