<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SegundoFactorService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MfaController extends Controller
{
    public function index(Request $request, SegundoFactorService $mfa): JsonResponse
    {
        $factors = array_map(fn (array $factor): array => [
            'id' => $factor['id'], 'tipo' => $factor['factor_type'], 'estado' => $factor['status'],
            'telefono_final' => isset($factor['phone']) ? substr($factor['phone'], -4) : null,
        ], $request->attributes->get('supabase_user')['factors'] ?? []);

        return response()->json(['data' => ['factores' => $factors, 'nivel' => $mfa->assuranceLevel($request->bearerToken()),
            'alta_telefono_habilitada' => (bool) config('services.supabase.mfa_phone_enabled')]])->header('Cache-Control', 'no-store');
    }

    public function enroll(Request $request): JsonResponse
    {
        if (! config('services.supabase.mfa_phone_enabled')) {
            return response()->json(['message' => 'La verificación por teléfono aún no está habilitada en el servidor.'], 503);
        }
        $data = $request->validate(['telefono' => ['required', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/']]);

        return $this->send($request, 'POST', '/factors', ['factor_type' => 'phone', 'phone' => $data['telefono']]);
    }

    public function challenge(Request $request, string $factor): JsonResponse
    {
        $own = collect($request->attributes->get('supabase_user')['factors'] ?? [])->firstWhere('id', $factor);
        if (! $own) {
            return response()->json(['message' => 'Factor no disponible.'], 404);
        }

        return $this->send($request, 'POST', '/factors/'.$factor.'/challenge', ($own['factor_type'] ?? '') === 'phone' ? ['channel' => 'sms'] : []);
    }

    public function verify(Request $request, string $factor): JsonResponse
    {
        $data = $request->validate(['challenge_id' => ['required', 'uuid'], 'codigo' => ['required', 'string', 'regex:/^[0-9]{6,10}$/']]);
        if (! collect($request->attributes->get('supabase_user')['factors'] ?? [])->contains('id', $factor)) {
            return response()->json(['message' => 'Factor no disponible.'], 404);
        }
        $response = $this->send($request, 'POST', '/factors/'.$factor.'/verify', ['challenge_id' => $data['challenge_id'], 'code' => $data['codigo']]);
        if ($response->getStatusCode() !== 200) {
            return $response;
        }
        $tokens = $response->getData(true)['data'];
        if (! is_string($tokens['access_token'] ?? null) || ! is_string($tokens['refresh_token'] ?? null)
            || (int) ($tokens['expires_in'] ?? 0) <= 0
            || app(SegundoFactorService::class)->assuranceLevel($tokens['access_token']) !== 'aal2'
            || ($tokens['user']['id'] ?? null) !== $request->user()->getAuthIdentifier()) {
            return response()->json(['message' => 'El servicio no confirmó la verificación. Intenta de nuevo.'], 503)->header('Cache-Control', 'no-store');
        }

        return $response;
    }

    public function destroy(Request $request, string $factor, SegundoFactorService $mfa): JsonResponse
    {
        if ($mfa->assuranceLevel($request->bearerToken()) !== 'aal2') {
            return response()->json(['message' => 'Confirma el segundo factor antes de retirarlo.', 'code' => 'MFA_REQUIRED'], 403);
        }

        return $this->send($request, 'DELETE', '/factors/'.$factor);
    }

    private function send(Request $request, string $method, string $path, array $body = []): JsonResponse
    {
        try {
            $response = Http::acceptJson()->withToken($request->bearerToken())->withHeader('apikey', config('services.supabase.key'))
                ->timeout(10)->send($method, rtrim(config('services.supabase.url'), '/').'/auth/v1'.$path, ['json' => $body]);
        } catch (ConnectionException $exception) {
            return response()->json(['message' => 'El servicio de verificación no respondió. Intenta de nuevo.'], 503);
        }
        if (! $response->successful()) {
            $status = match (true) {
                $response->status() === 429 => 429, $response->status() === 401 => 401,
                in_array($response->json('code'), ['mfa_phone_enroll_not_enabled', 'mfa_phone_verify_not_enabled'], true) => 503,
                default => 422
            };

            return response()->json(['message' => $status === 503 ? 'La verificación por teléfono no está configurada.' : 'No se completó la verificación. Revisa el código o intenta de nuevo.'], $status)->header('Cache-Control', 'no-store');
        }

        return response()->json(['data' => $response->json()])->header('Cache-Control', 'no-store');
    }
}
