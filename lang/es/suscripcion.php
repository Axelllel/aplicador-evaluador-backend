<?php

/**
 * Archivo: suscripcion.php
 * Módulo: lang/es
 * Descripción: Textos de la página de suscripción (solo lectura): plan vigente, planes disponibles y pagos
 *              (Manual COD-WEB, secciones 6.17 y 6.18).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

return [
    'titulo' => 'Suscripción',
    'descripcion' => 'Consulta tu plan, los planes disponibles y tus pagos.',

    'vigente' => [
        'encabezado' => 'Tu plan',
        'vigente_hasta' => 'Vigente hasta el :fecha',
        'sin_plan' => 'No tienes un plan activo.',
        'sin_plan_ayuda' => 'Para activar tu cuenta, solicita un plan a soporte. Cuando se confirme tu pago podrás crear grupos, preguntas y exámenes.',
    ],

    'planes' => [
        'encabezado' => 'Planes disponibles',
        'titulo' => 'Lista de planes',
        'vacia' => 'Aún no hay planes disponibles.',
        'plan_actual' => 'Tu plan',
        'columnas' => [
            'plan' => 'Plan',
            'grupos' => 'Grupos',
            'preguntas' => 'Preguntas',
            'alumnos' => 'Alumnos',
            'precio' => 'Precio',
        ],
    ],

    'pagos' => [
        'encabezado' => 'Tus pagos',
        'titulo' => 'Lista de pagos',
        'vacia' => 'Aún no tienes pagos registrados.',
        'columnas' => [
            'fecha' => 'Fecha',
            'plan' => 'Plan',
            'monto' => 'Monto',
            'estado' => 'Estado',
            'vigencia' => 'Vigencia',
        ],
        'estados' => [
            'confirmado' => 'Confirmado',
            'pendiente' => 'Pendiente',
            'rechazado' => 'Rechazado',
            'vencido' => 'Vencido',
            'cancelado' => 'Cancelado',
        ],
        'vigencia' => 'Del :inicio al :fin',
        'renovacion' => 'Renovación automática',
    ],

    'precio' => '$:monto',
];
