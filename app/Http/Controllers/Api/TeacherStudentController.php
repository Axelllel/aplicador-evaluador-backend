<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/** El docente autoriza explicitamente a un alumno para UN examen. */
class TeacherStudentController extends Controller
{
    public function index(Request $request, int $grupoId): JsonResponse
    {
        $uid = (string) $request->user()->getAuthIdentifier();
        $grupo = DB::table('grupo')->where('id', $grupoId)->where('docente_id', $uid)->first(['id', 'materia']);
        if (!$grupo) return response()->json(['message' => 'Grupo no disponible.'], 404);
        $alumnos = DB::table('alumno_grupo as ag')->join('alumno as a', 'a.id', '=', 'ag.alumno_id')
            ->where('ag.grupo_id', $grupoId)->orderBy('a.id')->get(['a.id', 'a.nombre', 'ag.fecha_union']);
        return response()->json(['data' => $alumnos]);
    }

    public function authorization(Request $request, int $examenId, int $alumnoId): JsonResponse
    {
        $uid = (string) $request->user()->getAuthIdentifier();
        $examen = DB::table('examen as e')->join('grupo as g', 'g.id', '=', 'e.grupo_id')
            ->where('e.id', $examenId)->where('g.docente_id', $uid)
            ->where('g.activo', true)->where('e.publicado', true)
            ->first(['e.id', 'e.grupo_id', 'e.fecha_cierre']);
        if (!$examen) return response()->json(['message' => 'Examen no disponible.'], 404);
        $inscrito = DB::table('alumno_grupo')->where('grupo_id', $examen->grupo_id)
            ->where('alumno_id', $alumnoId)->exists();
        if (!$inscrito) return response()->json(['message' => 'Alumno no inscrito en el grupo.'], 404);
        if (strtotime($examen->fecha_cierre) <= time()) {
            return response()->json(['message' => 'El examen ya cerro.'], 409);
        }
        // El pase es un secreto cifrado: no confundirlo con el folio publico del grupo.
        $vence = min(time() + 1800, strtotime($examen->fecha_cierre));
        $pase = Crypt::encryptString(json_encode([
            'kind' => 'bbb-exam-grant-v1', 'exam' => $examenId, 'student' => $alumnoId,
            'teacher' => $uid, 'exp' => $vence, 'nonce' => Str::random(24),
        ], JSON_THROW_ON_ERROR));
        return response()->json(['data' => ['pase' => $pase, 'vence_unix' => $vence,
            'alumno_id' => $alumnoId, 'examen_id' => $examenId]]);
    }
}
