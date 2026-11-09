<?php

/**
 * Archivo: EstadisticasPanelService.php
 * Módulo: app/Services
 * Descripción: Calcula en el panel las estadísticas de los exámenes y de los grupos (la API no tiene endpoint de
 *              analíticas) a partir de los resultados y de las respuestas: número de intentos, promedio, mínimo,
 *              máximo, tasa de aprobación (nota de 60 % de la escala o más), distribución de notas en cinco rangos
 *              y preguntas con mayor error (Manual COD-WEB, sección 8.3).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Estadísticas de resultados calculadas en el panel. Solo cuentan los intentos con nota definitiva (todas sus
 * preguntas calificadas); los pendientes y los no entregados se informan aparte.
 */
class EstadisticasPanelService
{
    /** Porcentaje de la escala a partir del cual un intento se considera aprobado. */
    public const PORCENTAJE_APROBATORIO = 60.0;

    /** Cantidad de rangos de la distribución de notas; cada uno cubre el 20 % de la escala. */
    public const RANGOS = 5;

    /** Cantidad máxima de preguntas que se listan en «Preguntas con mayor error». */
    public const PREGUNTAS_CON_ERROR = 5;

    /**
     * Convierte una nota a porcentaje de su escala («0-10» o «0-100»).
     */
    public function aPorcentaje(float $nota, string $escala): float
    {
        return round($escala === '0-10' ? $nota * 10 : $nota, 2);
    }

    /**
     * Resume una lista de porcentajes de la escala: calificados, promedio, mínimo, máximo, aprobados, tasa de
     * aprobación y distribución. Sin datos devuelve ceros y valores nulos.
     *
     * @param  Collection<int, float>  $porcentajes
     * @return array{calificados: int, promedio: float|null, minimo: float|null, maximo: float|null,
     *     aprobados: int, tasa_aprobacion: float|null, distribucion: list<int>}
     */
    public function resumen(Collection $porcentajes): array
    {
        $porcentajes = $porcentajes->map(fn (mixed $valor): float => (float) $valor)->values();

        if ($porcentajes->isEmpty()) {
            return [
                'calificados' => 0,
                'promedio' => null,
                'minimo' => null,
                'maximo' => null,
                'aprobados' => 0,
                'tasa_aprobacion' => null,
                'distribucion' => array_fill(0, self::RANGOS, 0),
            ];
        }

        $aprobados = $porcentajes->filter(fn (float $valor): bool => $valor >= self::PORCENTAJE_APROBATORIO)->count();

        return [
            'calificados' => $porcentajes->count(),
            'promedio' => round($porcentajes->avg(), 2),
            'minimo' => round($porcentajes->min(), 2),
            'maximo' => round($porcentajes->max(), 2),
            'aprobados' => $aprobados,
            'tasa_aprobacion' => round($aprobados / $porcentajes->count() * 100, 2),
            'distribucion' => $this->distribucion($porcentajes),
        ];
    }

    /**
     * Cuenta cuántos porcentajes caen en cada rango: 0 a 20, 20 a 40, 40 a 60, 60 a 80 y 80 a 100. Cada rango
     * incluye su límite inferior y el último incluye también el 100 %.
     *
     * @param  Collection<int, float>  $porcentajes
     * @return list<int>
     */
    public function distribucion(Collection $porcentajes): array
    {
        $cuentas = array_fill(0, self::RANGOS, 0);

        foreach ($porcentajes as $porcentaje) {
            $rango = (int) floor(min(100.0, max(0.0, (float) $porcentaje)) / (100 / self::RANGOS));
            $cuentas[min(self::RANGOS - 1, $rango)]++;
        }

        return $cuentas;
    }

    /**
     * Etiquetas de los rangos en la unidad indicada: «0 a 2» para la escala 0-10, «0 a 20» para 0-100 y para
     * porcentajes.
     *
     * @return list<string>
     */
    public function etiquetasDeRangos(string $escala): array
    {
        $paso = $escala === '0-10' ? 2 : 20;

        return array_map(
            fn (int $rango): string => ($rango * $paso).'–'.(($rango + 1) * $paso),
            range(0, self::RANGOS - 1),
        );
    }

