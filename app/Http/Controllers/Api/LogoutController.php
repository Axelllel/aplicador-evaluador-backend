<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RevocacionSesionService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class LogoutController extends Controller
{
    public function __invoke(Request $request, RevocacionSesionService $sessions): JsonResponse
    {
        $token = $request->bearerToken();
        $sessions->revoke($token, $request->attributes->get('supabase_user')['id']);
        try {
            $provider = Http::withHeaders(['apikey' => config('services.supabase.key')])->withToken($token)->timeout(5)
                ->post(rtrim(config('services.supabase.url'), '/').'/auth/v1/logout?scope=local');
            $providerRevoked = $provider->successful() || $provider->status() === 401;
        } catch (ConnectionException) {
            $providerRevoked = false;
        }

        return response()->json(['message' => 'Sesión cerrada.', 'data' => ['token_revocado' => true, 'sesion_proveedor_revocada' => $providerRevoked]])->header('Cache-Control', 'no-store');
    }
}
