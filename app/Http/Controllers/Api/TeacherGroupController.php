<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TeacherGroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // La identidad procede del middleware que valida Supabase Auth.
        // El mismo middleware mantiene app.docente_id durante la peticion.
        $docenteId = $request->user()->getAuthIdentifier();

        $grupos = DB::table('grupo')
            ->select(['id', 'materia', 'folio_codigo', 'activo'])
            ->where('docente_id', $docenteId)
            ->orderBy('id')
            ->get();

        return response()->json(['data' => $grupos]);
    }

    public function store(Request $request): JsonResponse
    {
        // No admitir docente_id, folio_codigo ni activo desde el cliente.
        $datos = $request->validate([
            'materia' => ['required', 'string', 'min:2', 'max:150'],
        ]);

        $docenteId = $request->user()->getAuthIdentifier();

        return DB::transaction(function () use ($docenteId, $datos): JsonResponse {
            // Serializa creaciones simultaneas del mismo docente para
            // evitar exceder la cuota por peticiones concurrentes.
            DB::selectOne(
                'SELECT pg_advisory_xact_lock(hashtextextended(CAST(? AS text), 0))',
                [$docenteId]
            );

            $docente = DB::table('docente')
                ->select(['id', 'paquete_id', 'estado_pago'])
                ->where('id', $docenteId)
                ->first();

            if (!$docente || $docente->estado_pago !== 'activo' || !$docente->paquete_id) {
                return response()->json([
                    'status' => 'error',
                    'code' => 403,
                    'message' => 'El docente no tiene un plan activo.',
                ], 403);
            }

            $paquete = DB::table('paquete')
                ->select(['id', 'max_grupos'])
                ->where('id', $docente->paquete_id)
                ->first();

            if (!$paquete) {
                return response()->json([
                    'status' => 'error',
                    'code' => 403,
                    'message' => 'El plan asignado no esta disponible.',
                ], 403);
            }

            // La vigencia del pago tambien debe estar confirmada.
            // No basta con que estado_pago quede accidentalmente en activo.
            $pagoVigente = DB::table('pago')
                ->where('docente_id', $docenteId)
                ->where('paquete_id', $paquete->id)
                ->where('estado', 'confirmado')
                ->whereRaw('fecha_inicio_vigencia <= CURRENT_TIMESTAMP')
                ->whereRaw('fecha_fin_vigencia > CURRENT_TIMESTAMP')
                ->exists();

            if (!$pagoVigente) {
                return response()->json([
                    'status' => 'error',
                    'code' => 403,
                    'message' => 'No hay un pago confirmado y vigente para el plan.',
                ], 403);
            }

            $usoActual = DB::table('grupo')
                ->where('docente_id', $docenteId)
                ->where('activo', true)
                ->count();

            if ($usoActual >= $paquete->max_grupos) {
                return response()->json([
                    'status' => 'error',
                    'code' => 402,
                    'message' => 'Limite de grupos alcanzado para el plan actual.',
                    'details' => [
                        'current_usage' => $usoActual,
                        'max_allowed' => (int) $paquete->max_grupos,
                    ],
                ], 402);
            }

            // El folio es aleatorio, unico en PostgreSQL y nunca elegido
            // directamente por quien llama a la API.
            $folio = Str::upper(Str::random(16));
            $nuevoId = DB::table('grupo')->insertGetId([
                'docente_id' => $docenteId,
                'materia' => $datos['materia'],
                'folio_codigo' => $folio,
                'activo' => true,
            ]);

            return response()->json([
                'data' => [
                    'id' => $nuevoId,
                    'materia' => $datos['materia'],
                    'folio_codigo' => $folio,
                    'activo' => true,
                ],
            ], 201);
        });
    }
}
