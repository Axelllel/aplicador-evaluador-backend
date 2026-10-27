<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Publicacion controlada de examenes, de acuerdo al Manual BD v5.3.
 * Requiere VerifySupabaseToken y su contexto transaccional app.docente_id.
 */
class TeacherExamPublishController extends Controller
{
    public function publish(Request $request, int $examenId): JsonResponse
    {
        $docenteId = (string) $request->user()->getAuthIdentifier();

        return DB::transaction(function () use ($examenId, $docenteId): JsonResponse {
            // Mismo candado usado al agregar apartados y reactivos.
            // Evita publicar al mismo tiempo que se modifica el contenido.
            DB::selectOne(
                'SELECT pg_advisory_xact_lock(hashtextextended(CAST(? AS text), 0))',
                ['bbb-exam-' . $examenId]
            );

            $examen = DB::table('examen as e')
                ->join('grupo as g', 'g.id', '=', 'e.grupo_id')
                ->where('e.id', $examenId)
                ->where('g.docente_id', $docenteId)
                ->first([
                    'e.id', 'e.nombre', 'e.publicado', 'e.grupo_id',
                    'e.fecha_apertura', 'e.fecha_cierre', 'g.activo',
                ]);

            if (!$examen) {
                return response()->json(['message' => 'Examen no disponible.'], 404);
            }

            if ($examen->publicado) {
                return response()->json([
                    'data' => ['id' => $examenId, 'publicado' => true],
                    'message' => 'El examen ya estaba publicado.',
                ], 200);
            }

            if (!$examen->activo) {
                return response()->json(['message' => 'El grupo esta inactivo.'], 403);
            }

            // Mismas reglas de vigencia utilizadas por los controladores anteriores.
            $suscripcionVigente = DB::table('docente as d')
                ->join('pago as pg', function ($join) {
                    $join->on('pg.docente_id', '=', 'd.id')
                        ->on('pg.paquete_id', '=', 'd.paquete_id');
                })
                ->where('d.id', $docenteId)
                ->where('d.estado_pago', 'activo')
                ->where('pg.estado', 'confirmado')
                ->whereRaw('pg.fecha_inicio_vigencia <= CURRENT_TIMESTAMP')
                ->whereRaw('pg.fecha_fin_vigencia > CURRENT_TIMESTAMP')
                ->exists();

            if (!$suscripcionVigente) {
                return response()->json(['message' => 'Suscripcion no vigente.'], 403);
            }

            if (!DB::table('examen')
                ->where('id', $examenId)
                ->whereRaw('fecha_cierre > CURRENT_TIMESTAMP')
                ->exists()) {
                return response()->json([
                    'message' => 'No se puede publicar un examen cuya fecha de cierre ya paso.',
                ], 409);
            }

            $apartados = DB::table('apartado')
                ->where('examen_id', $examenId)
                ->get(['id', 'tipo', 'cantidad_preguntas']);

            if ($apartados->isEmpty()) {
                return response()->json([
                    'message' => 'El examen necesita al menos un apartado.',
                ], 422);
            }

            $preguntas = DB::table('examen_pregunta as ep')
                ->join('apartado as a', 'a.id', '=', 'ep.apartado_id')
                ->join('pregunta as p', 'p.id', '=', 'ep.pregunta_id')
                ->where('a.examen_id', $examenId)
                ->get([
                    'ep.apartado_id', 'ep.pregunta_id', 'ep.tiempo_segundos',
                    'ep.ponderacion', 'ep.respuesta_modelo_snapshot',
                    'p.tipo', 'p.docente_id', 'p.enunciado', 'p.opciones',
                ]);

            $idsPreguntas = [];
            $ponderacionCentimos = 0;

            foreach ($apartados as $apartado) {
                $delApartado = $preguntas->where('apartado_id', $apartado->id);
                if ($delApartado->count() !== (int) $apartado->cantidad_preguntas) {
                    return response()->json([
                        'message' => 'El apartado ' . $apartado->id . ' no tiene la cantidad requerida de preguntas.',
                    ], 422);
                }

                foreach ($delApartado as $pregunta) {
                    if ($pregunta->docente_id !== $docenteId || $pregunta->tipo !== $apartado->tipo) {
                        return response()->json([
                            'message' => 'Hay preguntas ajenas o de un tipo incorrecto.',
                        ], 422);
                    }

                    if (trim((string) $pregunta->enunciado) === '' ||
                        (int) $pregunta->tiempo_segundos <= 0 ||
                        (float) $pregunta->ponderacion <= 0) {
                        return response()->json([
                            'message' => 'Hay preguntas incompletas o con tiempo o ponderacion invalidos.',
                        ], 422);
                    }

                    // Respuesta de referencia necesaria para el sistema evaluador.
                    if (trim((string) ($pregunta->respuesta_modelo_snapshot ?? '')) === '') {
                        return response()->json([
                            'message' => 'Hay preguntas sin respuesta modelo de referencia.',
                        ], 422);
                    }

                    // El formato definitivo de opciones de los clientes sigue pendiente.
                    // Al menos dos opciones para preguntas de opcion multiple.
                    if ($pregunta->tipo === 'opcion_multiple') {
                        $opciones = json_decode((string) $pregunta->opciones, true);
                        if (!is_array($opciones) || count($opciones) < 2) {
                            return response()->json([
                                'message' => 'Una pregunta de opcion multiple no tiene suficientes opciones.',
                            ], 422);
                        }
                    }

                    if (isset($idsPreguntas[$pregunta->pregunta_id])) {
                        return response()->json([
                            'message' => 'Una pregunta aparece mas de una vez en el examen.',
                        ], 422);
                    }
                    $idsPreguntas[$pregunta->pregunta_id] = true;
                    $ponderacionCentimos += (int) round((float) $pregunta->ponderacion * 100);
                }
            }

            // Regla de composicion del backend: las ponderaciones de todas las
            // preguntas del examen suman 100.00%, independiente de la escala.
            if ($ponderacionCentimos !== 10000) {
                return response()->json([
                    'message' => 'La ponderacion total de las preguntas debe sumar 100.00%.',
                    'details' => ['ponderacion_actual' => number_format($ponderacionCentimos / 100, 2, '.', '')],
                ], 422);
            }

            // El UPDATE corre bajo laravel_app + RLS y dentro de la transaccion.
            $actualizados = DB::table('examen')
                ->where('id', $examenId)
                ->where('publicado', false)
                ->update(['publicado' => true]);

            if ($actualizados !== 1) {
                return response()->json([
                    'message' => 'No se pudo completar la publicacion.',
                ], 409);
            }

            return response()->json([
                'data' => [
                    'id' => $examenId,
                    'grupo_id' => $examen->grupo_id,
                    'nombre' => $examen->nombre,
                    'publicado' => true,
                    'apartados' => $apartados->count(),
                    'preguntas' => $preguntas->count(),
                    'fecha_apertura' => $examen->fecha_apertura,
                    'fecha_cierre' => $examen->fecha_cierre,
                ],
                'message' => 'Examen publicado. Solo estara disponible durante su ventana de aplicacion.',
            ], 200);
        });
    }
}
