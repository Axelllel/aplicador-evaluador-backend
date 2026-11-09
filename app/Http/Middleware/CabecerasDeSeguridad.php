<?php

/**
 * Archivo: CabecerasDeSeguridad.php
 * Módulo: app/Http/Middleware
 * Descripción: Añade cabeceras de seguridad a todas las respuestas: evita que el navegador adivine el tipo
 *              de archivo, impide incrustar el sitio en otro (clickjacking), recorta el origen que se envía
 *              al salir y limita los permisos del navegador. HSTS solo en conexiones HTTPS.
 * Autor: Leonardo Ballestero López
 * Creado: 09/11/2026
 * Modificado: 09/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pone las cabeceras de seguridad HTTP en cada respuesta.
 *
 * No incluye Content-Security-Policy: las vistas actuales usan estilos y scripts en línea y fuentes
 * externas, y una política estricta las rompería. Queda pendiente para cuando se muevan a archivos.
 */
class CabecerasDeSeguridad
{
    /**
     * Agrega las cabeceras a la respuesta ya generada.
     */
    public function handle(Request $solicitud, Closure $siguiente): Response
    {
        $respuesta = $siguiente($solicitud);

        $respuesta->headers->set('X-Content-Type-Options', 'nosniff');
        $respuesta->headers->set('X-Frame-Options', 'DENY');
        $respuesta->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // La cámara se permite solo a este sitio: el alumno escanea el QR del grupo.
        $respuesta->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');

        if ($solicitud->isSecure()) {
            $respuesta->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $respuesta;
    }
}
