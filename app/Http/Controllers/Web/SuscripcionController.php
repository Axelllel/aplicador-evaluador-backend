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

use App\Enums\MetodoPagoSimulado;
use App\Http\Controllers\Controller;
use App\Http\Requests\CotizacionPlanRequest;
use App\Services\ConsultasPanelService;
use App\Services\CotizacionPlanService;
use App\Services\PaginadorPanel;
use App\Services\PlanDocenteService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
     * Muestra la cotización por capacidad y los paquetes registrados.
     */
    public function catalogo(CotizacionPlanRequest $solicitud, CotizacionPlanService $cotizador): View
    {
        $planes = $this->consultas->planes()->sortBy('precio')->values();
        $vigente = $this->planes->planVigente($planes, $this->consultas->pagos());

        return view('suscripcion.planes', $this->cotizacion($solicitud, $cotizador) + [
            'planes' => $planes,
            'idDelPlanVigente' => $vigente !== null ? (int) $vigente['plan']['id'] : null,
            'opcionesDeGrupos' => range(1, CotizacionPlanService::MAX_GRUPOS),
            'opcionesDeAlumnos' => CotizacionPlanService::ALUMNOS_POR_GRUPO,
            'opcionesDePreguntas' => CotizacionPlanService::PREGUNTAS,
            'tarifasCentavos' => CotizacionPlanService::TARIFAS_CENTAVOS,
        ]);
    }

    public function resumenDePago(CotizacionPlanRequest $solicitud, CotizacionPlanService $cotizador): View
    {
        return view('suscripcion.pago', $this->cotizacion($solicitud, $cotizador) + [
            'metodos' => MetodoPagoSimulado::cases(),
        ]);
    }

    public function simularPago(CotizacionPlanRequest $solicitud, CotizacionPlanService $cotizador): View
    {
        $datos = $solicitud->validate(['metodo' => ['required', Rule::enum(MetodoPagoSimulado::class)]]);

        return view('suscripcion.pago', $this->cotizacion($solicitud, $cotizador) + [
            'metodoSimulado' => MetodoPagoSimulado::from($datos['metodo']),
        ]);
    }

    /** @return array{grupos: int, alumnos: int, preguntas: int, precioCentavos: int} */
    private function cotizacion(CotizacionPlanRequest $solicitud, CotizacionPlanService $cotizador): array
    {
        $grupos = $solicitud->integer('grupos', 1);
        $alumnos = $solicitud->integer('alumnos', CotizacionPlanService::ALUMNOS_POR_GRUPO[0]);
        $preguntas = $solicitud->integer('preguntas', CotizacionPlanService::PREGUNTAS[0]);

        return [
            'grupos' => $grupos,
            'alumnos' => $alumnos,
            'preguntas' => $preguntas,
            'precioCentavos' => $cotizador->centavos($grupos, $alumnos, $preguntas),
        ];
    }
}
