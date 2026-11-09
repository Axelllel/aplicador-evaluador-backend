<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CredencialAlumnoService;
use Illuminate\Database\QueryException;
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
        try {
            $alumnos = DB::select('SELECT * FROM public.fn_listar_alumnos_credencial(CAST(? AS bigint))', [$grupoId]);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === 'P1002') {
                return response()->json(['message' => 'Grupo no disponible.'], 404);
            }
            report($exception);
            return response()->json(['message' => 'No se pudo consultar el grupo.'], 503);
        }

        return response()->json(['data' => $alumnos]);
    }

    public function issueCredential(Request $request, int $grupoId, int $alumnoId, CredencialAlumnoService $credenciales): JsonResponse
    {
        for ($intento = 0; $intento < 5; $intento++) {
            $codigo = $credenciales->generarCodigo();
            try {
                DB::selectOne(
                    'SELECT public.fn_asignar_credencial_alumno(CAST(? AS bigint), CAST(? AS bigint), CAST(? AS text)) AS asignada',
                    [$grupoId, $alumnoId, $credenciales->hashCodigo($codigo)]
                );
                return response()->json([
                    'data' => ['alumno_id' => $alumnoId, 'codigo_acceso' => $codigo],
                    'message' => 'Código generado. Entrégalo al alumno ahora; solo se muestra una vez.',
                ], 201);
            } catch (QueryException $exception) {
                $sqlState = $exception->errorInfo[0] ?? null;
                if ($sqlState === '23505') {
                    continue;
                }
                if ($sqlState === 'P1005') {
                    return response()->json(['message' => 'Alumno no disponible en este grupo.'], 404);
                }
                report($exception);
                return response()->json(['message' => 'No se pudo generar el código.'], 503);
            }
        }

        return response()->json(['message' => 'No se pudo generar un código único. Inténtalo de nuevo.'], 503);
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
