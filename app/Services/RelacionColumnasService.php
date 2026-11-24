<?php

/**
 * Archivo: RelacionColumnasService.php
 * Módulo: app/Services
 * Descripción: Presenta las selecciones de columnas con las referencias del examen, sin exponer JSON como respuesta legible.
 * Autor: Axel Rebollo Zeferino
 * Creado: 23/11/2026
 * Modificado: 23/11/2026 · Equipo BigBitByte · Revisión de columnas
 */

declare(strict_types=1);

namespace App\Services;

class RelacionColumnasService
{
    /** Traduce la respuesta serializada usando las etiquetas y la referencia del examen. */
    public function revision(?string $texto, ?string $modelo, array $opciones): ?array
    {
        if (! array_is_list($opciones) || count($opciones) < 2) {
            return null;
        }
        $lineas = array_values(array_filter(preg_split('/\R/u', $modelo ?? '') ?: [], fn (string $linea): bool => trim($linea) !== ''));
        $referenciaLegible = count($lineas) === count($opciones);
        $valores = json_decode($texto ?? '', true);
        $legible = is_array($valores) && count($valores) <= count($opciones)
            && count(array_filter($valores, fn (mixed $valor): bool => ! is_string($valor) && $valor !== null)) === 0;
        $filas = [];
        foreach ($opciones as $indice => $par) {
            if (! is_array($par) || ! is_string($par['izquierda'] ?? null)) {
                return null;
            }
            $izquierda = trim($par['izquierda']);
            $prefijo = $izquierda.' = ';
            $coincidencias = array_values(array_filter($lineas, fn (string $linea): bool => str_starts_with(trim($linea), $prefijo)));
            $correcta = count($coincidencias) === 1 ? trim(substr(trim($coincidencias[0]), strlen($prefijo))) : null;
            $referenciaLegible = $referenciaLegible && filled($correcta);
            $seleccion = $legible ? (array_is_list($valores) ? ($valores[$indice] ?? null) : ($valores[$izquierda] ?? null)) : null;
            $filas[] = ['izquierda' => $izquierda, 'respuesta' => is_string($seleccion) && trim($seleccion) !== '' ? $seleccion : null, 'modelo' => $correcta];
        }

        return ['filas' => $filas, 'formato_reconocido' => $legible || blank($texto), 'referencia_reconocida' => $referenciaLegible];
    }
}
