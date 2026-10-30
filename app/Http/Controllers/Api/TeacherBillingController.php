<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Consultas de planes y pagos. No captura tarjetas ni confirma pagos. */
class TeacherBillingController extends Controller
{
    public function plans(): JsonResponse
    {
        return response()->json(['data'=>DB::table('paquete')->orderBy('id')->get([
            'id','nombre','max_grupos','max_preguntas','max_alumnos','precio',
        ])]);
    }

    public function payments(Request $request): JsonResponse
    {
        $uid=$request->user()->getAuthIdentifier();
        return response()->json(['data'=>DB::table('pago')->where('docente_id',$uid)
            ->orderByDesc('id')->get(['id','paquete_id','fecha','monto','estado',
                'fecha_inicio_vigencia','fecha_fin_vigencia','renovacion_automatica'])]);
    }
}
