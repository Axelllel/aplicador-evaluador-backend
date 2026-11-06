<?php

/**
 * Archivo: RequiereSesionPanel.php
 * Módulo: app/Http/Middleware
 * Descripción: Protege las páginas del panel (alias «panel.sesion»): sin token vigente en la sesión redirige
 *              al inicio de sesión, y las respuestas no se guardan en la caché del navegador
 *              (Manual COD-WEB, secciones 6.16 y 8.2).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\SesionPanelService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige que el docente haya iniciado sesión en el panel.
 */
class RequiereSesionPanel
{
    public function __construct(private readonly SesionPanelService $sesion)
    {
    }

    /**
     * Deja pasar solo si hay un token vigente; si había uno vencido avisa en el inicio de sesión.
     */
    public function handle(Request $solicitud, Closure $siguiente): Response
    {
        if (! $this->sesion->activa()) {
            $habiaSesion = $this->sesion->existe();
            $this->sesion->olvidar();

            $redireccion = redirect()->guest(route('login'));

            return $habiaSesion ? $redireccion->with('aviso', __('sesion.vencida')) : $redireccion;
        }

        $respuesta = $siguiente($solicitud);

        // Evita que el botón «atrás» muestre datos del panel después de cerrar sesión.
        $respuesta->headers->set('Cache-Control', 'no-store, private');

        return $respuesta;
    }
}
