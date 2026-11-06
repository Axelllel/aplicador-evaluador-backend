<?php

/**
 * Archivo: PlanDocenteService.php
 * Módulo: app/Services
 * Descripción: Determina el plan vigente del docente a partir de sus pagos confirmados y arma las tarjetas
 *              de consumo (grupos, preguntas y alumnos contra los límites del paquete)
 *              (Manual COD-WEB, secciones 6.16 y 8.4).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Calcula el plan vigente del docente y su consumo.
 */
class PlanDocenteService
{
    /**
     * Devuelve el plan vigente y su pago confirmado, o null si el docente no tiene plan activo.
     *
     * La API no expone docente.estado_pago ni docente.paquete_id, así que el plan se deduce del pago
     * confirmado cuya vigencia incluye el momento actual (la misma regla que aplica el backend al crear).
     *
     * @param  Collection<int, array<string, mixed>>  $planes
     * @param  Collection<int, array<string, mixed>>  $pagos
     * @return array{plan: array<string, mixed>, pago: array<string, mixed>}|null
     */
    public function planVigente(Collection $planes, Collection $pagos, ?CarbonImmutable $ahora = null): ?array
    {
        $ahora ??= CarbonImmutable::now();

        $pago = $pagos
            ->filter(fn (array $pago): bool => ($pago['estado'] ?? null) === 'confirmado'
                && CarbonImmutable::parse($pago['fecha_inicio_vigencia'])->lessThanOrEqualTo($ahora)
                && CarbonImmutable::parse($pago['fecha_fin_vigencia'])->greaterThan($ahora))
            ->sortByDesc(fn (array $pago): int => CarbonImmutable::parse($pago['fecha_fin_vigencia'])->getTimestamp())
            ->first();

        if ($pago === null) {
            return null;
        }

        $plan = $planes->first(fn (array $plan): bool => (int) $plan['id'] === (int) $pago['paquete_id']);

        return $plan === null ? null : ['plan' => $plan, 'pago' => $pago];
    }

    /**
     * Arma el resumen que consume la vista del panel: nombre del plan, recursos y avisos de límite.
     *
     * @param  array<string, mixed>  $plan
     * @return array{nombre: string, recursos: list<array<string, mixed>>, avisos: list<string>}
     */
    public function resumen(array $plan, int $grupos, int $preguntas, int $alumnos): array
    {
        $recursos = [
            $this->describirRecurso('grupos', $grupos, (int) $plan['max_grupos']),
            $this->describirRecurso('preguntas', $preguntas, (int) $plan['max_preguntas']),
            $this->describirRecurso('alumnos', $alumnos, (int) $plan['max_alumnos']),
        ];

        $alcanzados = array_filter($recursos, fn (array $recurso): bool => $recurso['estado'] === 'alcanzado');

        return [
            'nombre' => (string) $plan['nombre'],
            'recursos' => $recursos,
            'avisos' => array_values(array_map(fn (array $recurso): string => $recurso['clave'], $alcanzados)),
        ];
    }

    /**
     * Describe el uso de un recurso: porcentaje y estado (normal, cercano o alcanzado).
     *
     * @return array<string, mixed>
     */
    public function describirRecurso(string $clave, int $usado, int $limite): array
    {
        $porcentaje = $limite > 0 ? (int) floor($usado / $limite * 100) : 100;

        $estado = match (true) {
            $usado >= $limite => 'alcanzado',
            $porcentaje >= 80 => 'cercano',
            default => 'normal',
        };

        return [
            'clave' => $clave,
            'usado' => $usado,
            'limite' => $limite,
            'porcentaje' => min($porcentaje, 100),
            'estado' => $estado,
        ];
    }
}
