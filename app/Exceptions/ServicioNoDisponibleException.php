<?php

/**
 * Archivo: ServicioNoDisponibleException.php
 * Módulo: app/Exceptions
 * Descripción: Se lanza cuando una consulta del panel a la API falla (5xx, sin conexión o respuesta inesperada);
 *              muestra una página con un mensaje genérico y el botón Reintentar, sin detalles técnicos
 *              (Manual COD-WEB, sección 6.18).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * La API no pudo entregar los datos que necesitaba una página del panel.
 */
class ServicioNoDisponibleException extends RuntimeException
{
    /**
     * El detalle técnico ya quedó registrado donde ocurrió el fallo.
     */
    public function report(): bool
    {
        return false;
    }

    /**
     * Muestra la página de error amable dentro del diseño del panel.
     */
    public function render(Request $solicitud): Response
    {
        return response()->view('panel.error', [
            'titulo' => __('errores.servicio.titulo'),
            'mensaje' => __('errores.servicio.mensaje'),
        ], 503);
    }
}
