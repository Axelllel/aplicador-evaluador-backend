<?php

/**
 * Archivo: ReglasExamen.php
 * Módulo: app/Support
 * Descripción: Reglas de validación compartidas por el formulario web y la API para dar de alta un examen:
 *              nombre legible y ventana de fechas coherente (Manual COD-WEB, secciones 6.10 y 6.18).
 * Autor: Leonardo Ballestero López
 * Creado: 23/11/2026
 * Modificado: 23/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Closure;
use Throwable;

/**
 * Concentra las reglas del alta de examen para que web y API no se desfasen.
 */
final class ReglasExamen
{
    /** Año mínimo aceptado para la apertura de un examen. */
    public const ANIO_MINIMO = 2000;

    /** Minutos de tolerancia para una apertura «ahora» (el docente tarda en enviar el formulario). */
    public const TOLERANCIA_MINUTOS = 5;

    /** Longitud máxima del nombre de un examen, en caracteres. */
    public const LONGITUD_MAXIMA_NOMBRE = 30;

    /** Duración máxima de la ventana de aplicación, en días. */
    public const DURACION_MAXIMA_DIAS = 30;

    /**
     * Zona horaria en la que el docente escribe las fechas del formulario.
     */
    public static function zonaDelDocente(): string
    {
        return (string) config('app.timezone_docente', 'America/Mexico_City');
    }

    /**
     * Reglas del nombre: texto visible, sin caracteres de control ni invisibles, con al menos una letra o número.
     *
     * @return array<int, string>
     */
    public static function nombre(): array
    {
        return ['required', 'string', 'min:2', 'max:'.self::LONGITUD_MAXIMA_NOMBRE, 'regex:/^(?=.*[\p{L}\p{N}])[^\p{Cc}\p{Cf}]+$/u'];
    }

    /**
     * Reglas de la fecha de apertura.
     *
     * @return array<int, mixed>
     */
    public static function apertura(string $zona): array
    {
        return ['required', 'date', function (string $atributo, mixed $valor, Closure $falla) use ($zona): void {
            $instante = self::instante($valor, $zona);

            if ($instante === null) {
                return;
            }

            if ($instante->year < self::ANIO_MINIMO) {
                $falla(__('examenes.errores.apertura_antigua', ['anio' => self::ANIO_MINIMO]));
            } elseif ($instante->lessThan(CarbonImmutable::now('UTC')->subMinutes(self::TOLERANCIA_MINUTOS))) {
                $falla(__('examenes.errores.apertura_pasada'));
            }
        }];
    }

    /**
     * Reglas de la fecha de cierre; se compara contra la apertura enviada en la misma solicitud.
     *
     * @return array<int, mixed>
     */
    public static function cierre(string $zona, string $campoApertura = 'fecha_apertura'): array
    {
        return ['required', 'date', 'after:'.$campoApertura, function (string $atributo, mixed $valor, Closure $falla) use ($zona, $campoApertura): void {
            $cierre = self::instante($valor, $zona);
            $apertura = self::instante(request()->input($campoApertura), $zona);

            // Con una apertura inválida ya se avisó en su campo; no se repite el error en el cierre.
            if ($cierre === null || $apertura === null || $apertura->year < self::ANIO_MINIMO) {
                return;
            }

            if ($cierre->greaterThan($apertura->addDays(self::DURACION_MAXIMA_DIAS))) {
                $falla(__('examenes.errores.duracion_excesiva', ['dias' => self::DURACION_MAXIMA_DIAS]));
            }
        }];
    }

    /**
     * Interpreta un texto de fecha; si no trae zona, se toma la indicada. Devuelve null si no es una fecha válida.
     */
    public static function instante(mixed $valor, string $zona): ?CarbonImmutable
    {
        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($valor, $zona);
        } catch (Throwable) {
            return null;
        }
    }
}
