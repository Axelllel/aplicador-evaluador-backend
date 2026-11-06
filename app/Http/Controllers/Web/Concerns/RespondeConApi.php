<?php

/**
 * Archivo: RespondeConApi.php
 * Módulo: app/Http/Controllers/Web/Concerns
 * Descripción: Traduce la respuesta de la API a una redirección con el mensaje correcto para el docente:
 *              éxito, errores de campo (422), límite del plan (402), falta de plan (403) y fallos del
 *              servicio sin detalles técnicos (Manual COD-WEB, secciones 6.18 y 8.4).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Http\Controllers\Web\Concerns;

use App\Services\RespuestaApi;
use Illuminate\Http\RedirectResponse;

/**
 * Convierte una RespuestaApi en una redirección con mensaje de sesión.
 */
trait RespondeConApi
{
    /**
     * Redirige al destino si la API tuvo éxito; si no, vuelve al formulario con el mensaje que corresponde.
     *
     * @param  string|null  $recurso  «grupos» o «preguntas» cuando el 402 es el límite de ese recurso.
     * @param  string|null  $errorGeneral  Texto de error cuando la API rechaza sin un mensaje conocido.
     */
    protected function redirigirSegunRespuesta(
        RespuestaApi $respuesta,
        RedirectResponse $destinoDeExito,
        string $mensajeDeExito,
        ?string $recurso = null,
        ?string $errorGeneral = null,
    ): RedirectResponse {
        if ($respuesta->exitosa()) {
            return $destinoDeExito->with('exito', $mensajeDeExito);
        }

        $vuelta = redirect()->back()->withInput();

        return match (true) {
            $respuesta->esDeValidacion() && $respuesta->errores() !== [] => $vuelta->withErrors($respuesta->errores()),
            $respuesta->esLimiteDelPlan() => $vuelta->with('aviso', __('mensajes.aviso.'.($recurso !== null ? 'limite_'.$recurso : 'limite_generico'))),
            $respuesta->esSinPermiso() => $vuelta->with('aviso', __('mensajes.aviso.sin_plan')),
            $respuesta->esDemasiadasPeticiones() => $vuelta->with('aviso', __('mensajes.aviso.demasiados_intentos')),
            $respuesta->esFalloDelServicio() => $vuelta->with('error', __('mensajes.error.servicio')),
            default => $vuelta->with('error', $respuesta->mensajeAmigable() ?? $errorGeneral ?? __('mensajes.error.servicio')),
        };
    }
}
