<?php

/**
 * Archivo: ReglasPregunta.php
 * Módulo: app/Support
 * Descripción: Reglas compartidas por el formulario web y la API para el banco de preguntas: enunciado legible,
 *              opciones y parejas sin repetir y lenguaje de las preguntas de programación
 *              (Manual COD-WEB, secciones 6.10 y 6.18).
 * Autor: Leonardo Ballestero López
 * Creado: 24/11/2026
 * Modificado: 24/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Support;

/**
 * Concentra las reglas del banco de preguntas para que web y API no se desfasen.
 */
final class ReglasPregunta
{
    /** Lenguajes que ofrece el menú; además existen «Otro» (texto libre del docente) y «Libre» (lo elige el alumno). */
    public const LENGUAJES = ['Python', 'JavaScript', 'Java', 'C', 'C#', 'C++', 'PHP', 'SQL', 'HTML y CSS'];

    /** Valor guardado cuando el alumno puede responder en el lenguaje que prefiera. */
    public const LENGUAJE_LIBRE = 'Libre';

    /** Máximo de opciones o parejas por pregunta. */
    public const MAXIMO_DE_OPCIONES = 10;

    /**
     * Reglas del enunciado: con contenido real y sin caracteres de control ni invisibles (se permiten saltos de línea).
     *
     * @return array<int, string>
     */
    public static function enunciado(): array
    {
        return [
            'required', 'string', 'min:3', 'max:10000',
            'regex:/^(?=.*[\p{L}\p{N}])[^\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x{200B}\x{200C}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2064}\x{FEFF}]+$/u',
        ];
    }

    /**
     * Reglas del lenguaje guardado: texto corto con letras, números y signos habituales (C#, C++, HTML y CSS).
     *
     * @return array<int, string>
     */
    public static function lenguaje(): array
    {
        return ['nullable', 'string', 'max:40', 'regex:/^[\p{L}\p{N} .+#\-]+$/u'];
    }

    /**
     * Texto en minúsculas y con los espacios colapsados, para comparar sin distinguir mayúsculas.
     */
    public static function normalizar(string $texto): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($texto)));
    }

    /**
     * Indica si alguna opción se repite, sin distinguir mayúsculas ni espacios de más.
     *
     * @param  array<int, mixed>  $textos
     */
    public static function hayRepetidos(array $textos): bool
    {
        $normalizados = array_map(fn (mixed $texto): string => self::normalizar((string) $texto), $textos);

        return count(array_unique($normalizados)) !== count($normalizados);
    }

    /**
     * Indica si dos parejas son idénticas o comparten el mismo concepto (la relación sería ambigua).
     *
     * @param  array<int, array{izquierda?: mixed, derecha?: mixed}>  $pares
     */
    public static function hayParesRepetidos(array $pares): bool
    {
        $conceptos = array_map(fn (array $par): string => self::normalizar((string) ($par['izquierda'] ?? '')), $pares);

        return count(array_unique($conceptos)) !== count($conceptos);
    }

    /**
     * Valor final del lenguaje a partir de la elección del menú y del texto de «Otro».
     */
    public static function lenguajeFinal(?string $eleccion, ?string $otro): ?string
    {
        return match (true) {
            $eleccion === null || $eleccion === '' => null,
            $eleccion === 'libre' => self::LENGUAJE_LIBRE,
            $eleccion === 'otro' => trim((string) $otro),
            default => $eleccion,
        };
    }

    /**
     * Elección del menú y texto de «Otro» a partir del valor guardado, para precargar el formulario de edición.
     *
     * @return array{eleccion: string, otro: string}
     */
    public static function eleccionDesdeGuardado(?string $guardado): array
    {
        return match (true) {
            $guardado === null || $guardado === '' => ['eleccion' => '', 'otro' => ''],
            $guardado === self::LENGUAJE_LIBRE => ['eleccion' => 'libre', 'otro' => ''],
            in_array($guardado, self::LENGUAJES, true) => ['eleccion' => $guardado, 'otro' => ''],
            default => ['eleccion' => 'otro', 'otro' => $guardado],
        };
    }
}
