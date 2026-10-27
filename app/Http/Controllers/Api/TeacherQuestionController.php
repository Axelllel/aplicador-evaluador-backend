<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Banco de preguntas del docente. Se utiliza la misma identidad RLS instalada
 * por VerifySupabaseToken en el contexto de la transaccion HTTP.
 */
class TeacherQuestionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'tipo' => ['sometimes', Rule::in([
                'opcion_multiple', 'relacion_columnas', 'abierta', 'programacion',
            ])],
        ]);
        $docenteId = $request->user()->getAuthIdentifier();

        $consulta = DB::table('pregunta')
            ->where('docente_id', $docenteId)
            ->select(['id', 'tipo', 'enunciado', 'respuesta_modelo', 'opciones']);

        if (isset($datos['tipo'])) {
            $consulta->where('tipo', $datos['tipo']);
        }

        $preguntas = $consulta->orderByDesc('id')->get()->map(function ($pregunta) {
            $pregunta->opciones = $pregunta->opciones === null
                ? null
                : json_decode($pregunta->opciones, true);
            return $pregunta;
        });

        return response()->json(['data' => $preguntas]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'tipo' => ['required', Rule::in([
                'opcion_multiple', 'relacion_columnas', 'abierta', 'programacion',
            ])],
            'enunciado' => ['required', 'string', 'min:3', 'max:10000'],
            'respuesta_modelo' => ['nullable', 'string', 'max:20000'],
            'opciones' => ['nullable', 'array'],
        ]);

        // Evita preguntas de opcion multiple sin alternativas. El formato
        // definitivo del JSON de opciones debe acordarse con ambos clientes.
        if ($datos['tipo'] === 'opcion_multiple' && count($datos['opciones'] ?? []) < 2) {
            return response()->json([
                'message' => 'Se necesitan al menos dos opciones.',
                'errors' => ['opciones' => ['Proporciona dos o mas alternativas.']],
            ], 422);
        }

        $docenteId = $request->user()->getAuthIdentifier();

        return DB::transaction(function () use ($datos, $docenteId): JsonResponse {
            // Serializa operaciones sujetas a cuotas del mismo docente.
            DB::selectOne(
                'SELECT pg_advisory_xact_lock(hashtextextended(CAST(? AS text), 0))',
                [$docenteId]
            );

            $docente = DB::table('docente')
                ->where('id', $docenteId)
                ->first(['paquete_id', 'estado_pago']);

            if (!$docente || $docente->estado_pago !== 'activo' || !$docente->paquete_id) {
                return response()->json([
                    'message' => 'El docente no tiene un plan activo.',
                ], 403);
            }

            $paquete = DB::table('paquete')
                ->where('id', $docente->paquete_id)
                ->first(['max_preguntas']);

            if (!$paquete) {
                return response()->json(['message' => 'Plan no disponible.'], 403);
            }

            $pagoVigente = DB::table('pago')
                ->where('docente_id', $docenteId)
                ->where('paquete_id', $docente->paquete_id)
                ->where('estado', 'confirmado')
                ->whereRaw('fecha_inicio_vigencia <= CURRENT_TIMESTAMP')
                ->whereRaw('fecha_fin_vigencia > CURRENT_TIMESTAMP')
                ->exists();

            if (!$pagoVigente) {
                return response()->json([
                    'message' => 'No hay suscripcion confirmada y vigente.',
                ], 403);
            }

            $usoActual = DB::table('pregunta')
                ->where('docente_id', $docenteId)
                ->count();

            if ($usoActual >= (int) $paquete->max_preguntas) {
                return response()->json([
                    'status' => 'error',
                    'code' => 402,
                    'message' => 'Limite de preguntas alcanzado para el plan actual.',
                    'details' => [
                        'current_usage' => $usoActual,
                        'max_allowed' => (int) $paquete->max_preguntas,
                    ],
                ], 402);
            }

            $nuevo = [
                'docente_id' => $docenteId,
                'tipo' => $datos['tipo'],
                'enunciado' => $datos['enunciado'],
                'respuesta_modelo' => $datos['respuesta_modelo'] ?? null,
                'opciones' => isset($datos['opciones'])
                    ? json_encode($datos['opciones'], JSON_THROW_ON_ERROR)
                    : null,
            ];

            $id = DB::table('pregunta')->insertGetId($nuevo);

            return response()->json([
                'data' => [
                    'id' => $id,
                    'tipo' => $nuevo['tipo'],
                    'enunciado' => $nuevo['enunciado'],
                    'respuesta_modelo' => $nuevo['respuesta_modelo'],
                    'opciones' => $datos['opciones'] ?? null,
                ],
                'message' => 'Pregunta agregada al banco del docente.',
            ], 201);
        });
    }
}
