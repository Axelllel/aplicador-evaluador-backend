<?php

/**
 * Archivo: ExamenesPanelService.php
 * Módulo: app/Services
 * Descripción: Convierte los exámenes de la API en las filas de la tabla del panel (nombre, grupo, estado,
 *              fecha y acciones) (Manual COD-WEB, secciones 6.17 y 6.18).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Prepara los exámenes reales para la tabla del panel.
 */
class ExamenesPanelService
{
    /** Formato de las fechas mostradas al docente. */
    public const FORMATO_DE_FECHA = 'd/m/Y H:i';

    /**
     * Calcula el estado de un examen: borrador, publicado (dentro o antes de su ventana) o cerrado.
     *
     * @param  array<string, mixed>  $examen
     */
    public function estado(array $examen, ?CarbonImmutable $ahora = null): string
    {
        $ahora ??= CarbonImmutable::now();

        if (! ($examen['publicado'] ?? false)) {
            return 'borrador';
        }

        return CarbonImmutable::parse($examen['fecha_cierre'])->lessThanOrEqualTo($ahora) ? 'cerrado' : 'publicado';
    }

    /**
     * Arma las filas de la tabla; el grupo se muestra por su materia.
     *
     * @param  Collection<int, array<string, mixed>>  $examenes
     * @param  Collection<int, array<string, mixed>>  $grupos
     * @return Collection<int, array<string, mixed>>
     */
    public function filas(Collection $examenes, Collection $grupos): Collection
    {
        $materias = $grupos->pluck('materia', 'id');

        return $examenes->map(function (array $examen) use ($materias): array {
            $estado = $this->estado($examen);

            return [
                'id' => (int) $examen['id'],
                'nombre' => (string) $examen['nombre'],
                'grupo' => (string) ($materias[$examen['grupo_id']] ?? '—'),
                'estado' => $estado,
                'fecha' => CarbonImmutable::parse($examen['fecha_apertura'])->format(self::FORMATO_DE_FECHA),
                'acciones' => $estado === 'borrador'
                    ? ['abrir', 'publicar', 'eliminar']
                    : ['abrir', 'resultados', 'estadisticas'],
            ];
        })->values();
    }
}