    /**
     * Calcula las estadísticas de cada examen.
     *
     * @param  Collection<int, array{examen: array<string, mixed>, resultados: Collection<int, array<string, mixed>>}>  $datos
     * @return Collection<int, array<string, mixed>>
     */
    public function porExamen(Collection $datos): Collection
    {
        return $datos->map(function (array $dato): array {
            $examen = $dato['examen'];
            $escala = (string) ($examen['escala'] ?? '0-10');
            $resultados = $dato['resultados'];

            $notas = $resultados
                ->filter(fn (array $resultado): bool => ($resultado['completo'] ?? false) && ($resultado['calificacion'] ?? null) !== null)
                ->map(fn (array $resultado): float => (float) $resultado['calificacion'])
                ->values();

            $porcentajes = $notas->map(fn (float $nota): float => $this->aPorcentaje($nota, $escala));
            $resumen = $this->resumen($porcentajes);
            $factor = $escala === '0-10' ? 0.1 : 1.0;

            return [
                'id' => (int) $examen['id'],
                'nombre' => (string) $examen['nombre'],
                'grupo_id' => isset($examen['grupo_id']) ? (int) $examen['grupo_id'] : null,
                'escala' => $escala,
                'intentos' => $resultados->count(),
                'entregados' => $resultados->where('estado', 'finalizado')->count(),
                'pendientes' => $resultados->where('estado', 'finalizado')->reject(
                    fn (array $resultado): bool => ($resultado['completo'] ?? false) && ($resultado['calificacion'] ?? null) !== null,
                )->count(),
                'calificados' => $resumen['calificados'],
                // Promedio, mínimo y máximo en la escala del propio examen.
                'promedio' => $resumen['promedio'] !== null ? round($resumen['promedio'] * $factor, 2) : null,
                'minimo' => $resumen['minimo'] !== null ? round($resumen['minimo'] * $factor, 2) : null,
                'maximo' => $resumen['maximo'] !== null ? round($resumen['maximo'] * $factor, 2) : null,
                'promedio_porcentaje' => $resumen['promedio'],
                'aprobados' => $resumen['aprobados'],
                'tasa_aprobacion' => $resumen['tasa_aprobacion'],
                'distribucion' => $resumen['distribucion'],
                'porcentajes' => $porcentajes->all(),
            ];
        })->values();
    }

    /**
     * Calcula las estadísticas de cada grupo juntando los intentos de todos sus exámenes, normalizados como
     * porcentaje de la escala de cada examen (los exámenes pueden tener escalas distintas).
     *
     * @param  Collection<int, array<string, mixed>>  $examenes  Resultado de {@see self::porExamen()}.
     * @param  Collection<int, array<string, mixed>>  $grupos  Grupos de la API (id y materia).
     * @return Collection<int, array<string, mixed>>
     */
    public function porGrupo(Collection $examenes, Collection $grupos): Collection
    {
        return $grupos->map(function (array $grupo) use ($examenes): ?array {
            $delGrupo = $examenes->where('grupo_id', (int) $grupo['id']);

            if ($delGrupo->isEmpty()) {
                return null;
            }

            $resumen = $this->resumen($delGrupo->flatMap(fn (array $examen): array => $examen['porcentajes']));

            return [
                'id' => (int) $grupo['id'],
                'nombre' => (string) $grupo['materia'],
                'examenes' => $delGrupo->count(),
                'intentos' => $delGrupo->sum('intentos'),
                'pendientes' => $delGrupo->sum('pendientes'),
                'calificados' => $resumen['calificados'],
                'promedio_porcentaje' => $resumen['promedio'],
                'minimo_porcentaje' => $resumen['minimo'],
                'maximo_porcentaje' => $resumen['maximo'],
                'aprobados' => $resumen['aprobados'],
                'tasa_aprobacion' => $resumen['tasa_aprobacion'],
                'distribucion' => $resumen['distribucion'],
            ];
        })->filter()->values();
    }

    /**
     * Totales de todos los exámenes juntos, como porcentaje de la escala de cada uno.
     *
     * @param  Collection<int, array<string, mixed>>  $examenes  Resultado de {@see self::porExamen()}.
     * @return array<string, mixed>
     */
    public function general(Collection $examenes): array
    {
        return $this->resumen($examenes->flatMap(fn (array $examen): array => $examen['porcentajes'])) + [
            'examenes' => $examenes->count(),
            'intentos' => $examenes->sum('intentos'),
            'pendientes' => $examenes->sum('pendientes'),
        ];
    }

    /**
     * Datos de la gráfica «Promedio y aprobación por examen»: los exámenes con intentos calificados más recientes,
     * de izquierda a derecha del más antiguo al más reciente.
     *
     * @param  Collection<int, array<string, mixed>>  $examenes  Resultado de {@see self::porExamen()}, del más reciente al más antiguo.
     * @return array{categorias: list<string>, series: list<array<string, mixed>>}
     */
    public function graficaPorExamen(Collection $examenes, int $limite = 10): array
    {
        $filas = $examenes->where('calificados', '>', 0)->take($limite)->reverse()->values();

        return $this->graficaDePromedioYAprobacion($filas, 'nombre', 'promedio_porcentaje');
    }

