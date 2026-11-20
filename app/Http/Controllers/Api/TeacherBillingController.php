<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SimularPagoRequest;
use App\Services\SimulacionPagoService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherBillingController extends Controller
{
    public function plans(): JsonResponse
    {
        return response()->json(['data' => DB::table('paquete')->orderBy('id')->get([
            'id', 'nombre', 'max_grupos', 'max_preguntas', 'max_alumnos', 'precio',
        ])]);
    }

    public function payments(Request $request): JsonResponse
    {
        $uid = $request->user()->getAuthIdentifier();
        $docente = DB::table('docente')->where('id', $uid)->first(['paquete_id', 'estado_pago']);
        $pagos = DB::table('pago')->where('docente_id', $uid)->orderByDesc('id')
            ->get(['id', 'paquete_id', 'fecha', 'monto', 'estado', 'metodo_pago', 'proveedor_pago', 'referencia_externa',
                'fecha_inicio_vigencia', 'fecha_fin_vigencia', 'renovacion_automatica']);
        foreach ($pagos as $pago) {
            $pago->es_plan_actual = $docente && $docente->estado_pago === 'activo' && (int) $docente->paquete_id === (int) $pago->paquete_id;
        }

        return response()->json(['data' => $pagos]);
    }

    public function simulate(SimularPagoRequest $request, SimulacionPagoService $simulador): JsonResponse
    {
        if (! config('services.payment_simulation.enabled')) {
            return response()->json(['message' => 'La simulación de pagos está deshabilitada.'], 403);
        }
        try {
            $datos = DB::transaction(fn (): array => $simulador->confirmar($request->validated()));
        } catch (QueryException $exception) {
            $code = $exception->errorInfo[0] ?? $exception->getCode();
            [$message, $status] = match ($code) {
                'P1001' => ['Docente no disponible.', 403],
                'P1002' => ['Revisa la capacidad y los datos de la simulación.', 422],
                'P1003', '23505' => ['La operación ya se utilizó con otros datos. Vuelve al resumen para iniciar una nueva compra.', 409],
                'P1004' => ['El plan elegido es menor que tu uso actual. Aumenta su capacidad para continuar.', 409],
                '42883', '42703' => ['La simulación todavía no está habilitada en la base de datos. No se registró el pago.', 503],
                default => ['No se pudo confirmar la simulación. Reintenta con la misma operación.', 503],
            };

            return response()->json(['message' => $message], $status);
        }

        return response()->json(['message' => 'Pago simulado confirmado.', 'data' => $datos], $datos['reutilizado'] ? 200 : 201);
    }
}
