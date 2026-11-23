<?php

/**
 * Archivo: StudentAttemptController.php
 * Módulo: API de alumnos
 * Descripción: Inicia o recupera un intento móvil mediante folio y PIN, sin cookies.
 * Autor: Axel Rebollo Zeferino
 * Creado: 20/11/2026
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartStudentAttemptRequest;
use App\Services\CredencialAlumnoService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentAttemptController extends Controller
{
    public function start(StartStudentAttemptRequest $request, CredencialAlumnoService $credenciales, int $examenId): JsonResponse
    {
        $datos = $request->validated();

        try {
            $intento = DB::selectOne(
                'SELECT * FROM public.fn_iniciar_intento_movil(CAST(? AS text), CAST(? AS text), CAST(? AS bigint))',
                [mb_strtoupper($datos['folio_codigo']), $credenciales->hashCodigo($datos['codigo_acceso']), $examenId],
                false,
            );
        } catch (QueryException $exception) {
            $sqlstate = $exception->errorInfo[0] ?? '';
            [$estado, $codigo, $mensaje] = match ($sqlstate) {
                'P1004' => [401, 'INVALID_CREDENTIAL', 'El código personal no es válido.'],
                'P1002', 'P0001' => [404, 'EXAM_UNAVAILABLE', 'El examen no está disponible para este grupo.'],
                'P1006' => [409, 'ATTEMPT_CLOSED', 'Ya entregaste este examen o el intento fue anulado.'],
                '42883' => [503, 'MIGRATION_REQUIRED', 'Falta habilitar la migración de inicio de exámenes móviles.'],
                default => [503, 'REQUEST_FAILED', 'No se pudo iniciar el examen. Inténtalo de nuevo.'],
            };
            if ($estado === 503) {
                Log::error('Error al iniciar intento móvil', ['sqlstate' => $sqlstate]);
            }

            return response()->json(['message' => $mensaje, 'code' => $codigo], $estado)
                ->header('Cache-Control', 'no-store');
        }

        return response()->json(['data' => [
            'intento_id' => (int) $intento->intento_id,
            'examen_id' => $examenId,
            'token_sesion' => $intento->token_sesion,
            'fecha_inicio' => $intento->fecha_inicio,
            'reanudado' => (bool) $intento->reanudado,
        ]], $intento->reanudado ? 200 : 201)->header('Cache-Control', 'no-store');
    }
}