    /**
     * Datos de la gráfica «Promedio y aprobación por grupo».
     *
     * @param  Collection<int, array<string, mixed>>  $grupos  Resultado de {@see self::porGrupo()}.
     * @return array{categorias: list<string>, series: list<array<string, mixed>>}
     */
    public function graficaPorGrupo(Collection $grupos, int $limite = 10): array
    {
        return $this->graficaDePromedioYAprobacion($grupos->where('calificados', '>', 0)->take($limite)->values(), 'nombre', 'promedio_porcentaje');
    }

    /**
     * Datos de la gráfica de distribución de notas de un examen: alumnos por rango, en la escala del examen.
     *
     * @param  array<string, mixed>  $examen  Fila de {@see self::porExamen()}.
     * @return array{categorias: list<string>, series: list<array<string, mixed>>}
     */
    public function graficaDeDistribucion(array $examen): array
    {
        return [
            'categorias' => $this->etiquetasDeRangos((string) $examen['escala']),
            'series' => [[
                'nombre' => __('estadisticas.series.alumnos'),
                'valores' => $examen['calificados'] > 0 ? $examen['distribucion'] : [null, null, null, null, null],
                'tono' => 'principal',
            ]],
        ];
    }

    /**
     * Datos de la gráfica de preguntas con mayor error.
     *
     * @param  Collection<int, array<string, mixed>>  $preguntas  Resultado de {@see self::preguntasConMasError()}.
     * @return array{categorias: list<string>, series: list<array<string, mixed>>}
     */
    public function graficaDeErrores(Collection $preguntas): array
    {
        return [
            'categorias' => $preguntas->pluck('etiqueta')->all(),
            'series' => [[
                'nombre' => __('estadisticas.series.error'),
                'valores' => $preguntas->pluck('error')->all(),
                'tono' => 'principal',
            ]],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return array{categorias: list<string>, series: list<array<string, mixed>>}
     */
    private function graficaDePromedioYAprobacion(Collection $filas, string $claveDeNombre, string $clavePromedio): array
    {
        return [
            'categorias' => $filas->pluck($claveDeNombre)->all(),
            'series' => [
                [
                    'nombre' => __('estadisticas.series.promedio'),
                    'valores' => $filas->pluck($clavePromedio)->all(),
                    'tono' => 'principal',
                ],
                [
                    'nombre' => __('estadisticas.series.aprobacion'),
                    'valores' => $filas->pluck('tasa_aprobacion')->all(),
                    'tono' => 'secundaria',
                ],
            ],
        ];
    }

    /**
     * Ordena las preguntas de un examen por su error promedio (100 menos el promedio de calificación de las
     * respuestas ya calificadas), de mayor a menor. Si hay empate gana la que tiene más respuestas.
     *
     * @param  Collection<int, array<string, mixed>>  $respuestas  Respuestas de todos los intentos del examen.
     * @return Collection<int, array<string, mixed>>
     */
    public function preguntasConMasError(Collection $respuestas, int $limite = self::PREGUNTAS_CON_ERROR): Collection
    {
        return $respuestas
            ->filter(fn (array $respuesta): bool => ($respuesta['calificacion_final'] ?? null) !== null)
            ->groupBy('examen_pregunta_id')
            ->map(function (Collection $grupo, int|string $preguntaId): array {
                $calificaciones = $grupo->map(fn (array $respuesta): float => (float) $respuesta['calificacion_final']);
                $primera = $grupo->first();

                return [
                    'pregunta_id' => (int) $preguntaId,
                    'enunciado' => (string) $primera['enunciado'],
                    'tipo' => (string) $primera['tipo'],
                    'respuestas' => $calificaciones->count(),
                    'promedio' => round($calificaciones->avg(), 2),
                    'error' => round(100 - $calificaciones->avg(), 2),
                    'reprobadas' => $calificaciones->filter(fn (float $valor): bool => $valor < self::PORCENTAJE_APROBATORIO)->count(),
                ];
            })
            ->sort(fn (array $a, array $b): int => [$b['error'], $b['respuestas'], $a['pregunta_id']] <=> [$a['error'], $a['respuestas'], $b['pregunta_id']])
            ->take($limite)
            ->values()
            ->map(fn (array $pregunta, int $posicion): array => $pregunta + ['etiqueta' => 'P'.($posicion + 1)]);
    }
}
