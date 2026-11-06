<?php

/**
 * Archivo: RegistroController.php
 * Módulo: app/Http/Controllers/Web
 * Descripción: Registro de docentes con POST /api/v1/auth/register. La cuenta queda inactiva hasta que se
 *              asigne un plan (Manual COD-WEB, secciones 6.16 y 8.3).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegistroRequest;
use App\Services\PanelApiService;
use App\Services\SesionPanelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Controla el alta de cuentas de docente.
 */
class RegistroController extends Controller
{
    public function __construct(
        private readonly PanelApiService $api,
        private readonly SesionPanelService $sesion,
    ) {
    }

    /**
     * Muestra el formulario de registro.
     */
    public function create(): View|RedirectResponse
    {
        if ($this->sesion->activa()) {
            return redirect()->route('panel.inicio');
        }

        return view('auth.registro');
    }

    /**
     * Envía el alta a la API; al terminar avisa que la cuenta espera un plan.
     */
    public function store(RegistroRequest $solicitud): RedirectResponse
    {
        $respuesta = $this->api->publico('/auth/register', $solicitud->datosParaLaApi());

        // 201: cuenta y perfil creados. 202: Supabase pide confirmar el correo o el perfil se sincroniza después.
        if ($respuesta->estado === 201 || $respuesta->estado === 202) {
            $destino = redirect()->route('login');

            if ($respuesta->estado === 202) {
                return $destino->with('aviso', __('registro.pendiente'));
            }

            return $destino
                ->with('exito', __('registro.creada'))
                ->with('aviso', __('registro.sin_plan'));
        }

        $vuelta = redirect()->route('registro')->withInput();

        return match (true) {
            $respuesta->esDeValidacion() && $respuesta->errores() !== [] => $vuelta->withErrors($respuesta->errores()),
            $respuesta->esDeValidacion() => $vuelta->with('error', __('registro.no_posible')),
            $respuesta->esDemasiadasPeticiones() => $vuelta->with('aviso', __('mensajes.aviso.demasiados_intentos')),
            default => $vuelta->with('error', __('mensajes.error.servicio')),
        };
    }
}
