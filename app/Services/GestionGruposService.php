<?php

/**
 * Archivo: GestionGruposService.php
 * Módulo: app/Services
 * Descripción: Consulta el impacto de retirar grupos y ejecuta funciones limitadas al docente autenticado.
 * Autor: Axel Rebollo Zeferino
 * Creado: 24/11/2026
 * Modificado: 24/11/2026 · Equipo BigBitByte · Gestión de grupos
 */

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GestionGruposService
{
    public function resumen(int $grupo): array
    {
        $row = DB::table('grupo')->where('id', $grupo)->where('docente_id', Auth::id())
            ->first(['id', 'materia', 'folio_codigo', 'activo']);
        abort_if($row === null, 404, 'Grupo no disponible.');
        $examenes = DB::table('examen')->where('grupo_id', $grupo)->count();
        $evaluaciones = DB::table('evaluacion')->where('grupo_id', $grupo)->count();

        return (array) $row + [
            'alumnos' => DB::table('alumno_grupo')->where('grupo_id', $grupo)->count(),
            'examenes' => $examenes,
            'evaluaciones' => $evaluaciones,
            'intentos' => DB::table('intento_examen as i')->join('examen as e', 'e.id', '=', 'i.examen_id')->where('e.grupo_id', $grupo)->count(),
            'intentos_en_curso' => DB::table('intento_examen as i')->join('examen as e', 'e.id', '=', 'i.examen_id')->where('e.grupo_id', $grupo)->where('i.estado', 'en_progreso')->count(),
        ];
    }

    public function retirarGrupo(int $grupo): array
    {
        return $this->ejecutar('SELECT public.fn_eliminar_grupo_docente(?::bigint) AS datos', [$grupo]);
    }

    public function retirarAlumno(int $grupo, int $alumno): array
    {
        return $this->ejecutar('SELECT public.fn_eliminar_alumno_grupo(?::bigint,?::bigint) AS datos', [$grupo, $alumno]);
    }

    private function ejecutar(string $sql, array $parametros): array
    {
        try {
            $row = DB::selectOne($sql, $parametros);
        } catch (QueryException $exception) {
            [$status, $message] = match ($exception->errorInfo[0] ?? '') {
                'P3001' => [404, 'Grupo o alumno no disponible.'],
                'P3002' => [409, 'Hay un examen en curso. Espera a que termine antes de retirar al alumno o al grupo.'],
                '42883' => [503, 'Falta aplicar la migración de gestión de alumnos y grupos.'],
                default => [503, 'No se pudo completar la acción. No se modificaron los datos.'],
            };
            abort($status, $message);
        }

        return json_decode($row->datos, true, flags: JSON_THROW_ON_ERROR);
    }
}
