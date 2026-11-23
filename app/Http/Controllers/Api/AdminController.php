<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function teachers(): JsonResponse
    {
        return $this->invoke('SELECT public.fn_admin_docentes() AS data');
    }

    public function payments(): JsonResponse
    {
        return $this->invoke('SELECT public.fn_admin_pagos() AS data');
    }

    public function teacherStatus(Request $request, string $teacher): JsonResponse
    {
        $data = $request->validate(['suspender' => ['required', 'boolean'], 'motivo' => ['required', 'string', 'min:10', 'max:1000']]);

        return $this->invoke('SELECT public.fn_admin_estado_docente(?::uuid,?::boolean,?::text)', [$teacher, $data['suspender'] ? 'true' : 'false', $data['motivo']]);
    }

    public function paymentStatus(Request $request, int $payment): JsonResponse
    {
        $data = $request->validate(['estado' => ['required', 'in:pendiente,confirmado,rechazado'], 'motivo' => ['required', 'string', 'min:10', 'max:1000']]);

        return $this->invoke('SELECT public.fn_admin_estado_pago(?::bigint,?::text,?::text)', [$payment, $data['estado'], $data['motivo']]);
    }

    private function invoke(string $sql, array $bindings = []): JsonResponse
    {
        try {
            $row = DB::selectOne($sql, $bindings, false);

            return response()->json(['data' => isset($row->data) ? json_decode($row->data, true, 512, JSON_THROW_ON_ERROR) : ['actualizado' => true]])->header('Cache-Control', 'no-store');
        } catch (QueryException $exception) {
            return response()->json(['message' => 'Operación administrativa no disponible.'], match ($exception->errorInfo[0] ?? '') {
                '42501' => 403, 'P1002' => 404, 'P1003' => 422, default => 503,
            })->header('Cache-Control', 'no-store');
        }
    }
}
