<?php

/**
 * Archivo: SesionPanelService.php
 * Módulo: app/Services
 * Descripción: Guarda y consulta en la sesión web el token de acceso de Supabase y los datos del docente
 *              (los tokens se cifran en reposo); renueva el identificador de sesión al entrar y la
 *              invalida al salir (Manual COD-WEB, secciones 6.16 y 8.2).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/**
 * Administra la sesión del docente que entró al panel.
 */
class SesionPanelService
{
    /** Clave de la sesión donde viven los datos de acceso. */
    public const CLAVE = 'panel_sesion';

    /** Segundos de margen para no usar un token que está a punto de vencer. */
    private const MARGEN_EN_SEGUNDOS = 30;

    private const DURACION_POR_OMISION_EN_SEGUNDOS = 3600;

    /**
     * Convierte la respuesta de /api/v1/auth/login en los datos que se guardan en la sesión.
     *
     * @param  array<string, mixed>  $respuestaDeLogin  JSON completo de la respuesta de inicio de sesión.
     * @return array<string, mixed>
     */
    public static function datosParaSesion(array $respuestaDeLogin, ?int $ahora = null): array
    {
        $ahora ??= time();
        $duracion = (int) ($respuestaDeLogin['expires_in'] ?? 0);
        $duracion = $duracion > 0 ? $duracion : self::DURACION_POR_OMISION_EN_SEGUNDOS;
        $docente = (array) ($respuestaDeLogin['docente'] ?? []);

        return [
            'access_token' => Crypt::encryptString((string) ($respuestaDeLogin['access_token'] ?? '')),
            'refresh_token' => Crypt::encryptString((string) ($respuestaDeLogin['refresh_token'] ?? '')),
            'expira_en' => $ahora + $duracion - self::MARGEN_EN_SEGUNDOS,
            'docente' => [
                'id' => (string) ($docente['id'] ?? ''),
                'nombre' => (string) ($docente['nombre'] ?? ''),
                'correo' => (string) ($docente['correo'] ?? ''),
            ],
        ];
    }

    /**
     * Abre la sesión del docente: renueva el identificador (anti fijación) y guarda sus datos.
     *
     * @param  array<string, mixed>  $respuestaDeLogin
     */
    public function iniciar(Request $solicitud, array $respuestaDeLogin): void
    {
        $solicitud->session()->regenerate();
        $solicitud->session()->put(self::CLAVE, self::datosParaSesion($respuestaDeLogin));
    }

    /**
     * Indica si hay datos de acceso guardados, aunque ya hayan vencido.
     */
    public function existe(): bool
    {
        return is_array(session(self::CLAVE)) && $this->token(false) !== null;
    }

    /**
     * Indica si hay un token vigente.
     */
    public function activa(): bool
    {
        return $this->token() !== null;
    }

    /**
     * Devuelve el token de acceso en claro, o null si no hay sesión o ya venció.
     */
    public function token(bool $exigirVigencia = true): ?string
    {
        $datos = session(self::CLAVE);

        if (! is_array($datos) || ! isset($datos['access_token'], $datos['expira_en'])) {
            return null;
        }

        if ($exigirVigencia && (int) $datos['expira_en'] <= time()) {
            return null;
        }

        try {
            $token = Crypt::decryptString((string) $datos['access_token']);
        } catch (DecryptException) {
            return null;
        }

        return $token !== '' ? $token : null;
    }

    /**
     * Devuelve los datos del docente de la sesión (id, nombre y correo).
     *
     * @return array{id: string, nombre: string, correo: string}|null
     */
    public function docente(): ?array
    {
        $datos = session(self::CLAVE);

        if (! is_array($datos) || ! is_array($datos['docente'] ?? null)) {
            return null;
        }

        return [
            'id' => (string) ($datos['docente']['id'] ?? ''),
            'nombre' => (string) ($datos['docente']['nombre'] ?? ''),
            'correo' => (string) ($datos['docente']['correo'] ?? ''),
        ];
    }

    /**
     * Devuelve el nombre que se muestra en la barra superior.
     */
    public function nombre(): ?string
    {
        $nombre = $this->docente()['nombre'] ?? '';

        return $nombre !== '' ? $nombre : null;
    }

    /**
     * Borra solo los datos de acceso (sesión vencida).
     */
    public function olvidar(): void
    {
        session()->forget(self::CLAVE);
    }

    /**
     * Cierra la sesión: la invalida y renueva el token CSRF.
     */
    public function cerrar(Request $solicitud): void
    {
        if (! $solicitud->hasSession()) {
            return;
        }

        $solicitud->session()->invalidate();
        $solicitud->session()->regenerateToken();
    }
}
