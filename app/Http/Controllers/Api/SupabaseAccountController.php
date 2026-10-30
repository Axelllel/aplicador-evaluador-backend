<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
        $data=$request->validate([
            'nombre'=>['required','string','min:2','max:150'],
            'email'=>['required','email','max:150'],
            'password'=>['required','string','min:10','max:200'],
            'telefono'=>['nullable','string','max:20'],
        ]);
        $url=config('services.supabase.url'); $key=config('services.supabase.key');
        if (!$url || !$key) return response()->json(['message'=>'Supabase no configurado.'],503);
        try {
            $response=Http::acceptJson()->withHeaders(['apikey'=>$key])->timeout(10)
                ->post(rtrim($url,'/').'/auth/v1/signup',[
                    'email'=>$data['email'],'password'=>$data['password'],
                ]);
        } catch (Throwable $e) { return response()->json(['message'=>'Servicio Auth no disponible.'],503); }
        if (!$response->successful()) {
            return response()->json(['message'=>'No fue posible registrar la cuenta.'],422);
        }
        $uid=$response->json('user.id');
        if (!is_string($uid) || !Str::isUuid($uid)) {
            return response()->json(['message'=>'Revisa tu correo para completar el registro.'],202);
        }
        try {
            app(DatabaseUserContext::class)->ejecutarConDocenteVerificado($uid, function () use($uid,$data): void {
                DB::table('docente')->insertOrIgnore([
                    'id'=>$uid,'nombre'=>$data['nombre'],'correo'=>$data['email'],
                    'telefono'=>$data['telefono']??null,'estado_pago'=>'inactivo',
                ]);
            });
        } catch (Throwable $e) {
            // Auth externo y DB no son una transaccion unica; requiere recuperacion administrativa.
            return response()->json(['message'=>'Cuenta creada en Auth; perfil pendiente de sincronizacion.'],202);
        }
        return response()->json(['message'=>'Registro recibido. Verifica el correo si Supabase lo solicita.'],201);
    }

    public function recover(Request $request): JsonResponse
    {
        $data=$request->validate(['email'=>['required','email','max:150']]);
        $url=config('services.supabase.url'); $key=config('services.supabase.key');
        if (!$url || !$key) return response()->json(['message'=>'Supabase no configurado.'],503);
        try {
            Http::acceptJson()->withHeaders(['apikey'=>$key])->timeout(10)
                ->post(rtrim($url,'/').'/auth/v1/recover',['email'=>$data['email']]);
        } catch (Throwable $e) { return response()->json(['message'=>'Servicio Auth no disponible.'],503); }
        // No revelar si el correo existe.
        return response()->json(['message'=>'Si la cuenta existe, recibira instrucciones por correo.']);
    }
}
