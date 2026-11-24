<?php

/**
 * Archivo: RevocacionSesionService.php
 * Módulo: app/Services
 * Descripción: Revoca tokens y sesiones verificadas sin almacenar credenciales en texto.
 * Autor: Axel Rebollo Zeferino
 * Creado: 22/11/2026
 * Modificado: 22/11/2026 · Equipo BigBitByte · Cierre de sesión
 */

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RevocacionSesionService
{
    /** El proveedor debe haber validado este mismo JWT antes de consultar sus claims. */
    public function claims(string $validatedToken): array
    {
        $parts = explode('.', $validatedToken);
        if (count($parts) !== 3) {
            return [];
        }
        $json = base64_decode(strtr($parts[1], '-_', '+/'), true);
        $claims = $json === false ? null : json_decode($json, true);

        return is_array($claims) ? $claims : [];
    }

    public function sessionId(string $validatedToken): ?string
    {
        $id = $this->claims($validatedToken)['session_id'] ?? null;

        return is_string($id) && Str::isUuid($id) ? $id : null;
    }

    public function revoked(string $validatedToken): bool
    {
        return (bool) DB::selectOne('SELECT public.fn_token_docente_revocado(?,?::uuid) AS revoked', [hash('sha256', $validatedToken), $this->sessionId($validatedToken)])->revoked;
    }

    public function revoke(string $validatedToken, string $verifiedUser): void
    {
        $exp = $this->claims($validatedToken)['exp'] ?? null;
        // Un JWT verificado de Supabase siempre incluye exp. El fallback solo sirve a adaptadores de prueba.
        $expiration = is_numeric($exp) ? gmdate('c', (int) $exp) : now()->addHour()->toIso8601String();
        app(DatabaseUserContext::class)->ejecutarConDocenteVerificado($verifiedUser,
            fn () => DB::select('SELECT public.fn_revocar_sesion_docente(?,?::uuid,?::timestamptz)', [hash('sha256', $validatedToken), $this->sessionId($validatedToken), $expiration]));
    }
}
