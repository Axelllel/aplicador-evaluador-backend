<?php

/**
 * Archivo: PanelController.php
 * Módulo: app/Http/Controllers/Web
 * Descripción: Muestra el panel inicial del docente con datos reales de la API: consumo del plan
 *              (grupos, preguntas y alumnos contra los límites del paquete) y tabla de exámenes
 *              (Manual COD-WEB, secciones 6.3 y 6.16).
 * Autor: Leonardo Ballestero López
 * Creado: 27/10/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Datos reales de la API; se quita el rol por URL
 */

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\ConsultasPanelService;
use App\Services\ExamenesPanelService;
use App\Services\PaginadorPanel;
use App\Services\PlanDocenteService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controla el panel inicial del docente.
 */
class PanelController extends Controller
{
    public function __construct(
        private readonly ConsultasPanelService $consultas,
        private readonly PlanDocenteService $planes,
        private readonly ExamenesPanelService $examenes,
        private readonly PaginadorPanel $paginador,
    ) {
    }

    /**
     * Muestra el panel del docente autenticado.
     */
    public function index(Request $solicitud): View
    {
        $grupos = $this->consultas->grupos();
        $preguntas = $this->consultas->preguntas();
        $examenes = $this->consultas->examenes();
        $vigente = $this->planes->planVigente($this->consultas->planes(), $this->consultas->pagos());

        $resumen = null;

        if ($vigente !== null) {
            $gruposActivos = $grupos->where('activo', true);
            $alumnosDelGrupoMayor = $gruposActivos->max(fn (array $grupo): int => $this->consultas->alumnosDelGrupo((int) $grupo['id']));

            $resumen = $this->planes->resumen($vigente['plan'], $gruposActivos->count(), $preguntas->count(), (int) $alumnosDelGrupoMayor);
        }

        return view('teacher.dashboard', [
            'plan' => $resumen,
            'examenes' => $this->paginador->paginar(
                $this->examenes->filas($examenes, $grupos),
                $solicitud->integer('page', 1),
            ),
        ]);
    }
}
