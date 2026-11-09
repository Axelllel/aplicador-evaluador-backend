<?php

/**
 * Archivo: ejemplo.php
 * Módulo: lang/es
 * Descripción: Datos de ejemplo de la maqueta del panel (usuarios, plan, exámenes, docentes).
 *              Se eliminan cuando existan los modelos y los datos reales.
 * Autor: Leonardo Ballestero López
 * Creado: 27/10/2026
 * Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

return [
    'usuario' => [
        'docente' => 'Laura Méndez Ríos',
        'super_admin' => 'Carlos Rivera Soto',
    ],

    'plan' => [
        'nombre' => 'Plan básico',
    ],

    'planes' => [
        'basico' => 'Plan básico',
        'profesional' => 'Plan profesional',
        'institucional' => 'Plan institucional',
    ],

    'nombre_examen' => ':tipo de :asignatura',

    'tipos_examen' => ['Parcial 1', 'Parcial 2', 'Examen final', 'Diagnóstico', 'Práctica'],

    'asignaturas' => [
        'álgebra',
        'biología',
        'cálculo',
        'física',
        'historia',
        'inglés',
        'programación',
        'química',
        'redes',
        'literatura',
    ],

    'grupos' => ['1A', '1B', '2A', '2B', '3A', '3C'],

    'nombres' => [
        'Ana',
        'Luis',
        'María',
        'Jorge',
        'Elena',
        'Pablo',
        'Sofía',
        'Diego',
        'Lucía',
        'Marcos',
        'Irene',
    ],

    'apellidos' => [
        'García Torres',
        'Hernández Ruiz',
        'López Vega',
        'Martínez Cruz',
        'Pérez Soto',
        'Ramírez Díaz',
        'Sánchez Mora',
        'Torres Luna',
    ],
];
