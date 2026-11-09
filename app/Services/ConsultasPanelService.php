<?php

/**
 * Archivo: ConsultasPanelService.php
 * Módulo: app/Services
 * Descripción: Lecturas del panel a la API del docente (grupos, preguntas, exámenes, apartados, planes, pagos,
 *              resultados, intentos, respuestas e incidencias). Si la API falla, lanza
 *              ServicioNoDisponibleException para mostrar la página de error amable
 *              (Manual COD-WEB, secciones 8.3 y 6.18).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Lecturas de resultados, intentos, respuestas e incidencias
 */

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ServicioNoDisponibleException;
use Illuminate\Support\Collection;

/**
 * Consultas de solo lectura a la API del docente.
 */
class ConsultasPanelService
{
    public function __construct(private readonly PanelApiService $api)
    {
    }

    /**
     * Grupos del docente: id, materia, folio_codigo y activo.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function grupos(): Collection
    {
        return $this->lista('/teacher/groups');
    }

    /**
     * Preguntas del banco, de la más reciente a la más antigua; se puede filtrar por tipo.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function preguntas(?string $tipo = null): Collection
    {
        return $this->lista('/teacher/questions', $tipo !== null ? ['tipo' => $tipo] : []);
    }

    /**
     * Exámenes del docente, del más reciente al más antiguo.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function examenes(): Collection
    {
        return $this->lista('/teacher/exams');
    }

    /**
     * Paquetes disponibles (planes).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function planes(): Collection
    {
        return $this->lista('/teacher/plans');
    }

    /**
     * Pagos del docente, del más reciente al más antiguo.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function pagos(): Collection
    {
        return $this->lista('/teacher/payments');
    }

    /**
     * Apartados de un examen con sus preguntas; null si el examen no existe o no es del docente.
     *
     * @return Collection<int, array<string, mixed>>|null
     */
    public function apartados(int $examenId): ?Collection
    {
        $respuesta = $this->api->get('/teacher/exams/'.$examenId.'/sections');

        if ($respuesta->esNoEncontrada()) {
            return null;
        }

        if (! $respuesta->exitosa()) {
            throw new ServicioNoDisponibleException('No se pudieron leer los apartados.');
        }

        return collect($respuesta->datos())->values();
    }

    /**
     * Cantidad de alumnos inscritos en un grupo.
     */
    public function alumnosDelGrupo(int $grupoId): int
    {
        return $this->estudiantesDelGrupo($grupoId)->count();
    }

    /** Alumnos inscritos e intentos registrados en el grupo. */
    public function estudiantesDelGrupo(int $grupoId): Collection
    {
        return $this->lista('/teacher/groups/'.$grupoId.'/students');
    }

    /**
     * Resultados de un examen: sus datos y, por intento, el alumno, el estado y la nota (solo cuando todas las
     * preguntas están calificadas); null si el examen no existe o no es del docente.
     *
     * @return array{examen: array<string, mixed>, resultados: Collection<int, array<string, mixed>>}|null
     */
    public function resultadosDelExamen(int $examenId): ?array
    {
        $datos = $this->registro('/teacher/exams/'.$examenId.'/results');

        if ($datos === null) {
            return null;
        }

        return [
            'examen' => (array) ($datos['examen'] ?? []),
            'resultados' => collect($datos['resultados'] ?? [])->values(),
        ];
    }

    /**
     * Intentos de un examen con la fecha de inicio y de entrega; vacía si el examen no existe.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function intentosDelExamen(int $examenId): Collection
    {
        $respuesta = $this->api->get('/teacher/exams/'.$examenId.'/attempts');

        if ($respuesta->esNoEncontrada()) {
            return collect();
        }

        if (! $respuesta->exitosa()) {
            throw new ServicioNoDisponibleException('No se pudieron leer los intentos.');
        }

        return collect($respuesta->datos())->values();
    }

    /**
     * Respuestas de un intento con la ponderación, la calificación y la respuesta modelo; null si el intento
     * no existe o no es del docente.
     *
     * @return array{intento: array<string, mixed>, respuestas: Collection<int, array<string, mixed>>}|null
     */
    public function respuestasDelIntento(int $intentoId): ?array
    {
        $datos = $this->registro('/teacher/attempts/'.$intentoId.'/answers');

        if ($datos === null) {
            return null;
        }

        return [
            'intento' => (array) ($datos['intento'] ?? []),
            'respuestas' => collect($datos['respuestas'] ?? [])->values(),
        ];
    }

    /**
     * Incidencias registradas durante un intento (tipo, severidad y fecha).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function incidenciasDelIntento(int $intentoId): Collection
    {
        $respuesta = $this->api->get('/teacher/attempts/'.$intentoId.'/incidents');

        if ($respuesta->esNoEncontrada()) {
            return collect();
        }

        if (! $respuesta->exitosa()) {
            throw new ServicioNoDisponibleException('No se pudieron leer las incidencias.');
        }

        return collect($respuesta->datos())->values();
    }

    /**
     * Lee un registro de «data»; null si no existe (404) y excepción si la API falla.
     *
     * @return array<string, mixed>|null
     */
    private function registro(string $ruta): ?array
    {
        $respuesta = $this->api->get($ruta);

        if ($respuesta->esNoEncontrada()) {
            return null;
        }

        if (! $respuesta->exitosa()) {
            throw new ServicioNoDisponibleException('Falló la lectura de '.$ruta.'.');
        }

        return $respuesta->datos();
    }

    /**
     * @param  array<string, mixed>  $consulta
     * @return Collection<int, array<string, mixed>>
     */
    private function lista(string $ruta, array $consulta = []): Collection
    {
        $respuesta = $this->api->get($ruta, $consulta);

        if (! $respuesta->exitosa()) {
            throw new ServicioNoDisponibleException('Falló la lectura de '.$ruta.'.');
        }

        return collect($respuesta->datos())->values();
    }
}
