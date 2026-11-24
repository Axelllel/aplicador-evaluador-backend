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

    public const CLAVE_ADMIN = 'administracion_sesion';

    public static function esAdministracion(): bool
    {
        return request()->is('administracion', 'administracion/*');
    }

    public static function claveActual(): string
    {
        return self::esAdministracion() ? self::CLAVE_ADMIN : self::CLAVE;
    }

    public static function rutaLogin(): string
    {
        return self::esAdministracion() ? 'admin.login' : 'login';
    }

    public static function rutaInicio(): string
    {
        return self::esAdministracion() ? 'panel.docentes' : 'panel.inicio';
    }

    public static function rutaMfa(): string
    {
        return self::esAdministracion() ? 'admin.mfa' : 'panel.mfa';
    }

    public static function claveReto(): string
    {
        return self::esAdministracion() ? 'admin_mfa_challenge' : 'mfa_challenge';
    }

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
            'mfa_pendiente' => (bool) ($respuestaDeLogin['mfa_required'] ?? false),
            'expira_en' => $ahora + $duracion - self::MARGEN_EN_SEGUNDOS,
            'docente' => [
                'id' => (string) ($docente['id'] ?? ''),
                'nombre' => (string) ($docente['nombre'] ?? ''),
                'correo' => (string) ($docente['correo'] ?? ''),
                'rol' => ($docente['rol'] ?? '') === 'super_admin' ? 'super_admin' : 'docente',
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
        // El acceso elegido fija el portal; una capacidad administrativa nunca reemplaza al docente.
        if (self::esAdministracion() && ($respuestaDeLogin['docente']['rol'] ?? '') !== 'super_admin') {
            throw new \InvalidArgumentException('La cuenta no tiene permiso administrativo.');
        }
        $respuestaDeLogin['docente']['rol'] = self::esAdministracion() ? 'super_admin' : 'docente';
        $solicitud->session()->put(self::claveActual(), self::datosParaSesion($respuestaDeLogin));
    }

    /**
     * Indica si hay datos de acceso guardados, aunque ya hayan vencido.
     */
    public function existe(): bool
    {
        return is_array(session(self::claveActual())) && $this->token(false) !== null;
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
        $datos = session(self::claveActual());

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
        $datos = session(self::claveActual());

        if (! is_array($datos) || ! is_array($datos['docente'] ?? null)) {
            return null;
        }

        return [
            'id' => (string) ($datos['docente']['id'] ?? ''),
            'nombre' => (string) ($datos['docente']['nombre'] ?? ''),
            'correo' => (string) ($datos['docente']['correo'] ?? ''),
            'rol' => self::esAdministracion() && ($datos['docente']['rol'] ?? '') === 'super_admin' ? 'super_admin' : 'docente',
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
        session()->forget(self::claveActual());
    }

    /**
     * Cierra la sesión: la invalida y renueva el token CSRF.
     */
    public function cerrar(Request $solicitud): void
    {
        if (! $solicitud->hasSession()) {
            return;
        }

        $otraClave = self::esAdministracion() ? self::CLAVE : self::CLAVE_ADMIN;
        $otroReto = self::esAdministracion() ? 'mfa_challenge' : 'admin_mfa_challenge';
        $otraSesion = $solicitud->session()->get($otraClave);
        $reto = $solicitud->session()->get($otroReto);
        $solicitud->session()->invalidate();
        if (is_array($otraSesion)) {
            $solicitud->session()->put($otraClave, $otraSesion);
            if (is_array($reto)) {
                $solicitud->session()->put($otroReto, $reto);
            }
        }
        $solicitud->session()->regenerateToken();
    }
}
