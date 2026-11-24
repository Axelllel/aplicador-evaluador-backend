<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CredencialAlumnoService;
use App\Services\DatabaseUserContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PinRecoveryController extends Controller
{
    public function store(Request $request, DatabaseUserContext $context): JsonResponse
    {
        $data = $request->validate([
            'alumno_id' => ['required', 'integer', 'min:1'],
            'folio_caso' => ['required', 'string', 'min:4', 'max:100'],
            'referencia_identidad' => ['required', 'string', 'min:4', 'max:200'],
            'referencia_canal' => ['required', 'string', 'min:4', 'max:200'],
            'motivo' => ['required', 'string', 'min:10', 'max:1000'],
            'identidad_verificada' => ['required', 'accepted'],
        ]);
        try {
            $row = $context->ejecutarComoDocente(fn () => DB::selectOne(
                'SELECT public.fn_solicitar_recuperacion_pin(?::bigint,?::text,?::text,?::text,?::text) AS id',
                [$data['alumno_id'], $data['folio_caso'], $data['referencia_identidad'], $data['referencia_canal'], $data['motivo']]));
        } catch (QueryException $exception) {
            return $this->error($exception);
        }

        return response()->json(['data' => ['caso_id' => (int) $row->id, 'estado' => 'pendiente_autorizacion']], 201)->header('Cache-Control', 'no-store');
    }

    public function authorizeRecovery(Request $request, int $caseId, DatabaseUserContext $context, CredencialAlumnoService $credentials): JsonResponse
    {
        // Ningún campo del cliente elige el PIN ni la identidad del autorizador.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $pin = $credentials->generarCodigo();
            try {
                $row = $context->ejecutarComoDocente(fn () => DB::selectOne(
                    'SELECT public.fn_autorizar_recuperacion_pin(?::bigint,?::text) AS alumno_id', [$caseId, $credentials->hashCodigo($pin)]));

                return response()->json(['data' => ['alumno_id' => (int) $row->alumno_id, 'codigo_acceso' => $pin],
                    'message' => 'Entrega este código una sola vez mediante el canal validado del caso. Las sesiones anteriores fueron revocadas.'])
                    ->header('Cache-Control', 'no-store');
            } catch (QueryException $exception) {
                if (($exception->errorInfo[0] ?? '') === '23505' && $attempt < 4) {
                    continue;
                }

                return $this->error($exception);
            }
        }

        return response()->json(['message' => 'No fue posible emitir el código.'], 503);
    }

    private function error(QueryException $exception): JsonResponse
    {
        $state = $exception->errorInfo[0] ?? '';

        return response()->json(['message' => 'Operación de recuperación no disponible.'], match ($state) {
            '42501' => 403, 'P1003', '23505' => 409, default => 503,
        })->header('Cache-Control', 'no-store');
    }
}
