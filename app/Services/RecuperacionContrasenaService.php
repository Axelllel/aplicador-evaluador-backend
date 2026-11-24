<?php

/**
 * Archivo: RecuperacionContrasenaService.php
 * Módulo: app/Services
 * Descripción: Verifica el enlace de recuperación con Supabase y consume la autorización antes de cambiar la contraseña.
 * Autor: Axel Rebollo Zeferino
 * Creado: 24/11/2026
 * Modificado: 24/11/2026 · Equipo BigBitByte · Recuperación docente
 */

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RecuperacionContrasenaService
{
    public function __construct(private readonly RevocacionSesionService $revocacion) {}

    public function validar(#[\SensitiveParameter] string $token): array
    {
        $url = config('services.supabase.url');
        $key = config('services.supabase.key');
        if (! $url || ! $key) {
            throw new HttpException(503, __('recuperacion.servicio'));
        }
        try {
            $response = Http::acceptJson()->withHeaders(['apikey' => $key])->withToken($token)->timeout(10)->get(rtrim($url, '/').'/auth/v1/user');
        } catch (ConnectionException) {
            throw new HttpException(503, __('recuperacion.servicio'));
        }
        if ($response->serverError() || $response->status() === 429) {
            throw new HttpException(503, __('recuperacion.servicio'));
        }
        if (! $response->successful()) {
            $this->invalido();
        }
        // Leer claims solamente después de que Supabase haya validado este mismo JWT.
        $claims = $this->revocacion->claims($token);
        $recovery = collect($claims['amr'] ?? [])->firstWhere('method', 'recovery');
        $uid = $response->json('id');
        $email = $response->json('email');
        if (! is_string($uid) || ! Str::isUuid($uid) || ! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)
            || ($claims['sub'] ?? null) !== $uid || (int) ($claims['exp'] ?? 0) <= time()
            || ! $recovery || (int) ($recovery['timestamp'] ?? 0) < time() - 900 || (int) ($recovery['timestamp'] ?? 0) > time() + 60
            || $this->revocacion->revoked($token)) {
            $this->invalido();
        }

        return ['id' => $uid, 'email' => $email, 'expira' => min((int) $claims['exp'], (int) $recovery['timestamp'] + 900)];
    }

    public function cambiar(#[\SensitiveParameter] string $token, #[\SensitiveParameter] string $password, string $email): void
    {
        Cache::lock('password-reset:'.hash('sha256', $token), 30)->block(3, function () use ($token, $password, $email): void {
            $user = $this->validar($token);
            if (! hash_equals(mb_strtolower($user['email']), mb_strtolower($email))) {
                $this->invalido();
            }
            // El enlace queda consumido incluso si falla la red: no se reutiliza una autorización de cambio.
            $this->revocacion->revoke($token, $user['id']);
            try {
                $response = Http::acceptJson()->withHeaders(['apikey' => config('services.supabase.key')])->withToken($token)->timeout(10)
                    ->put(rtrim(config('services.supabase.url'), '/').'/auth/v1/user', ['password' => $password]);
            } catch (ConnectionException) {
                throw new HttpException(503, __('recuperacion.nuevo_enlace'));
            }
            if (! $response->successful() || $response->json('id') !== $user['id']) {
                throw ValidationException::withMessages(['password' => __('recuperacion.nuevo_enlace')]);
            }
            // Revocar también los refresh tokens del proveedor. No abre una sesión del portal.
            try {
                Http::acceptJson()->withHeaders(['apikey' => config('services.supabase.key')])->withToken($token)->timeout(5)
                    ->post(rtrim(config('services.supabase.url'), '/').'/auth/v1/logout?scope=global');
            } catch (ConnectionException) {
                // La autorización de recuperación ya está revocada de forma inmediata en el gateway.
            }
        });
    }

    private function invalido(): never
    {
        throw ValidationException::withMessages(['enlace' => __('recuperacion.enlace_invalido')]);
    }
}
