<?php

/**
 * Archivo: RespuestaApi.php
 * Módulo: app/Services
 * Descripción: Resultado de una llamada del panel a la API /api/v1: código HTTP y JSON ya decodificado,
 *              con ayudas para distinguir límites, falta de plan, validación y fallos del servicio
 *              (Manual COD-WEB, secciones 8.4 y 6.18).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Services;

/**
 * Respuesta de la API consumida por el panel.
 */
final class RespuestaApi
{
    /**
     * @param  int  $estado  Código HTTP; 0 cuando la API no llegó a responder.
     * @param  array<string, mixed>  $cuerpo  JSON de la respuesta ya decodificado.
     */
    public function __construct(
        public readonly int $estado,
        public readonly array $cuerpo = [],
    ) {
    }

    public function exitosa(): bool
    {
        return $this->estado >= 200 && $this->estado < 300;
    }

    /** 402: el plan llegó a su límite (grupos o preguntas). */
    public function esLimiteDelPlan(): bool
    {
        return $this->estado === 402;
    }

    /** 403: sin plan activo, pago vencido o recurso no permitido. */
    public function esSinPermiso(): bool
    {
        return $this->estado === 403;
    }

    public function esNoEncontrada(): bool
    {
        return $this->estado === 404;
    }

    public function esConflicto(): bool
    {
        return $this->estado === 409;
    }

    public function esDeValidacion(): bool
    {
        return $this->estado === 422;
    }

    public function esDemasiadasPeticiones(): bool
    {
        return $this->estado === 429;
    }

    /** Error 5xx o falta de conexión: el usuario solo debe poder reintentar. */
    public function esFalloDelServicio(): bool
    {
        return $this->estado === 0 || $this->estado >= 500;
    }

    /**
     * Devuelve el contenido de la clave «data» (lista o registro).
     *
     * @return array<int|string, mixed>
     */
    public function datos(): array
    {
        $datos = $this->cuerpo['data'] ?? [];

        return is_array($datos) ? $datos : [];
    }

    /**
     * Devuelve un campo del registro de «data».
     */
    public function dato(string $clave, mixed $porDefecto = null): mixed
    {
        return $this->datos()[$clave] ?? $porDefecto;
    }

    /**
     * Devuelve el texto «message» de la API; es un dato técnico y no se muestra tal cual al usuario.
     */
    public function mensajeDeApi(): ?string
    {
        $mensaje = $this->cuerpo['message'] ?? null;

        return is_string($mensaje) ? $mensaje : null;
    }

    /**
     * Devuelve los errores de validación por campo (formato «errors» de Laravel).
     *
     * @return array<string, list<string>>
     */
    public function errores(): array
    {
        $errores = $this->cuerpo['errors'] ?? [];

        if (! is_array($errores)) {
            return [];
        }

        $porCampo = [];

        foreach ($errores as $campo => $mensajes) {
            $porCampo[(string) $campo] = array_values(array_map('strval', (array) $mensajes));
        }

        return $porCampo;
    }

    /**
     * Devuelve el mensaje de negocio de la API traducido a un texto claro en español,
     * o null si no es uno de los mensajes conocidos (lang/es/api.php).
     */
    public function mensajeAmigable(): ?string
    {
        $mensaje = $this->mensajeDeApi();

        if ($mensaje === null) {
            return null;
        }

        $conocidos = (array) trans('api.mensajes');

        if (isset($conocidos[$mensaje])) {
            return (string) $conocidos[$mensaje];
        }

        if (preg_match('/^El apartado \d+ no tiene la cantidad requerida de preguntas\.$/', $mensaje) === 1) {
            return (string) trans('api.apartado_incompleto');
        }

        return null;
    }
}
