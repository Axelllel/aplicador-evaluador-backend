<?php

/**
 * Archivo: SesionVencidaException.php
 * Módulo: app/Exceptions
 * Descripción: Se lanza cuando la API responde 401 (token vencido o inválido); se muestra como una
 *              redirección al inicio de sesión con un aviso en español (Manual COD-WEB, sección 6.18).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Exceptions;

use App\Services\SesionPanelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * La sesión del docente venció y debe volver a iniciar sesión.
 */
class SesionVencidaException extends RuntimeException
{
    /**
     * No se registra en el log: es un caso normal del uso del panel.
     */
    public function report(): bool
    {
        return false;
    }

    /**
     * Cierra la sesión y vuelve al inicio de sesión con una advertencia.
     */
    public function render(Request $solicitud): RedirectResponse
    {
        app(SesionPanelService::class)->cerrar($solicitud);

        return redirect()
            ->route('login')
            ->with('aviso', __('sesion.vencida'));
    }
}
