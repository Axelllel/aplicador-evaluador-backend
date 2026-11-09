<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Concerns;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

trait ModificaExamenBorrador
{
    private function examenPropio(int $examenId, string $docenteId): ?object
    {
        return DB::table('examen as e')
            ->join('grupo as g', 'g.id', '=', 'e.grupo_id')
            ->where('e.id', $examenId)
            ->where('g.docente_id', $docenteId)
            ->first(['e.id', 'e.publicado', 'g.activo']);
    }

    /** @param Closure(): JsonResponse $operacion */
    private function modificarBorrador(int $examenId, string $docenteId, Closure $operacion): JsonResponse
    {
        return DB::transaction(function () use ($examenId, $docenteId, $operacion): JsonResponse {
            // Compartir el candado que usa la publicación del examen.
            DB::selectOne('SELECT pg_advisory_xact_lock(hashtextextended(CAST(? AS text), 0))',
                ['bbb-exam-'.$examenId]);

            $examen = $this->examenPropio($examenId, $docenteId);
            if (! $examen) {
                return response()->json(['message' => 'Examen no disponible.'], 404);
            }
            if ($examen->publicado) {
                return response()->json(['message' => 'No se puede modificar un examen publicado.'], 409);
            }
            if (DB::table('intento_examen')->where('examen_id', $examenId)->exists()) {
                return response()->json(['message' => 'No se puede modificar un examen con intentos.'], 409);
            }
            if (! $examen->activo || ! $this->suscripcionVigente($docenteId)) {
                return response()->json(['message' => 'Grupo o suscripcion no disponible.'], 403);
            }

            return $operacion();
        });
    }

    private function suscripcionVigente(string $docenteId): bool
    {
        return DB::table('docente as d')
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
    }
}
