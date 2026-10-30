<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherResultsController extends Controller
{
    private function examOwn(Request $request, int $examId): ?object
    {
        return DB::table('examen as e')->join('grupo as g', 'g.id', '=', 'e.grupo_id')
            ->where('e.id', $examId)->where('g.docente_id', $request->user()->getAuthIdentifier())
            ->first(['e.id','e.nombre','e.escala','e.resultado_visible']);
    }

    public function attempts(Request $request, int $examId): JsonResponse
    {
        if (!$this->examOwn($request, $examId)) return response()->json(['message' => 'Examen no disponible.'], 404);
        $rows = DB::table('intento_examen as i')->join('alumno as a','a.id','=','i.alumno_id')
            ->where('i.examen_id', $examId)->orderBy('i.id')
            ->get(['i.id','i.alumno_id','a.nombre','i.numero_intento','i.estado','i.fecha_inicio','i.fecha_entrega']);
        return response()->json(['data' => $rows]);
    }

    public function answers(Request $request, int $attemptId): JsonResponse
    {
        $attempt = $this->ownAttempt($request, $attemptId);
        if (!$attempt) return response()->json(['message'=>'Intento no disponible.'], 404);
        $rows = DB::table('respuesta_alumno as r')->join('examen_pregunta as ep','ep.id','=','r.examen_pregunta_id')
            ->join('pregunta as p','p.id','=','ep.pregunta_id')->where('r.intento_id', $attemptId)
            ->orderBy('ep.id')->get(['r.id','r.examen_pregunta_id','p.tipo','p.enunciado',
                'r.respuesta_texto','r.calificacion_final','r.revisada_manual','ep.ponderacion',
                'ep.respuesta_modelo_snapshot']);
        return response()->json(['data'=>['intento'=>$attempt,'respuestas'=>$rows]]);
    }

    private function ownAttempt(Request $request, int $attemptId): ?object
    {
        return DB::table('intento_examen as i')->join('examen as e','e.id','=','i.examen_id')
            ->join('grupo as g','g.id','=','e.grupo_id')
            ->where('i.id',$attemptId)->where('g.docente_id',$request->user()->getAuthIdentifier())
            ->first(['i.id','i.examen_id','i.alumno_id','i.estado','e.escala']);
    }

    public function grade(Request $request, int $answerId): JsonResponse
    {
        $data = $request->validate(['calificacion'=>['required','numeric','between:0,100'],
            'motivo'=>['nullable','string','max:2000']]);
        try {
            DB::selectOne('SELECT public.fn_calificar_respuesta(CAST(? AS bigint),CAST(? AS numeric),CAST(? AS text)) AS id',
                [$answerId,$data['calificacion'],$data['motivo']??null]);
        } catch (QueryException $e) {
            return response()->json(['message'=>'Respuesta no disponible o correccion invalida.'], 422);
        }
        return response()->json(['data'=>['respuesta_id'=>$answerId,'calificacion'=>$data['calificacion']]]);
    }

    public function autoGrade(Request $request, int $attemptId): JsonResponse
    {
        if (!$this->ownAttempt($request,$attemptId)) return response()->json(['message'=>'Intento no disponible.'],404);
        try {
            $row = DB::selectOne('SELECT public.fn_autocalificar_intento(CAST(? AS bigint)) AS total',[$attemptId]);
        } catch (QueryException $e) { return response()->json(['message'=>'El intento no se puede calificar.'],422); }
        return response()->json(['data'=>['preguntas_calificadas'=>(int)$row->total,
            'metodo'=>'coincidencia_exacta_opcion_multiple']]);
    }

    public function incidents(Request $request, int $attemptId): JsonResponse
    {
        if (!$this->ownAttempt($request,$attemptId)) return response()->json(['message'=>'Intento no disponible.'],404);
        $rows = DB::table('incidencia_proctoring')->where('intento_id',$attemptId)
            ->orderBy('id')->get(['id','tipo_alerta','nivel_severidad','evidencia_url','fecha_hora']);
        return response()->json(['data'=>$rows]);
    }

    public function results(Request $request, int $examId): JsonResponse
    {
        $exam = $this->examOwn($request,$examId);
        if (!$exam) return response()->json(['message'=>'Examen no disponible.'],404);
        $totalQuestions = DB::table('examen_pregunta as ep')->join('apartado as a','a.id','=','ep.apartado_id')
            ->where('a.examen_id',$examId)->count();
        $attempts = DB::table('intento_examen as i')->join('alumno as a','a.id','=','i.alumno_id')
            ->where('i.examen_id',$examId)->orderBy('i.id')->get(['i.id','i.alumno_id','a.nombre','i.estado']);
        $results = [];
        foreach ($attempts as $attempt) {
            $answers = DB::table('respuesta_alumno as r')->join('examen_pregunta as ep','ep.id','=','r.examen_pregunta_id')
                ->where('r.intento_id',$attempt->id)->get(['r.calificacion_final','ep.ponderacion']);
            $graded=0; $weighted=0.0;
            foreach ($answers as $answer) {
                if ($answer->calificacion_final !== null) {
                    $graded++;
                    $weighted += ((float)$answer->calificacion_final * (float)$answer->ponderacion)/100;
                }
            }
            $ready = $attempt->estado === 'finalizado' && $graded === $totalQuestions && $totalQuestions>0;
            $results[] = ['intento_id'=>$attempt->id,'alumno_id'=>$attempt->alumno_id,
                'nombre'=>$attempt->nombre,'estado'=>$attempt->estado,
                'calificadas'=>$graded,'total_preguntas'=>$totalQuestions,
                // No publicar notas parciales como definitivas.
                'calificacion'=>$ready ? round($weighted*($exam->escala==='0-10'?.1:1),2) : null,
                'escala'=>$exam->escala, 'completo'=>$ready];
        }
        return response()->json(['data'=>['examen'=>$exam,'resultados'=>$results]]);
    }
}
