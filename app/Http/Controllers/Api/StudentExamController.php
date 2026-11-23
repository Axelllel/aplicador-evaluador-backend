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
        $timeErrors = ['P1010' => ['ATTEMPT_EXPIRED', 'El tiempo del examen terminó.'],
            'P1011' => ['QUESTION_EXPIRED', 'El tiempo de esta pregunta terminó.'],
            'P1012' => ['QUESTION_NOT_OPEN', 'Abre la pregunta antes de responder.']];
        if (isset($timeErrors[$state])) {
            return response()->json(['code' => $timeErrors[$state][0], 'message' => $timeErrors[$state][1]], 409)
                ->header('Cache-Control', 'no-store');
        }
        if ($state === 'P1004') {
            return response()->json(['code' => 'GRANT_REVOKED', 'message' => 'El pase ya no está vigente. Ingresa con tu código personal.'], 403);
        }
        if ($state === '42883') {
            return response()->json(['code' => 'MIGRATION_REQUIRED', 'message' => 'Falta aplicar la migración de control de tiempo.'], 503);
        }

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
        if (! is_array($grant) || ($grant['kind'] ?? '') !== 'bbb-exam-grant-v1'
            || ! is_int($grant['exam'] ?? null) || ! is_int($grant['student'] ?? null)
            || ! is_int($grant['exp'] ?? null) || $grant['exp'] < time()) {
            return response()->json(['message' => 'Pase vencido o invalido.'], 403);
        }
        // El pase no autoriza mas de un intento, aun si se reutiliza.
        try {
            $row = DB::selectOne('SELECT * FROM public.fn_iniciar_intento_pase(?::bigint, ?::bigint, ?::text, ?::bigint)', [
                $grant['exam'], $grant['student'], $input['plataforma'], is_int($grant['credential_version'] ?? null) ? $grant['credential_version'] : null,
            ]);
        } catch (QueryException $e) {
            return $this->sqlError($e);
        }

        return response()->json(['data' => ['intento_id' => (int) $row->intento_id,
            'token_sesion' => $row->token_sesion, 'fecha_inicio' => $row->fecha_inicio]], 201);
    }

    public function questions(Request $request): JsonResponse
    {
        return $this->readQuestions($request, false);
    }

    public function questionsV2(Request $request): JsonResponse
    {
        return $this->readQuestions($request, true);
    }

    private function readQuestions(Request $request, bool $timed): JsonResponse
    {
        $input = $request->validate(['token_sesion' => ['required', 'uuid']]);
        try {
            $time = $timed
                ? json_decode(DB::selectOne('SELECT public.fn_activar_tiempo_por_pregunta(?::uuid) AS data', [$input['token_sesion']], false)->data, true, 512, JSON_THROW_ON_ERROR)
                : $this->timeData($input['token_sesion']);
            if ($time['estado'] !== 'en_progreso') {
                return response()->json(['code' => $timed ? 'ATTEMPT_CLOSED' : 'EXAM_UNAVAILABLE', 'message' => 'El examen ya fue entregado.'], $timed ? 409 : 403)->header('Cache-Control', 'no-store');
            }
            if (! $timed && $time['control_por_pregunta']) {
                return response()->json(['code' => 'UPGRADE_REQUIRED', 'message' => 'Este intento usa tiempo por pregunta. Continúa en la versión actualizada de la aplicación.'], 409)->header('Cache-Control', 'no-store');
            }
            $row = DB::selectOne('SELECT public.fn_preguntas_portal_alumno(CAST(? AS uuid)) AS data', [$input['token_sesion']], false);
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? '') === '42883') {
                return response()->json([
                    'message' => 'Falta habilitar la consulta de preguntas y respuestas guardadas.',
                    'code' => 'MIGRATION_REQUIRED',
                ], 503)->header('Cache-Control', 'no-store');
            }

            return $this->sqlError($e)->header('Cache-Control', 'no-store');
        }

        $data = json_decode($row->data, false, 512, JSON_THROW_ON_ERROR);
        if ($timed) {
            // El listado no revela consignas futuras: abrir cada pregunta fija su primer acceso en BD.
            $data->preguntas = array_map(fn ($question) => (object) [
                'examen_pregunta_id' => $question->examen_pregunta_id, 'apartado_id' => $question->apartado_id,
                'tipo' => $question->tipo, 'tiempo_segundos' => $question->tiempo_segundos,
            ], $data->preguntas);
            $data->version_contrato = 2;
            $data->requiere_abrir_pregunta = true;
        }
        $data->tiempo = $time;

        return response()->json(['data' => $data])->header('Cache-Control', 'no-store');
    }

    private function timeData(string $token, ?int $question = null): array
    {
        $row = DB::selectOne('SELECT public.fn_tiempo_intento(CAST(? AS uuid), CAST(? AS bigint)) AS data', [$token, $question], false);

        return json_decode($row->data, true, 512, JSON_THROW_ON_ERROR);
    }

    public function time(Request $request): JsonResponse
    {
        $input = $request->validate(['token_sesion' => ['required', 'uuid']]);
        try {
            return response()->json(['data' => $this->timeData($input['token_sesion'])])->header('Cache-Control', 'no-store');
        } catch (QueryException $e) {
            return $this->sqlError($e);
        }
    }

    public function openQuestion(Request $request): JsonResponse
    {
        $input = $request->validate(['token_sesion' => ['required', 'uuid'], 'examen_pregunta_id' => ['required', 'integer', 'min:1']]);
        try {
            $time = $this->timeData($input['token_sesion'], (int) $input['examen_pregunta_id']);
            $question = $time['pregunta'] ?? null;
            if (($question['tipo'] ?? '') === 'relacion_columnas' && isset($question['opciones'][0]['izquierda'], $question['opciones'][0]['derecha'])) {
                $pairs = $question['opciones'];
                $right = array_values(array_unique(array_column($pairs, 'derecha')));
                sort($right, SORT_NATURAL | SORT_FLAG_CASE);
                $time['pregunta']['opciones'] = ['izquierda' => array_column($pairs, 'izquierda'), 'derecha' => $right];
            }

            return response()->json(['data' => $time])->header('Cache-Control', 'no-store');
        } catch (QueryException $e) {
            return $this->sqlError($e);
        }
    }

    public function answer(Request $request): JsonResponse
    {
        return $this->saveAnswer($request, false);
    }

    public function answerV2(Request $request): JsonResponse
    {
        return $this->saveAnswer($request, true);
    }

    private function saveAnswer(Request $request, bool $timed): JsonResponse
    {
        $input = $request->validate([
            'token_sesion' => ['required', 'uuid'],
            'examen_pregunta_id' => ['required', 'integer', 'min:1'],
            'respuesta_texto' => ['required', 'string', 'max:20000'],
        ]);
        try {
            $function = $timed ? 'fn_registrar_respuesta_temporizada' : 'fn_registrar_respuesta_compatible';
            $row = DB::selectOne("SELECT public.{$function}(CAST(? AS uuid), CAST(? AS bigint), CAST(? AS text)) AS id", [
                $input['token_sesion'], $input['examen_pregunta_id'], $input['respuesta_texto'],
            ]);
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? '') === 'P1010') {
                $this->timeData($input['token_sesion']);
            }

            return $this->sqlError($e);
        }

        return response()->json(['data' => ['respuesta_id' => (int) $row->id]]);
    }

    public function finish(Request $request): JsonResponse
    {
        $input = $request->validate(['token_sesion' => ['required', 'uuid']]);
        try {
            $row = DB::selectOne('SELECT * FROM public.fn_finalizar_intento(CAST(? AS uuid))', [$input['token_sesion']]);
        } catch (QueryException $e) {
            return $this->sqlError($e);
        }

        return response()->json(['data' => ['intento_id' => (int) $row->intento_id,
            'estado' => $row->estado, 'fecha_entrega' => $row->fecha_entrega]]);
    }

    public function result(Request $request): JsonResponse
    {
        $input = $request->validate(['token_sesion' => ['required', 'uuid']]);
        try {
            $row = DB::selectOne('SELECT public.fn_resultado_alumno(CAST(? AS uuid)) AS data', [$input['token_sesion']]);
        } catch (QueryException $e) {
            return $this->sqlError($e);
        }

        return response()->json(['data' => json_decode($row->data, true)]);
    }

    public function incident(Request $request): JsonResponse
    {
        $input = $request->validate([
            'token_sesion' => ['required', 'uuid'],
            'tipo_alerta' => ['required', 'in:cambio_pestana,salida_pantalla,conexion,rostro_no_detectado,multiples_rostros,camara_desactivada,movimiento,otro'],
            'nivel_severidad' => ['required', 'in:baja,media,alta'],
            'evidencia_url' => ['nullable', 'url:https', 'max:2048'],
        ]);
        try {
            $row = DB::selectOne('SELECT public.fn_registrar_incidencia(CAST(? AS uuid), ?, ?, ?) AS id', [
                $input['token_sesion'], $input['tipo_alerta'], $input['nivel_severidad'], $input['evidencia_url'] ?? null,
            ]);
        } catch (QueryException $e) {
            return $this->sqlError($e);
        }

        return response()->json(['data' => ['incidencia_id' => (int) $row->id]], 201);
    }
}
