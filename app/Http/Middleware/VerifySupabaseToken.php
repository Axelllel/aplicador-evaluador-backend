<?php

namespace App\Http\Middleware;

use App\Models\Docente;
use App\Services\DatabaseUserContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class VerifySupabaseToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'message' => 'Token de autenticación requerido.'
            ], 401);
        }

        $url = config('services.supabase.url');
        $key = config('services.supabase.key');

        if (!$url || !$key) {
            return response()->json([
                'message' => 'Servicio de autenticación no configurado.'
            ], 503);
        }

        try {
            $response = Http::withHeaders([
                'apikey' => $key
            ])
                ->withToken($token)
                ->timeout(5)
                ->get(rtrim($url, '/') . '/auth/v1/user');
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return response()->json([
                'message' => 'Servicio de autenticación no disponible.'
            ], 503);
        }

        if (!$response->successful()) {
            return response()->json([
                'message' => 'Token inválido o expirado.'
            ], 401);
        }

        $userId = $response->json('id');

        if (!is_string($userId) || !Str::isUuid($userId)) {
            return response()->json([
                'message' => 'Identidad inválida.'
            ], 401);
        }

        // Mantener el contexto RLS durante toda la peticion protegida.
        // La identidad ya fue validada por Supabase Auth.
        return app(DatabaseUserContext::class)
            ->ejecutarConDocenteVerificado($userId, function () use ($userId, $request, $next): Response {
                $docente = Docente::find($userId);

                if (!$docente) {
                    return response()->json([
                        'message' => 'Docente no registrado.'
                    ], 403);
                }

                Auth::setUser($docente);
                $request->setUserResolver(fn () => $docente);

                return $next($request);
            });
    }
}