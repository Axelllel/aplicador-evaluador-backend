<?php

/**
 * Archivo: CotizacionPlanService.php
 * Módulo: app/Services
 * Descripción: Gestiona y verifica el módulo CotizacionPlanService.
 * Autor: Axel Rebollo Zeferino
 * Creado: 22/11/2026
 * Modificado: 22/11/2026 · Equipo BigBitByte · Integración de requisitos pendientes
 */

declare(strict_types=1);

namespace App\Services;

final class CotizacionPlanService
{
    public const MAX_GRUPOS = 10;

    public const ALUMNOS_POR_GRUPO = [15, 20, 25, 30];

    public const PREGUNTAS = [100, 150, 200];

    public const TARIFAS_CENTAVOS = [
        'base' => 7900,
        'grupo' => 2500,
        'alumno' => 20,
        'pregunta' => 8,
    ];

    public function centavos(int $grupos, int $alumnosPorGrupo, int $preguntas): int
    {
        $tarifas = self::TARIFAS_CENTAVOS;

        return $tarifas['base']
            + $grupos * $tarifas['grupo']
            + $grupos * $alumnosPorGrupo * $tarifas['alumno']
            + $preguntas * $tarifas['pregunta'];
    }
}
