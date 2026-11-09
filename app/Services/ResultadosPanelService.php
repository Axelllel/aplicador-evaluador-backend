<?php

/**
 * Archivo: ResultadosPanelService.php
 * Módulo: app/Services
 * Descripción: Convierte los resultados, intentos, respuestas e incidencias de la API en las filas de la tabla de
 *              resultados y en el detalle de un intento: estado de la nota («Pendiente de calificar» mientras haya
 *              preguntas sin calificar), nota en la escala del examen y origen de cada calificación
 *              (Manual COD-WEB, secciones 6.17, 6.18 y 8.3).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Prepara los resultados reales para las páginas de Resultados.
 */
class ResultadosPanelService
{
    /** Calificada: todas las preguntas tienen nota. Pendiente: finalizado con preguntas sin nota. */
    public const NOTA_CALIFICADA = 'calificada';

    public const NOTA_PENDIENTE = 'pendiente';

    public const NOTA_SIN_ENTREGAR = 'sin_entregar';

    public const NOTA_ANULADO = 'anulado';

    public function __construct(private readonly ConsultasPanelService $consultas)
    {
    }

    /**
     * Reúne todo lo que muestra la página de un intento: el detalle (nota, respuestas, permisos) y sus incidencias.
     * Devuelve null si el examen o el intento no existen, no son del docente o el intento es de otro examen.
     *
     * @return array<string, mixed>|null
     */
    public function intento(int $examenId, int $intentoId): ?array
    {
        $respuestas = $this->consultas->respuestasDelIntento($intentoId);

        if ($respuestas === null || (int) ($respuestas['intento']['examen_id'] ?? 0) !== $examenId) {
            return null;
        }

        $resultados = $this->consultas->resultadosDelExamen($examenId);

        if ($resultados === null) {
            return null;
        }

        $resultado = $resultados['resultados']->firstWhere('intento_id', $intentoId) ?? null;

        if ($resultado === null) {
            return null;
        }

        $intento = $this->consultas->intentosDelExamen($examenId)->firstWhere('id', $intentoId) ?? [];

        return $this->detalle($resultados['examen'], $resultado, $intento, $respuestas['respuestas']) + [
            'examen' => (string) ($resultados['examen']['nombre'] ?? ''),
            'incidencias' => $this->consultas->incidenciasDelIntento($intentoId)->map(fn (array $incidencia): array => [
                'tipo' => (string) $incidencia['tipo_alerta'],
                'severidad' => (string) $incidencia['nivel_severidad'],
                'fecha' => $this->fecha($incidencia['fecha_hora'] ?? null),
            ])->values(),
        ];
    }

    /**
     * Devuelve una respuesta tal como la entrega la API, solo si pertenece al intento y este al examen indicados.
     *
     * @return array<string, mixed>|null
     */
    public function respuestaDelIntento(int $examenId, int $intentoId, int $respuestaId): ?array
    {
        $respuestas = $this->consultas->respuestasDelIntento($intentoId);

        if ($respuestas === null || (int) ($respuestas['intento']['examen_id'] ?? 0) !== $examenId) {
            return null;
        }

        return $respuestas['respuestas']->firstWhere('id', $respuestaId);
    }

    /**
     * Arma las filas de la tabla de intentos de un examen uniendo los resultados (nota) con los intentos
     * (fechas de inicio y de entrega).
     *
     * @param  Collection<int, array<string, mixed>>  $resultados
     * @param  Collection<int, array<string, mixed>>  $intentos
     * @return Collection<int, array<string, mixed>>
     */
    public function filas(Collection $resultados, Collection $intentos): Collection
    {
        $intentosPorId = $intentos->keyBy('id');

        return $resultados->map(function (array $resultado) use ($intentosPorId): array {
            $intento = $intentosPorId->get($resultado['intento_id'], []);
            $estadoDeNota = $this->estadoDeNota($resultado);

            return [
                'intento_id' => (int) $resultado['intento_id'],
                'alumno' => (string) $resultado['nombre'],
                'estado' => (string) $resultado['estado'],
                'entrega' => $this->fecha($intento['fecha_entrega'] ?? null),
                'estado_de_nota' => $estadoDeNota,
                'nota' => $estadoDeNota === self::NOTA_CALIFICADA
                    ? $this->numero((float) $resultado['calificacion'])
                    : null,
                'escala' => (string) $resultado['escala'],
                'calificadas' => (int) $resultado['calificadas'],
                'total_preguntas' => (int) $resultado['total_preguntas'],
            ];
        })->values();
    }

    /**
     * Cuenta los intentos del examen según el estado de su nota.
     *
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return array{intentos: int, calificados: int, pendientes: int}
     */
    public function resumen(Collection $filas): array
    {
        return [
            'intentos' => $filas->count(),
            'calificados' => $filas->where('estado_de_nota', self::NOTA_CALIFICADA)->count(),
            'pendientes' => $filas->where('estado_de_nota', self::NOTA_PENDIENTE)->count(),
        ];
    }

