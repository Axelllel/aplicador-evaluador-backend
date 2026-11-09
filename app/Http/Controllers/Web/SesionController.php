<?php

/**
 * Archivo: SesionController.php
 * Módulo: app/Http/Controllers/Web
 * Descripción: Inicia y cierra la sesión del docente en el panel: autentica con POST /api/v1/auth/login,
 *              guarda el token en la sesión (con id de sesión nuevo) y al salir invalida la sesión
 *              (Manual COD-WEB, secciones 6.3, 6.16 y 8.2).
 * Autor: Leonardo Ballestero López
 * Creado: 27/10/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Autenticación real con la API y Supabase Auth
 */

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\IniciarSesionRequest;
use App\Services\PanelApiService;
use App\Services\SesionPanelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controla el inicio y el cierre de sesión del docente.
 */
class SesionController extends Controller
{
    public function __construct(
        private readonly PanelApiService $api,
        private readonly SesionPanelService $sesion,
    ) {
    }

    /**
     * Muestra el formulario de inicio de sesión; si ya hay sesión vigente va directo al panel.
     */
    public function create(): View|RedirectResponse
    {
        if ($this->sesion->activa()) {
            return redirect()->route('panel.inicio');
        }

        return view('auth.login');
    }

    /**
     * Valida el formulario, autentica con la API y entra al panel.
     */
    public function store(IniciarSesionRequest $solicitud): RedirectResponse
    {
        $respuesta = $this->api->publico('/auth/login', $solicitud->validated());

        if ($respuesta->exitosa()) {
            $this->sesion->iniciar($solicitud, $respuesta->cuerpo);

            return redirect()->intended(route('panel.inicio'));
        }

        $vuelta = redirect()->route('login')->onlyInput('email');

        return match (true) {
            $respuesta->estado === 401 => $vuelta->withErrors(['email' => __('sesion.credenciales_invalidas')]),
            $respuesta->esSinPermiso() => $vuelta->with('error', __('sesion.cuenta_no_registrada')),
            $respuesta->esDemasiadasPeticiones() => $vuelta->with('aviso', __('mensajes.aviso.demasiados_intentos')),
            $respuesta->esDeValidacion() && $respuesta->errores() !== [] => $vuelta->withErrors($respuesta->errores()),
            default => $vuelta->with('error', __('mensajes.error.servicio')),
        };
    }

    /**
     * Cierra la sesión: la invalida, renueva el token CSRF y vuelve al inicio de sesión.
     */
    public function destroy(Request $solicitud): RedirectResponse
    {
        $this->sesion->cerrar($solicitud);

        return redirect()
            ->route('login')
            ->with('exito', __('mensajes.exito.sesion_cerrada'));
    }
}
