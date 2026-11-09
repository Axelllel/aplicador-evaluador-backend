<?php

/**
 * Archivo: SuscripcionController.php
 * Módulo: app/Http/Controllers/Web
 * Descripción: Muestra la suscripción del docente en solo lectura: plan vigente, planes disponibles y
 *              pagos registrados (Manual COD-WEB, secciones 6.16 y 8.3).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\ConsultasPanelService;
use App\Services\PaginadorPanel;
use App\Services\PlanDocenteService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controla la página de suscripción.
 */
class SuscripcionController extends Controller
{
    public function __construct(
        private readonly ConsultasPanelService $consultas,
        private readonly PlanDocenteService $planes,
        private readonly PaginadorPanel $paginador,
    ) {
    }

    /**
     * Muestra el plan vigente y la tabla de pagos (15 por página).
     */
    public function index(Request $solicitud): View
    {
        $planes = $this->consultas->planes();
        $pagos = $this->consultas->pagos();
        $vigente = $this->planes->planVigente($planes, $pagos);

        return view('suscripcion.index', [
            'vigente' => $vigente,
            'nombresDePlanes' => $planes->pluck('nombre', 'id'),
            'pagos' => $this->paginador->paginar($pagos, $solicitud->integer('page', 1)),
        ]);
    }

    /**
     * Muestra los planes disponibles ordenados por precio y marca el plan vigente.
     */
    public function catalogo(): View
    {
        $planes = $this->consultas->planes()->sortBy('precio')->values();
        $vigente = $this->planes->planVigente($planes, $this->consultas->pagos());

        return view('suscripcion.planes', [
            'planes' => $planes,
            'idDelPlanVigente' => $vigente !== null ? (int) $vigente['plan']['id'] : null,
        ]);
    }
}
