<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExportacionResultadosService;
use App\Services\RevisionCalificacionesService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TeacherResultsController extends Controller
{
    private function examOwn(Request $request, int $examId): ?object
    {
        return DB::table('examen as e')->join('grupo as g', 'g.id', '=', 'e.grupo_id')
            ->where('e.id', $examId)->where('g.docente_id', $request->user()->getAuthIdentifier())
            ->first(['e.id', 'e.nombre', 'e.escala', 'e.resultado_visible']);
    }

    public function attempts(Request $request, int $examId): JsonResponse
    {
        if (! $this->examOwn($request, $examId)) {
            return response()->json(['message' => 'Examen no disponible.'], 404);
        }
        $rows = DB::table('intento_examen as i')->join('alumno as a', 'a.id', '=', 'i.alumno_id')
            ->leftJoin('cierre_intento as c', 'c.intento_id', '=', 'i.id')
            ->where('i.examen_id', $examId)->orderBy('i.id')
            ->get(['i.id', 'i.alumno_id', 'a.nombre', 'i.numero_intento', 'i.estado', 'i.fecha_inicio', 'i.fecha_entrega', 'c.motivo as motivo_cierre']);

        return response()->json(['data' => $rows]);
    }

    public function answers(Request $request, int $attemptId): JsonResponse
    {
        $attempt = $this->ownAttempt($request, $attemptId);
        if (! $attempt) {
            return response()->json(['message' => 'Intento no disponible.'], 404);
        }
        $rows = DB::table('respuesta_alumno as r')->join('examen_pregunta as ep', 'ep.id', '=', 'r.examen_pregunta_id')
            ->join('pregunta as p', 'p.id', '=', 'ep.pregunta_id')->leftJoin('trabajo_calificacion as t', 't.respuesta_id', '=', 'r.id')
            ->leftJoin('trabajo_calificacion as objetiva', fn ($join) => $join->on('objetiva.intento_id', '=', 'r.intento_id')->where('objetiva.tipo', 'objetivas'))
            ->where('r.intento_id', $attemptId)
            ->selectRaw("CASE WHEN p.tipo IN ('opcion_multiple','relacion_columnas') THEN objetiva.estado ELSE t.estado END AS estado_automatico")
            ->selectRaw('md5(jsonb_build_array(p.tipo,p.enunciado,ep.respuesta_modelo_snapshot,r.respuesta_texto)::text) AS huella_actual')
            ->orderBy('ep.id')->addSelect(['r.id', 'r.examen_pregunta_id', 'p.tipo', 'p.enunciado',
                'r.respuesta_texto', 'r.calificacion_final', 'r.revisada_manual', 'ep.ponderacion',
                'ep.respuesta_modelo_snapshot', 'p.opciones', 't.estado as estado_ia', 't.ultimo_error as error_ia', 'r.puntaje_ia', 'r.retroalimentacion_ia', 'r.tokens_consumidos'])->get();
        foreach ($rows as $row) {
            $row->opciones = is_string($row->opciones) ? json_decode($row->opciones, true) : $row->opciones;
            $row->retroalimentacion_ia = is_string($row->retroalimentacion_ia) ? json_decode($row->retroalimentacion_ia, true) : null;
            if (($row->retroalimentacion_ia['huella_contenido'] ?? null) !== $row->huella_actual) {
                $row->retroalimentacion_ia = null;
                $row->puntaje_ia = null;
            }
            unset($row->huella_actual);
        }

        $attempt->ia_automatica_disponible = (bool) config('ai.enabled') && (bool) config('ai.automatic_grading') && filled(config('ai.key'));

        return response()->json(['data' => ['intento' => $attempt, 'respuestas' => $rows]]);
    }

    public function prepareGrading(Request $request, int $attemptId): JsonResponse
    {
        $attempt = $this->ownAttempt($request, $attemptId);
        if (! $attempt || $attempt->estado !== 'finalizado') {
            return response()->json(['message' => 'Intento entregado no disponible.'], 404);
        }
        try {
            $row = DB::selectOne('SELECT public.fn_preparar_calificacion_intento(?::bigint) AS tareas', [$attemptId]);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? '') === '42883') {
                return response()->json(['message' => 'Falta aplicar la migración de calificación de relación de columnas.', 'code' => 'MIGRATION_REQUIRED'], 503);
            }
            throw $exception;
        }

        return response()->json(['data' => ['tareas_preparadas' => (int) $row->tareas]], 202);
    }

    private function ownAttempt(Request $request, int $attemptId): ?object
    {
        return DB::table('intento_examen as i')->join('examen as e', 'e.id', '=', 'i.examen_id')
            ->join('grupo as g', 'g.id', '=', 'e.grupo_id')
            ->where('i.id', $attemptId)->where('g.docente_id', $request->user()->getAuthIdentifier())
            ->first(['i.id', 'i.examen_id', 'i.alumno_id', 'i.estado', 'e.escala']);
    }

    public function grade(Request $request, int $answerId, RevisionCalificacionesService $revision): JsonResponse
    {
        $data = $request->validate(['calificacion' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'],
            'motivo' => ['nullable', 'string', 'max:2000']]);
        $answer = DB::table('respuesta_alumno')->where('id', $answerId)->first(['intento_id']);
        abort_if($answer === null, 404);
        try {
            $count = $revision->guardar($request->user()->getAuthIdentifier(), (int) $answer->intento_id, [['id' => $answerId] + $data]);
        } catch (QueryException $e) {
            return response()->json(['message' => 'Respuesta no disponible o correccion invalida.'], 422);
        }

        return response()->json(['data' => ['respuesta_id' => $answerId, 'calificacion' => $data['calificacion'], 'actualizadas' => $count]]);
    }

    public function gradeBatch(Request $request, int $attemptId, RevisionCalificacionesService $revision): JsonResponse
    {
        $data = $request->validate([
            'respuestas' => ['required', 'array', 'min:1', 'max:200'],
            'respuestas.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'respuestas.*.calificacion' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'],
            'respuestas.*.motivo' => ['nullable', 'string', 'max:2000'],
        ]);
        try {
            $count = $revision->guardar($request->user()->getAuthIdentifier(), $attemptId, $data['respuestas']);
        } catch (QueryException $e) {
            return response()->json(['message' => 'No se guardó la revisión. Inténtalo nuevamente.'], 422);
        }

        return response()->json(['data' => ['actualizadas' => $count]]);
    }

    public function autoGrade(Request $request, int $attemptId): JsonResponse
    {
        if (! $this->ownAttempt($request, $attemptId)) {
            return response()->json(['message' => 'Intento no disponible.'], 404);
        }
        try {
            $row = DB::selectOne('SELECT public.fn_autocalificar_intento(CAST(? AS bigint)) AS total', [$attemptId]);
        } catch (QueryException $e) {
            return response()->json(['message' => 'El intento no se puede calificar.'], 422);
        }

        return response()->json(['data' => ['preguntas_calificadas' => (int) $row->total,
            'metodo' => 'coincidencia_exacta_opcion_multiple']]);
    }

    public function incidents(Request $request, int $attemptId): JsonResponse
    {
        if (! $this->ownAttempt($request, $attemptId)) {
            return response()->json(['message' => 'Intento no disponible.'], 404);
        }
        $rows = DB::table('incidencia_proctoring')->where('intento_id', $attemptId)
            ->orderBy('id')->get(['id', 'tipo_alerta', 'nivel_severidad', 'evidencia_url', 'fecha_hora', 'evidencia_detalle'])->map(function ($row) {
                $row->evidencia_detalle = json_decode($row->evidencia_detalle, true);

                return $row;
            });

        return response()->json(['data' => $rows]);
    }

    public function results(Request $request, int $examId): JsonResponse
    {
        $exam = $this->examOwn($request, $examId);
        if (! $exam) {
            return response()->json(['message' => 'Examen no disponible.'], 404);
        }
        $totalQuestions = DB::table('examen_pregunta as ep')->join('apartado as a', 'a.id', '=', 'ep.apartado_id')
            ->where('a.examen_id', $examId)->count();
        $attempts = DB::table('intento_examen as i')->join('alumno as a', 'a.id', '=', 'i.alumno_id')
            ->where('i.examen_id', $examId)->orderBy('i.id')->get(['i.id', 'i.alumno_id', 'a.nombre', 'i.estado', 'i.fecha_entrega']);
        $scores = DB::table('respuesta_alumno as r')->join('examen_pregunta as ep', 'ep.id', '=', 'r.examen_pregunta_id')
            ->join('apartado as a', 'a.id', '=', 'ep.apartado_id')->join('intento_examen as i', 'i.id', '=', 'r.intento_id')
            ->where('i.examen_id', $examId)->where('a.examen_id', $examId)->groupBy('r.intento_id')
            ->selectRaw('r.intento_id, count(*) AS respondidas, count(r.calificacion_final) AS calificadas, coalesce(sum(r.calificacion_final * ep.ponderacion / 100),0) AS ponderada')
            ->get()->keyBy('intento_id');
        $results = [];
        foreach ($attempts as $attempt) {
            $score = $scores->get($attempt->id);
            $answered = (int) ($score->respondidas ?? 0);
            $graded = (int) ($score->calificadas ?? 0);
            // Las preguntas sin respuesta cuentan cero; solo las respuestas pendientes requieren revisión.
            $ready = $attempt->estado === 'finalizado' && $graded === $answered && $totalQuestions > 0;
            $results[] = ['intento_id' => $attempt->id, 'alumno_id' => $attempt->alumno_id,
                'nombre' => $attempt->nombre, 'estado' => $attempt->estado, 'fecha_entrega' => $attempt->fecha_entrega,
                'calificadas' => $graded + ($attempt->estado === 'finalizado' ? $totalQuestions - $answered : 0), 'total_preguntas' => $totalQuestions,
                'calificacion' => $ready ? round((float) ($score->ponderada ?? 0) * ($exam->escala === '0-10' ? .1 : 1), 2) : null,
                'escala' => $exam->escala, 'completo' => $ready];
        }

        return response()->json(['data' => ['examen' => $exam, 'resultados' => $results]]);
    }

    public function export(Request $request, int $examId, string $format, ExportacionResultadosService $exporter): Response
    {
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);
        $response = $this->results($request, $examId);
        if ($response->getStatusCode() !== 200) {
            return $response;
        }
        $data = $response->getData(true)['data'];

        return $exporter->download($data['examen'], $data['resultados'], $format);
    }
}
