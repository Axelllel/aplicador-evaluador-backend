<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EstadisticasPanelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherAnalyticsController extends Controller
{
    public function show(Request $request, int $groupId, TeacherResultsController $results, EstadisticasPanelService $statistics): JsonResponse
    {
        $group = DB::table('grupo')->where('id', $groupId)->where('docente_id', $request->user()->getAuthIdentifier())->first(['id', 'materia']);
        if (! $group) {
            return response()->json(['message' => 'Grupo no disponible.'], 404);
        }
        $exams = DB::table('examen')->where('grupo_id', $groupId)->orderBy('id')->pluck('id');
        $scores = collect();
        $attempts = 0;
        $pending = 0;
        foreach ($exams as $examId) {
            $response = $results->results($request, (int) $examId);
            if ($response->getStatusCode() !== 200) {
                continue;
            }
            foreach ($response->getData(true)['data']['resultados'] as $row) {
                $attempts++;
                if ($row['calificacion'] !== null) {
                    $scores->push($statistics->aPorcentaje((float) $row['calificacion'], $row['escala']));
                } elseif ($row['estado'] === 'finalizado') {
                    $pending++;
                }
            }
        }
        $errors = DB::table('respuesta_alumno as r')->join('intento_examen as i', 'i.id', '=', 'r.intento_id')
            ->join('examen as e', 'e.id', '=', 'i.examen_id')->join('examen_pregunta as ep', 'ep.id', '=', 'r.examen_pregunta_id')
            ->join('pregunta as p', 'p.id', '=', 'ep.pregunta_id')->where('e.grupo_id', $groupId)
            ->where('i.estado', 'finalizado')->whereNotNull('r.calificacion_final')->groupBy('p.id', 'p.enunciado')
            ->selectRaw('p.id,p.enunciado,count(*) AS evaluadas, round(avg(100-r.calificacion_final),2) AS porcentaje_error')
            ->orderByDesc('porcentaje_error')->limit(5)->get();

        return response()->json(['data' => ['grupo' => $group, 'examenes' => $exams->count(), 'intentos' => $attempts,
            'pendientes_revision' => $pending, 'resumen_porcentaje' => $statistics->resumen($scores), 'preguntas_con_error' => $errors]])->header('Cache-Control', 'no-store');
    }
}
