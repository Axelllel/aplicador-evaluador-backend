<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ModificaExamenBorrador;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Gestion docente: apartados y vinculacion de preguntas a examenes en borrador.
 * Todas las operaciones ocurren bajo VerifySupabaseToken + app.docente_id (RLS).
 */
class TeacherExamSectionController extends Controller
{
    use ModificaExamenBorrador;

    public function index(Request $request, int $examenId): JsonResponse
    {
        $docenteId = (string) $request->user()->getAuthIdentifier();
        if (!$this->examenPropio($examenId, $docenteId)) {
            return response()->json(['message' => 'Examen no disponible.'], 404);
        }

        $apartados = DB::table('apartado')
            ->where('examen_id', $examenId)
            ->orderBy('id')
            ->get(['id', 'examen_id', 'tipo', 'cantidad_preguntas']);

        foreach ($apartados as $apartado) {
            // Vista para el docente; no expone snapshots de respuestas modelo.
            $apartado->preguntas = DB::table('examen_pregunta as ep')
                ->join('pregunta as p', 'p.id', '=', 'ep.pregunta_id')
                ->where('ep.apartado_id', $apartado->id)
                ->orderBy('ep.id')
                ->get([
                    'ep.id as examen_pregunta_id', 'ep.pregunta_id',
                    'p.tipo', 'p.enunciado',
                    'ep.tiempo_segundos', 'ep.ponderacion',
                ]);
        }

        return response()->json(['data' => $apartados]);
    }

    public function store(Request $request, int $examenId): JsonResponse
    {
        $datos = $request->validate([
            'tipo' => ['required', Rule::in([
                'opcion_multiple', 'relacion_columnas', 'abierta', 'programacion',
            ])],
            'cantidad_preguntas' => ['required', 'integer', 'min:1', 'max:200'],
        ]);
        $docenteId = (string) $request->user()->getAuthIdentifier();

        return $this->modificarBorrador($examenId, $docenteId, function () use ($examenId, $datos): JsonResponse {
            $id = DB::table('apartado')->insertGetId([
                'examen_id' => $examenId,
                'tipo' => $datos['tipo'],
                'cantidad_preguntas' => $datos['cantidad_preguntas'],
            ]);

            return response()->json(['data' => [
                'id' => $id,
                'examen_id' => $examenId,
                'tipo' => $datos['tipo'],
                'cantidad_preguntas' => $datos['cantidad_preguntas'],
            ]], 201);
        });
    }

    public function attachQuestion(Request $request, int $examenId, int $apartadoId): JsonResponse
    {
        $datos = $request->validate([
            'pregunta_id' => ['required', 'integer', 'min:1'],
            'tiempo_segundos' => ['required', 'integer', 'min:1', 'max:7200'],
            'ponderacion' => ['required', 'numeric', 'gt:0', 'lte:100', 'decimal:0,2'],
        ]);
        $docenteId = (string) $request->user()->getAuthIdentifier();

        return $this->modificarBorrador($examenId, $docenteId, function () use ($docenteId, $examenId, $apartadoId, $datos): JsonResponse {
            $apartado = DB::table('apartado')
                ->where('id', $apartadoId)
                ->where('examen_id', $examenId)
                ->first(['id', 'tipo', 'cantidad_preguntas']);
            if (!$apartado) {
                return response()->json(['message' => 'Apartado no disponible.'], 404);
            }

            $pregunta = DB::table('pregunta')
                ->where('id', $datos['pregunta_id'])
                ->where('docente_id', $docenteId)
                ->first(['id', 'tipo', 'respuesta_modelo']);
            if (!$pregunta) {
                return response()->json(['message' => 'Pregunta no disponible.'], 404);
            }
            if ($pregunta->tipo !== $apartado->tipo) {
                return response()->json(['message' => 'El tipo de pregunta no coincide con el apartado.'], 422);
            }

            $usadas = DB::table('examen_pregunta')->where('apartado_id', $apartadoId)->count();
            if ($usadas >= $apartado->cantidad_preguntas) {
                return response()->json(['message' => 'El apartado ya tiene la cantidad configurada.'], 409);
            }

            $repetida = DB::table('examen_pregunta as ep')
                ->join('apartado as a', 'a.id', '=', 'ep.apartado_id')
                ->where('a.examen_id', $examenId)
                ->where('ep.pregunta_id', $pregunta->id)
                ->exists();
            if ($repetida) {
                return response()->json(['message' => 'La pregunta ya pertenece a este examen.'], 409);
            }

            // La respuesta modelo se copia del banco a un snapshot inmutable
            // de esta asignacion, y jamas se recibe del cliente.
            $id = DB::table('examen_pregunta')->insertGetId([
                'apartado_id' => $apartadoId,
                'pregunta_id' => $pregunta->id,
                'tiempo_segundos' => $datos['tiempo_segundos'],
                'ponderacion' => $datos['ponderacion'],
                'respuesta_modelo_snapshot' => $pregunta->respuesta_modelo,
            ]);

            return response()->json(['data' => [
                'examen_pregunta_id' => $id,
                'apartado_id' => $apartadoId,
                'pregunta_id' => $pregunta->id,
                'tiempo_segundos' => $datos['tiempo_segundos'],
                'ponderacion' => $datos['ponderacion'],
            ]], 201);
        });
    }

    public function updateWeight(Request $request, int $examenId, int $examenPreguntaId): JsonResponse
    {
        $datos = $request->validate([
            'ponderacion' => ['required', 'numeric', 'gt:0', 'lte:100', 'decimal:0,2'],
        ]);
        $docenteId = (string) $request->user()->getAuthIdentifier();

        return $this->modificarBorrador($examenId, $docenteId, function () use ($examenId, $examenPreguntaId, $datos): JsonResponse {
            $actualizadas = DB::table('examen_pregunta')
                ->where('id', $examenPreguntaId)
                ->whereIn('apartado_id', DB::table('apartado')->select('id')->where('examen_id', $examenId))
                ->update(['ponderacion' => $datos['ponderacion']]);

            if ($actualizadas === 0) {
                return response()->json(['message' => 'Pregunta no disponible en este examen.'], 404);
            }

            return response()->json(['data' => [
                'examen_pregunta_id' => $examenPreguntaId,
                'ponderacion' => $datos['ponderacion'],
            ]]);
        });
    }
}
