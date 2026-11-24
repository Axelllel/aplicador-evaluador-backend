<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ReglasRegistro;
use App\Services\DatabaseUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/** Alta y recuperacion via Supabase Auth. Nunca crea pagos ni planes activos. */
class SupabaseAccountController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ReglasRegistro::nombre(),
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required', 'string', 'min:10', 'max:200'],
            'telefono' => ReglasRegistro::telefono(),
        ], ReglasRegistro::mensajes());
        $url = config('services.supabase.url');
        $key = config('services.supabase.key');
        if (! $url || ! $key) {
            return response()->json(['message' => 'Supabase no configurado.'], 503);
        }
        try {
            $response = Http::acceptJson()->withHeaders(['apikey' => $key])->timeout(10)
                ->post(rtrim($url, '/').'/auth/v1/signup', [
                    'email' => $data['email'], 'password' => $data['password'],
                ]);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Servicio Auth no disponible.'], 503);
        }
        if (! $response->successful()) {
            $status = $response->status() === 429 ? 429 : ($response->serverError() ? 503 : 422);

            return response()->json(['message' => 'No fue posible registrar la cuenta. Intenta de nuevo más tarde.'], $status);
        }
        $confirmed = filled($response->json('email_confirmed_at') ?? $response->json('user.email_confirmed_at'))
            || filled($response->json('access_token') ?? $response->json('session.access_token'));
        $confirmationRequested = filled($response->json('confirmation_sent_at') ?? $response->json('user.confirmation_sent_at'));
        $profilePending = false;

        // GoTrue /signup devuelve 'id' en la raiz; aceptar formato alternativo si cambia.
        $uid = $response->json('id') ?? $response->json('user.id');
        if (! is_string($uid) || ! Str::isUuid($uid)) {
            return response()->json(['message' => 'La solicitud de registro sigue pendiente.', 'data' => [
                'registration_status' => 'pending', 'confirmation_email_requested' => false,
            ]], 202);
        }
        try {
            app(DatabaseUserContext::class)->ejecutarConDocenteVerificado($uid, function () use ($uid, $data): void {
                DB::table('docente')->insertOrIgnore([
                    'id' => $uid, 'nombre' => $data['nombre'], 'correo' => $data['email'],
                    'telefono' => $data['telefono'] ?? null,
                    // La DB asigna estado_pago='inactivo' por DEFAULT; laravel_app
                    // no tiene permisos de escritura sobre esa columna sensible.
                ]);
            });
        } catch (Throwable $e) {
            // Auth externo y DB no son una transaccion unica; requiere recuperacion administrativa.
            $profilePending = true;
        }
        $status = ! $confirmed ? 'confirmation_required' : ($profilePending ? 'profile_pending' : 'ready');

        return response()->json(['message' => $status === 'ready' ? 'Cuenta creada correctamente.' : 'Registro recibido; revisa el siguiente paso.', 'data' => [
            'registration_status' => $status,
            'email_confirmation_required' => ! $confirmed,
            'confirmation_email_requested' => ! $confirmed && $confirmationRequested,
            'profile_pending' => $profilePending,
        ]], $status === 'ready' ? 201 : 202);
    }

    public function recover(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:150'], 'web_redirect' => ['sometimes', 'boolean']]);
        $url = config('services.supabase.url');
        $key = config('services.supabase.key');
        if (! $url || ! $key) {
            return response()->json(['message' => 'Supabase no configurado.'], 503);
        }
        try {
            $response = Http::acceptJson()->withHeaders(['apikey' => $key])->timeout(10)
                ->post(rtrim($url, '/').'/auth/v1/recover'.(! empty($data['web_redirect']) ? '?'.http_build_query(['redirect_to' => config('services.supabase.password_reset_url')]) : ''), ['email' => $data['email']]);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Servicio Auth no disponible.'], 503);
        }
        if (! $response->successful()) {
            return response()->json(['message' => 'No fue posible solicitar las instrucciones. Intenta de nuevo más tarde.'], $response->status() === 429 ? 429 : 503);
        }

        // No revelar si el correo existe.
        return response()->json(['message' => 'Si la cuenta existe, recibira instrucciones por correo.']);
    }
}
