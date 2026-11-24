<?php

namespace App\Http\Middleware;

use App\Models\Docente;
use App\Services\DatabaseUserContext;
use App\Services\RevocacionSesionService;
use App\Services\SegundoFactorService;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class VerifySupabaseToken
{
    public function handle(Request $request, Closure $next, string $context = 'request'): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'message' => 'Token de autenticación requerido.',
            ], 401);
        }

        $url = config('services.supabase.url');
        $key = config('services.supabase.key');

        if (! $url || ! $key) {
            return response()->json([
                'message' => 'Servicio de autenticación no configurado.',
            ], 503);
        }

        try {
            $response = Http::withHeaders([
                'apikey' => $key,
            ])
                ->withToken($token)
                ->timeout(5)
                ->get(rtrim($url, '/').'/auth/v1/user');
        } catch (ConnectionException $e) {
            return response()->json([
                'message' => 'Servicio de autenticación no disponible.',
            ], 503);
        }

        if (! $response->successful()) {
            return response()->json([
                'message' => 'Token inválido o expirado.',
            ], 401);
        }

        $userId = $response->json('id');

        if (! is_string($userId) || ! Str::isUuid($userId)) {
            return response()->json([
                'message' => 'Identidad inválida.',
            ], 401);
        }

        $request->attributes->set('supabase_user', $response->json());
        $mfa = app(SegundoFactorService::class);
        if (! in_array($context, ['mfa', 'logout'], true) && $mfa->required($response->json(), $token)) {
            return response()->json(['message' => 'Confirma el segundo factor para continuar.', 'code' => 'MFA_REQUIRED'], 403)->header('Cache-Control', 'no-store');
        }
        if ($context === 'logout') {
            return $next($request);
        }
        if (app(RevocacionSesionService::class)->revoked($token)) {
            return response()->json(['message' => 'Token revocado. Inicia sesión de nuevo.', 'code' => 'TOKEN_REVOKED'], 401)->header('Cache-Control', 'no-store');
        }
        if (in_array($context, ['short', 'mfa'], true)) {
            // La IA espera una red externa. Solo autenticar aquí; sus servicios abren
            // transacciones RLS cortas para cada operación de base de datos.
            $docente = app(DatabaseUserContext::class)->ejecutarConDocenteVerificado($userId, fn () => Docente::find($userId));
            if (! $docente) {
                return response()->json(['message' => 'Docente no registrado.'], 403);
            }
            if ($docente->suspendido_en !== null) {
                return response()->json(['message' => 'Cuenta suspendida.', 'code' => 'ACCOUNT_SUSPENDED'], 403);
            }
            Auth::setUser($docente);
            $request->setUserResolver(fn () => $docente);

            return $next($request);
        }

        // Mantener el contexto RLS durante toda la peticion protegida.
        // La identidad ya fue validada por Supabase Auth.
        return app(DatabaseUserContext::class)
            ->ejecutarConDocenteVerificado($userId, function () use ($userId, $request, $next): Response {
                $docente = Docente::find($userId);

                if (! $docente) {
                    return response()->json([
                        'message' => 'Docente no registrado.',
                    ], 403);
                }

                if ($docente->suspendido_en !== null) {
                    return response()->json(['message' => 'Cuenta suspendida.', 'code' => 'ACCOUNT_SUSPENDED'], 403);
                }
                Auth::setUser($docente);
                $request->setUserResolver(fn () => $docente);

                return $next($request);
            });
    }
}
