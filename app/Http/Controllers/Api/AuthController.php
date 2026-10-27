<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Docente;
use App\Services\DatabaseUserContext;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);
        
        $url = config('services.supabase.url');
        $key = config('services.supabase.key');

        if (!$url || !$key) {
            return response()->json([
                'message' => 'Supabase Auth no está configurado.'
            ], 503);
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders([
                    'apikey' => $key,
                ])
                ->timeout(10)
                ->post(
                    rtrim($url, '/') . '/auth/v1/token?grant_type=password',
                    $datos
                );
        } catch (ConnectionException $e) {
            return response()->json([
                'message' => 'No se pudo conectar con Supabase Auth.'
            ], 503);
        }

        if (!$response->successful()) {
            return response()->json([
                'message' => 'Credenciales inválidas o cuenta no habilitada.'
            ], $response->status() === 429 ? 429 : 401);
        }

        $userId = $response->json('user.id');

        if (!is_string($userId) || !Str::isUuid($userId)) {
            return response()->json([
                'message' => 'Identidad de usuario inválida.'
            ], 401);
        }

        $docente = app(DatabaseUserContext::class)
            ->ejecutarConDocenteVerificado(
                $userId,
                fn () => Docente::find($userId)
            );

        if (!$docente) {
            return response()->json([
                'message' => 'Docente no registrado en el sistema.'
            ], 403);
        }

        return response()->json([
            'message' => 'Inicio de sesión exitoso.',
            'access_token' => $response->json('access_token'),
            'refresh_token' => $response->json('refresh_token'),
            'token_type' => $response->json('token_type'),
            'expires_in' => $response->json('expires_in'),
            'docente' => [
                'id' => $docente->id,
                'nombre' => $docente->nombre,
                'correo' => $docente->correo,
            ],
        ]);
    }
}