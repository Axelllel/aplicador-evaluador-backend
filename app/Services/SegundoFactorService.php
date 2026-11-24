<?php

/**
 * Archivo: SegundoFactorService.php
 * Módulo: app/Services
 * Descripción: Comprueba el nivel de seguridad de tokens previamente validados por Supabase.
 * Autor: Axel Rebollo Zeferino
 * Creado: 22/11/2026
 * Modificado: 22/11/2026 · Equipo BigBitByte · Autenticación
 */

declare(strict_types=1);

namespace App\Services;

class SegundoFactorService
{
    public function verifiedFactors(array $user): array
    {
        return array_values(array_filter($user['factors'] ?? [], fn (array $factor): bool => ($factor['status'] ?? '') === 'verified'));
    }

    /** Solo invocar después de validar este mismo token con Supabase Auth. */
    public function assuranceLevel(string $validatedToken): string
    {
        $parts = explode('.', $validatedToken);
        if (count($parts) !== 3) {
            return 'aal1';
        }
        $json = base64_decode(strtr($parts[1], '-_', '+/'), true);
        $claims = $json === false ? null : json_decode($json, true);

        return is_array($claims) && ($claims['aal'] ?? '') === 'aal2' ? 'aal2' : 'aal1';
    }

    public function required(array $user, string $validatedToken): bool
    {
        return ($this->verifiedFactors($user) !== [] || config('services.supabase.mfa_phone_enabled')) && $this->assuranceLevel($validatedToken) !== 'aal2';
    }
}
