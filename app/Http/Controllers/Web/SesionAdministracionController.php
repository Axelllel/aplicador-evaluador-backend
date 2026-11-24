<?php

/**
 * Archivo: SesionAdministracionController.php
 * Módulo: app/Http/Controllers/Web
 * Descripción: Acceso exclusivo al portal administrativo sin sustituir la sesión docente.
 * Autor: Axel Rebollo Zeferino
 * Creado: 23/11/2026
 * Modificado: 23/11/2026 · Equipo BigBitByte · Separación de accesos
 */

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Exceptions\SesionVencidaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\IniciarSesionRequest;
use App\Services\PanelApiService;
use App\Services\SesionPanelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SesionAdministracionController extends Controller
{
    public function __construct(private readonly PanelApiService $api, private readonly SesionPanelService $sesion) {}

    public function create(): Response|RedirectResponse
    {
        if ($this->sesion->activa() && ($this->sesion->docente()['rol'] ?? '') === 'super_admin') {
            return redirect()->route('panel.docentes');
        }

        return response()->view('auth.admin-login')->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function store(IniciarSesionRequest $request): RedirectResponse
    {
        $response = $this->api->publico('/auth/login', $request->validated());
        if ($response->exitosa() && ($response->cuerpo['docente']['rol'] ?? '') === 'super_admin') {
            $this->sesion->iniciar($request, $response->cuerpo);

            return redirect()->route(($response->cuerpo['mfa_required'] ?? false) ? 'admin.mfa' : 'panel.docentes');
        }
        $redirect = redirect()->route('admin.login')->onlyInput('email');
        if ($response->esDemasiadasPeticiones()) {
            return $redirect->with('aviso', __('mensajes.aviso.demasiados_intentos'));
        }
        if ($response->estado >= 500 || $response->estado === 0) {
            return $redirect->with('error', __('mensajes.error.servicio'));
        }

        return $redirect->withErrors(['email' => 'No se pudo autorizar el acceso administrativo.']);
    }

    public function destroy(Request $request): RedirectResponse
    {
        try {
            $this->api->post('/auth/logout');
        } catch (SesionVencidaException) {
        }
        $this->sesion->cerrar($request);

        return redirect()->route('admin.login')->with('exito', __('mensajes.exito.sesion_cerrada'));
    }
}
