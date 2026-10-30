<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Endpoints publicos basados en secretos de sesion, sin lectura SQL directa bajo RLS. */
class StudentExamController extends Controller
{
    private function sqlError(QueryException $e): JsonResponse
    {
        // No exponer SQL, datos ni detalles de otros alumnos.
        $state = $e->errorInfo[0] ?? '';
        return response()->json(['message' => 'Solicitud no autorizada o examen no disponible.',
            'code' => $state === 'P0001' ? 'EXAM_UNAVAILABLE' : 'REQUEST_FAILED'],
            $state === 'P0001' ? 403 : 422);
    }

    public function start(Request $request): JsonResponse
    {
        $input = $request->validate([
            'pase' => ['required', 'string', 'max:4096'],
            'plataforma' => ['required', 'in:web,movil'],
        ]);
        try {
            $grant = json_decode(Crypt::decryptString($input['pase']), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Pase invalido.'], 403);
        }
        if (!is_array($grant) || ($grant['kind'] ?? '') !== 'bbb-exam-grant-v1'
            || !is_int($grant['exam'] ?? null) || !is_int($grant['student'] ?? null)
            || !is_int($grant['exp'] ?? null) || $grant['exp'] < time()) {
            return response()->json(['message' => 'Pase vencido o invalido.'], 403);
        }
        // El pase no autoriza mas de un intento, aun si se reutiliza.
        try {
            $row = DB::selectOne('SELECT * FROM public.fn_iniciar_intento(?, ?, ?)', [
                $grant['exam'], $grant['student'], $input['plataforma'],
            ]);
        } catch (QueryException $e) { return $this->sqlError($e); }
        return response()->json(['data' => ['intento_id' => (int) $row->intento_id,
            'token_sesion' => $row->token_sesion, 'fecha_inicio' => $row->fecha_inicio]], 201);
    }

    public function questions(Request $request): JsonResponse
    {
        $input = $request->validate(['token_sesion' => ['required', 'uuid']]);
        try {
            $row = DB::selectOne('SELECT public.fn_preguntas_intento(CAST(? AS uuid)) AS data', [$input['token_sesion']]);
        } catch (QueryException $e) { return $this->sqlError($e); }
        return response()->json(['data' => json_decode($row->data, true)]);
    }

    public function answer(Request $request): JsonResponse
    {
        $input = $request->validate([
            'token_sesion' => ['required', 'uuid'],
            'examen_pregunta_id' => ['required', 'integer', 'min:1'],
            'respuesta_texto' => ['required', 'string', 'max:20000'],
        ]);
        try {
            $row = DB::selectOne('SELECT public.fn_registrar_respuesta(CAST(? AS uuid), CAST(? AS bigint), CAST(? AS text)) AS id', [
                $input['token_sesion'], $input['examen_pregunta_id'], $input['respuesta_texto'],
            ]);
        } catch (QueryException $e) { return $this->sqlError($e); }
        return response()->json(['data' => ['respuesta_id' => (int) $row->id]]);
    }

    public function finish(Request $request): JsonResponse
    {
        $input = $request->validate(['token_sesion' => ['required', 'uuid']]);
        try {
            $row = DB::selectOne('SELECT * FROM public.fn_finalizar_intento(CAST(? AS uuid))', [$input['token_sesion']]);
        } catch (QueryException $e) { return $this->sqlError($e); }
        return response()->json(['data' => ['intento_id' => (int) $row->intento_id,
            'estado' => $row->estado, 'fecha_entrega' => $row->fecha_entrega]]);
    }

    public function result(Request $request): JsonResponse
    {
        $input = $request->validate(['token_sesion' => ['required', 'uuid']]);
        try {
            $row = DB::selectOne('SELECT public.fn_resultado_alumno(CAST(? AS uuid)) AS data', [$input['token_sesion']]);
        } catch (QueryException $e) { return $this->sqlError($e); }
        return response()->json(['data' => json_decode($row->data, true)]);
    }

    public function incident(Request $request): JsonResponse
    {
        $input = $request->validate([
            'token_sesion' => ['required', 'uuid'],
            'tipo_alerta' => ['required', 'in:cambio_pestana,salida_pantalla,conexion,otro'],
            'nivel_severidad' => ['required', 'in:baja,media,alta'],
            'evidencia_url' => ['nullable', 'url:https', 'max:2048'],
        ]);
        try {
            $row = DB::selectOne('SELECT public.fn_registrar_incidencia(CAST(? AS uuid), ?, ?, ?) AS id', [
                $input['token_sesion'], $input['tipo_alerta'], $input['nivel_severidad'], $input['evidencia_url'] ?? null,
            ]);
        } catch (QueryException $e) { return $this->sqlError($e); }
        return response()->json(['data' => ['incidencia_id' => (int) $row->id]], 201);
    }
}
