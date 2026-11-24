<?php

/**
 * Archivo: AdministracionController.php
 * Módulo: app/Http/Controllers/Web
 * Descripción: Administración autorizada de docentes y estados de pago mediante la API.
 * Autor: Axel Rebollo Zeferino
 * Creado: 22/11/2026
 * Modificado: 24/11/2026 · Equipo BigBitByte · Resumen y gestión administrativa
 */

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AdministracionVistaService;
use App\Services\PaginadorPanel;
use App\Services\PanelApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdministracionController extends Controller
{
    public function __construct(private readonly PanelApiService $api, private readonly AdministracionVistaService $vista, private readonly PaginadorPanel $paginador) {}

    public function dashboard(): View
    {
        $docentes = $this->vista->docentes($this->leer('/admin/teachers'));
        $pagos = $this->vista->pagos($this->leer('/admin/payments'));

        return view('admin.dashboard', [
            'resumen' => $this->vista->resumen($docentes, $pagos), 'serie' => $this->vista->serie($pagos),
            'docentes' => $docentes->take(5), 'pendientes' => $pagos->where('estado', 'pendiente')->take(4),
        ]);
    }

    public function teachers(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'estado' => ['nullable', 'in:habilitado,suspendido'], 'page' => ['nullable', 'integer', 'min:1']]);
        $docentes = $this->vista->docentes($this->leer('/admin/teachers'));

        return view('admin.docentes', [
            'docentes' => $this->paginador->paginar($this->vista->filtrarDocentes($docentes, $filters), $request->integer('page', 1)),
            'filtros' => $filters, 'resumen' => $this->vista->resumen($docentes, collect()),
        ]);
    }

    public function payments(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'estado' => ['nullable', 'in:pendiente,confirmado,rechazado'],
            'mes' => ['nullable', 'date_format:Y-m'], 'page' => ['nullable', 'integer', 'min:1']]);
        $pagos = $this->vista->pagos($this->leer('/admin/payments'));

        return view('admin.pagos', [
            'pagos' => $this->paginador->paginar($this->vista->filtrarPagos($pagos, $filters), $request->integer('page', 1)),
            'filtros' => $filters, 'resumen' => $this->vista->resumen(collect(), $pagos),
        ]);
    }

    private function leer(string $path): array
    {
        $response = $this->api->get($path);
        abort_unless($response->exitosa(), $response->estado ?: 503);

        return $response->cuerpo['data'];
    }

    public function teacherStatus(Request $request, string $teacher): RedirectResponse
    {
        $data = $request->validateWithBag('docente_'.$teacher, ['suspender' => ['required', 'boolean'], 'motivo' => ['required', 'string', 'min:10', 'max:1000']]);
        $response = $this->api->patch('/admin/teachers/'.$teacher, $data);

        return redirect()->route('panel.docentes', array_intersect_key($request->query(), array_flip(['q', 'estado', 'page'])))->with($response->exitosa() ? 'exito' : 'error', $response->exitosa() ? __('admin.actualizado') : __('admin.error'));
    }

    public function paymentStatus(Request $request, int $payment): RedirectResponse
    {
        $data = $request->validateWithBag('pago_'.$payment, ['estado' => ['required', 'in:pendiente,confirmado,rechazado'], 'motivo' => ['required', 'string', 'min:10', 'max:1000']]);
        $response = $this->api->patch('/admin/payments/'.$payment, $data);

        return redirect()->route('panel.pagos', array_intersect_key($request->query(), array_flip(['q', 'estado', 'mes', 'page'])))->with($response->exitosa() ? 'exito' : 'error', $response->exitosa() ? __('admin.actualizado') : __('admin.error'));
    }
}