    /**
     * Determina si la nota de un intento es definitiva, está pendiente o aún no existe.
     *
     * @param  array<string, mixed>  $resultado
     */
    public function estadoDeNota(array $resultado): string
    {
        return match ($resultado['estado']) {
            'anulado' => self::NOTA_ANULADO,
            'finalizado' => ($resultado['completo'] ?? false) && ($resultado['calificacion'] ?? null) !== null
                ? self::NOTA_CALIFICADA
                : self::NOTA_PENDIENTE,
            default => self::NOTA_SIN_ENTREGAR,
        };
    }

    /**
     * Arma los datos de la página de un intento: resumen de la nota, respuestas con su calificación e indicadores
     * de lo que el docente puede hacer.
     *
     * @param  array<string, mixed>  $examen
     * @param  array<string, mixed>  $resultado  Fila del intento en los resultados del examen.
     * @param  array<string, mixed>  $intento  Fila del intento en la lista de intentos (fechas).
     * @param  Collection<int, array<string, mixed>>  $respuestas
     * @return array<string, mixed>
     */
    public function detalle(array $examen, array $resultado, array $intento, Collection $respuestas): array
    {
        $escala = (string) ($examen['escala'] ?? $resultado['escala'] ?? '0-10');
        $factor = $escala === '0-10' ? 0.1 : 1.0;
        $estadoDeNota = $this->estadoDeNota($resultado);
        $finalizado = $resultado['estado'] === 'finalizado';

        $lista = $respuestas->values()->map(function (array $respuesta, int $posicion) use ($factor): array {
            $calificacion = $respuesta['calificacion_final'] !== null ? (float) $respuesta['calificacion_final'] : null;
            $texto = $respuesta['respuesta_texto'] ?? null;
            $modelo = $respuesta['respuesta_modelo_snapshot'] ?? null;

            return [
                'id' => (int) $respuesta['id'],
                'numero' => $posicion + 1,
                'tipo' => (string) $respuesta['tipo'],
                'enunciado' => (string) $respuesta['enunciado'],
                'respuesta' => is_string($texto) && trim($texto) !== '' ? $texto : null,
                'modelo' => is_string($modelo) && trim($modelo) !== '' ? $modelo : null,
                'ponderacion' => $this->numero((float) $respuesta['ponderacion']),
                'calificacion' => $calificacion,
                'calificacion_texto' => $calificacion !== null ? $this->numero($calificacion) : null,
                'origen' => match (true) {
                    $calificacion === null => 'sin_calificar',
                    (bool) ($respuesta['revisada_manual'] ?? false) => 'manual',
                    default => 'automatica',
                },
                'aporte' => $calificacion !== null
                    ? $this->numero($calificacion * (float) $respuesta['ponderacion'] / 100 * $factor)
                    : null,
            ];
        });

        $total = (int) ($resultado['total_preguntas'] ?? $lista->count());

        return [
            'alumno' => (string) $resultado['nombre'],
            'estado' => (string) $resultado['estado'],
            'numero_de_intento' => (int) ($intento['numero_intento'] ?? 1),
            'inicio' => $this->fecha($intento['fecha_inicio'] ?? null),
            'entrega' => $this->fecha($intento['fecha_entrega'] ?? null),
            'escala' => $escala,
            'estado_de_nota' => $estadoDeNota,
            'nota' => $estadoDeNota === self::NOTA_CALIFICADA ? $this->numero((float) $resultado['calificacion']) : null,
            'calificadas' => (int) ($resultado['calificadas'] ?? 0),
            'total_preguntas' => $total,
            'respuestas' => $lista,
            'sin_responder' => max(0, $total - $lista->count()),
            'puede_calificar' => $finalizado,
            'puede_autocalificar' => $finalizado && $lista->contains(
                fn (array $fila): bool => $fila['tipo'] === 'opcion_multiple' && $fila['calificacion'] === null,
            ),
        ];
    }

    /**
     * Da formato a una cifra: hasta dos decimales y sin ceros sobrantes.
     */
    public function numero(float $valor): string
    {
        $texto = rtrim(rtrim(number_format($valor, 2, '.', ''), '0'), '.');

        return $texto === '' || $texto === '-0' ? '0' : $texto;
    }

    /**
     * Da formato a una fecha de la API; null si no hay.
     */
    public function fecha(mixed $fecha): ?string
    {
        return is_string($fecha) && $fecha !== ''
            ? CarbonImmutable::parse($fecha)->format(ExamenesPanelService::FORMATO_DE_FECHA)
            : null;
    }
}
