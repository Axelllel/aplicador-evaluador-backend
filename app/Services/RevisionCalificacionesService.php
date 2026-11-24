<?php

/**
 * Archivo: RevisionCalificacionesService.php
 * Módulo: app/Services
 * Descripción: Guarda revisiones en una transacción; diferencia revisión inicial y corrección docente.
 * Autor: Axel Rebollo Zeferino
 * Creado: 24/11/2026
 * Modificado: 24/11/2026 · Equipo BigBitByte · Revisión conjunta
 */

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RevisionCalificacionesService
{
    public function guardar(string $docente, int $intento, array $notas): int
    {
        return DB::transaction(function () use ($docente, $intento, $notas): int {
            // Serializa la revisión individual y conjunta del mismo intento, sin abrir permisos de escritura directa.
            DB::selectOne('SELECT pg_catalog.pg_advisory_xact_lock(pg_catalog.hashtextextended(?, 0))', ['bbb-revision:'.$intento]);
            $propio = DB::table('intento_examen as i')->join('examen as e', 'e.id', '=', 'i.examen_id')
                ->join('grupo as g', 'g.id', '=', 'e.grupo_id')
                ->where('i.id', $intento)->where('g.docente_id', $docente)->where('i.estado', 'finalizado')->exists();
            abort_unless($propio, 404, 'Intento entregado no disponible.');
            $filas = DB::table('respuesta_alumno')->where('intento_id', $intento)->whereIn('id', array_column($notas, 'id'))->get()->keyBy('id');
            $cambios = [];
            foreach ($notas as $nota) {
                $id = $nota['id'];
                $actual = $filas->get($id);
                abort_if($actual === null, 404, 'Respuesta no disponible en este intento.');
                $manual = (bool) $actual->revisada_manual;
                $igual = $actual->calificacion_final !== null && (float) $actual->calificacion_final === (float) $nota['calificacion'];
                if ($manual && $igual) {
                    continue;
                }
                $motivo = trim((string) ($nota['motivo'] ?? ''));
                if ($manual && $actual->calificacion_final !== null && $motivo === '') {
                    throw ValidationException::withMessages(['respuestas.'.$id.'.motivo' => __('resultados.errores.motivo_obligatorio')]);
                }
                // La función SQL existente conserva la auditoría también al confirmar una nota automática.
                $cambios[] = [$id, $nota['calificacion'], $motivo !== '' ? $motivo : ($actual->calificacion_final !== null ? 'Primera revisión docente.' : null)];
            }
            foreach ($cambios as $cambio) {
                DB::selectOne('SELECT public.fn_calificar_respuesta(CAST(? AS bigint),CAST(? AS numeric),CAST(? AS text)) AS id', $cambio);
            }

            return count($cambios);
        });
    }
}
