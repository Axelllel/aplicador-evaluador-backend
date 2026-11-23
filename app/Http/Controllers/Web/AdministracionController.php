<?php

/**
 * Archivo: AdministracionController.php
 * Módulo: app/Http/Controllers/Web
 * Descripción: Administración autorizada de docentes y estados de pago mediante la API.
 * Autor: Axel Rebollo Zeferino
 * Creado: 22/11/2026
 * Modificado: 22/11/2026 · Equipo BigBitByte · Administración
 */

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\PanelApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdministracionController extends Controller
{
    public function __construct(private readonly PanelApiService $api) {}

    public function teachers(): View
    {
        $response = $this->api->get('/admin/teachers');
        abort_unless($response->exitosa(), $response->estado ?: 503);

        return view('admin.docentes', ['docentes' => $response->cuerpo['data']]);
    }

    public function payments(): View
    {
        $response = $this->api->get('/admin/payments');
        abort_unless($response->exitosa(), $response->estado ?: 503);

        return view('admin.pagos', ['pagos' => $response->cuerpo['data']]);
    }

    public function teacherStatus(Request $request, string $teacher): RedirectResponse
    {
        $data = $request->validate(['suspender' => ['required', 'boolean'], 'motivo' => ['required', 'string', 'min:10', 'max:1000']]);
        $response = $this->api->patch('/admin/teachers/'.$teacher, $data);

        return redirect()->route('panel.docentes')->with($response->exitosa() ? 'exito' : 'error', $response->exitosa() ? __('admin.actualizado') : __('admin.error'));
    }

    public function paymentStatus(Request $request, int $payment): RedirectResponse
    {
        $data = $request->validate(['estado' => ['required', 'in:pendiente,confirmado,rechazado'], 'motivo' => ['required', 'string', 'min:10', 'max:1000']]);
        $response = $this->api->patch('/admin/payments/'.$payment, $data);

        return redirect()->route('panel.pagos')->with($response->exitosa() ? 'exito' : 'error', $response->exitosa() ? __('admin.actualizado') : __('admin.error'));
    }
}
