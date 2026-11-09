<?php

/**
 * Archivo: EstadisticaController.php
 * Módulo: app/Http/Controllers/Web
 * Descripción: Estadísticas de resultados por examen y por grupo, calculadas en el panel a partir de los resultados
 *              y de las respuestas que entrega la API (Manual COD-WEB, secciones 6.16 y 8.3).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\ConsultasPanelService;
use App\Services\EstadisticasPanelService;
use App\Services\PaginadorPanel;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Controla la página de Estadísticas.
 */
class EstadisticaController extends Controller
{
    /** Tope de intentos cuyas respuestas se leen para hallar las preguntas con mayor error. */
    private const MAXIMO_DE_INTENTOS_A_LEER = 200;

    public function __construct(
        private readonly ConsultasPanelService $consultas,
        private readonly EstadisticasPanelService $estadisticas,
        private readonly PaginadorPanel $paginador,
    ) {
    }

    /**
     * Muestra las estadísticas de todos los exámenes publicados, de los grupos y el detalle de un examen
     * (distribución de notas y preguntas con mayor error); sin examen elegido usa el más reciente con notas.
     */
    public function index(Request $solicitud): View
    {
        $grupos = $this->consultas->grupos();
        $publicados = $this->consultas->examenes()->where('publicado', true)->values();

        $resultadosPorExamen = $publicados->mapWithKeys(
            fn (array $examen): array => [$examen['id'] => $this->consultas->resultadosDelExamen((int) $examen['id'])['resultados'] ?? collect()],
        );

        $porExamen = $this->estadisticas->porExamen($publicados->map(fn (array $examen): array => [
            'examen' => $examen,
            'resultados' => $resultadosPorExamen[$examen['id']],
        ]));
        $porGrupo = $this->estadisticas->porGrupo($porExamen, $grupos);

        $conNotas = $porExamen->where('calificados', '>', 0)->values();
        $elegido = $solicitud->filled('examen')
            ? $porExamen->firstWhere('id', $solicitud->integer('examen'))
            : $conNotas->first();

        abort_if($solicitud->filled('examen') && $elegido === null, 404);

        $preguntas = $elegido !== null ? $this->preguntasConMasError($resultadosPorExamen[$elegido['id']]) : collect();

        return view('estadisticas.index', [
            'general' => $this->estadisticas->general($porExamen),
            'hayDatos' => $conNotas->isNotEmpty(),
            'examenes' => $this->paginador->paginar($porExamen, $solicitud->integer('pagina_examenes', 1), 'pagina_examenes'),
            'grupos' => $this->paginador->paginar($porGrupo, $solicitud->integer('pagina_grupos', 1), 'pagina_grupos'),
            'nombresDeGrupos' => $grupos->pluck('materia', 'id')->all(),
            'graficaPorExamen' => $this->estadisticas->graficaPorExamen($porExamen),
            'graficaPorGrupo' => $this->estadisticas->graficaPorGrupo($porGrupo),
            'opciones' => $publicados->pluck('nombre', 'id')->all(),
            'elegido' => $elegido,
            'graficaDeDistribucion' => $elegido !== null ? $this->estadisticas->graficaDeDistribucion($elegido) : null,
            'preguntas' => $preguntas,
            'graficaDeErrores' => $this->estadisticas->graficaDeErrores($preguntas),
        ]);
    }

    /**
     * Lee las respuestas de los intentos entregados de un examen y devuelve sus preguntas con mayor error.
     *
     * @param  Collection<int, array<string, mixed>>  $resultados
     * @return Collection<int, array<string, mixed>>
     */
    private function preguntasConMasError(Collection $resultados): Collection
    {
        $respuestas = $resultados
            ->where('estado', 'finalizado')
            ->take(self::MAXIMO_DE_INTENTOS_A_LEER)
            ->flatMap(fn (array $resultado): Collection => $this->consultas->respuestasDelIntento((int) $resultado['intento_id'])['respuestas'] ?? collect());

        return $this->estadisticas->preguntasConMasError($respuestas);
    }
}
