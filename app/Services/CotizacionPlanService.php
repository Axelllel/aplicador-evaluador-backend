<?php

/**
 * Archivo: CotizacionPlanService.php
 * Módulo: app/Services
 * Descripción: Cotiza un plan de suscripción según la cantidad de grupos y de alumnos por grupo elegidos.
 * Autor: Axel (Axelllel)
 * Creado: 08/11/2026
 * Modificado: 20/11/2026 · Leonardo Ballestero López · Prólogo del Manual COD-WEB 6.7
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
