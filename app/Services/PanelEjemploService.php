<?php

/**
 * Archivo: PanelEjemploService.php
 * Módulo: app/Services
 * Descripción: Entrega datos de ejemplo para la maqueta del panel (plan, exámenes, docentes)
 *              hasta que existan los modelos Eloquent (Manual COD-WEB, sección 6.16).
 * Autor: Leonardo Ballestero López
 * Creado: 27/10/2026
 * Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

// TODO(Leonardo Ballestero López): reemplazar este servicio por consultas a los modelos
// Docente, Grupo, Examen y Suscripción cuando existan (Manual COD-WEB, sección 6.4).
/**
 * Entrega datos de ejemplo para la maqueta del panel.
 */
class PanelEjemploService
{
    public const FILAS_POR_PAGINA = 15;

    private const TOTAL_EXAMENES = 38;

    private const TOTAL_DOCENTES = 22;

    /** Estados de examen que se reparten en las filas de ejemplo. */
    private const ESTADOS = ['cerrado', 'publicado', 'borrador', 'cerrado'];

    /**
     * Devuelve el uso del plan del docente: recursos usados contra su límite.
     *
     * @return array{nombre: string, recursos: list<array<string, mixed>>, avisos: list<string>}
     */
    public function resumenDelPlan(): array
    {
        $recursos = [
            $this->describirRecurso('grupos', 5, 5),
            $this->describirRecurso('preguntas', 186, 200),
            $this->describirRecurso('alumnos', 85, 150),
        ];

        $alcanzados = array_filter($recursos, fn (array $recurso): bool => $recurso['estado'] === 'alcanzado');

        return [
            'nombre' => __('ejemplo.plan.nombre'),
            'recursos' => $recursos,
            'avisos' => array_values(array_map(fn (array $recurso): string => $recurso['clave'], $alcanzados)),
        ];
    }

    /**
     * Devuelve los exámenes de ejemplo paginados de a 15 filas.
     */
    public function examenes(int $pagina, string $nombrePagina = 'page'): LengthAwarePaginator
    {
        $filas = collect(range(self::TOTAL_EXAMENES, 1))
            ->map(fn (int $numero): array => $this->crearExamen($numero));

        return $this->paginar($filas, $pagina, $nombrePagina);
    }

    /**
     * Devuelve las cifras de resumen del panel de superadministración.
     *
     * @return array{activos: int, suspendidos: int, pagos_pendientes: int}
     */
    public function resumenDeAdministracion(): array
    {
        $docentes = $this->todosLosDocentes();

        return [
            'activos' => $docentes->where('estado', 'activo')->count(),
            'suspendidos' => $docentes->where('estado', 'suspendido')->count(),
            'pagos_pendientes' => $docentes->where('pago_pendiente', true)->count(),
        ];
    }

    /**
     * Devuelve los docentes de ejemplo paginados de a 15 filas.
     */
    public function docentes(int $pagina): LengthAwarePaginator
    {
        return $this->paginar($this->todosLosDocentes(), $pagina, 'page');
    }

    /**
     * @return array<string, mixed>
     */
    private function describirRecurso(string $clave, int $usado, int $limite): array
    {
        $porcentaje = (int) floor($usado / $limite * 100);

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

    /**
     * @return array<string, mixed>
     */
    private function crearExamen(int $numero): array
    {
        $asignaturas = (array) __('ejemplo.asignaturas');
        $tipos = (array) __('ejemplo.tipos_examen');
        $grupos = (array) __('ejemplo.grupos');
        $estado = self::ESTADOS[$numero % count(self::ESTADOS)];

        return [
            'nombre' => __('ejemplo.nombre_examen', [
                'tipo' => $tipos[$numero % count($tipos)],
                'asignatura' => $asignaturas[($numero * 3) % count($asignaturas)],
            ]),
            'grupo' => $grupos[$numero % count($grupos)],
            'estado' => $estado,
            'fecha' => CarbonImmutable::create(2026, 10, 1)->addDays($numero)->format('d/m/Y'),
            'acciones' => $this->accionesDelExamen($estado),
        ];
    }

    /**
     * Devuelve las acciones que ofrece cada fila según el estado del examen.
     *
     * @return list<string>
     */
    private function accionesDelExamen(string $estado): array
    {
        return match ($estado) {
            'borrador' => ['publicar', 'abrir', 'editar', 'eliminar'],
            default => ['abrir', 'resultados', 'estadisticas', 'eliminar'],
        };
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function todosLosDocentes(): Collection
    {
        return collect(range(1, self::TOTAL_DOCENTES))
            ->map(fn (int $numero): array => $this->crearDocente($numero));
    }

    /**
     * @return array<string, mixed>
     */
    private function crearDocente(int $numero): array
    {
        $nombres = (array) __('ejemplo.nombres');
        $apellidos = (array) __('ejemplo.apellidos');
        $planes = array_values((array) __('ejemplo.planes'));

        $nombre = $nombres[($numero * 3) % count($nombres)].' '.$apellidos[($numero * 5 + 1) % count($apellidos)];

        return [
            'nombre' => $nombre,
            'correo' => Str::slug($nombre, '.').'@escuela.example',
            'plan' => $planes[$numero % count($planes)],
            'estado' => $numero % 7 === 0 ? 'suspendido' : 'activo',
            'pago_pendiente' => $numero % 6 === 0,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     */
    private function paginar(Collection $filas, int $pagina, string $nombrePagina): LengthAwarePaginator
    {
        $ultimaPagina = max(1, (int) ceil($filas->count() / self::FILAS_POR_PAGINA));
        $paginaActual = min(max(1, $pagina), $ultimaPagina);

        return new LengthAwarePaginator(
            $filas->forPage($paginaActual, self::FILAS_POR_PAGINA)->values(),
            $filas->count(),
            self::FILAS_POR_PAGINA,
            $paginaActual,
            ['path' => url()->current(), 'pageName' => $nombrePagina],
        );
    }
}
