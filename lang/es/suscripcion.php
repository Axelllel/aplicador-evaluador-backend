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
    'descripcion' => 'Consulta tu plan y tus pagos, o crea un plan a tu medida.',

    'vigente' => [
        'encabezado' => 'Tu plan',
        'vigente_hasta' => 'Vigente hasta el :fecha',
        'sin_plan' => 'No tienes un plan activo.',
        'sin_plan_ayuda' => 'Para activar tu cuenta, solicita un plan a soporte. Cuando se confirme tu pago podrás crear grupos, preguntas y exámenes.',
    ],

    'planes' => [
        'encabezado' => 'Crea tu plan',
        'descripcion' => 'Elige la capacidad que necesitas y consulta su precio mensual.',
        'registrados' => 'Paquetes registrados',
        'titulo' => 'Lista de planes',
        'vacia' => 'Aún no hay planes disponibles.',
        'plan_actual' => 'Tu plan',
        'columnas' => [
            'plan' => 'Plan',
            'grupos' => 'Grupos',
            'preguntas' => 'Preguntas',
            'alumnos' => 'Alumnos por grupo',
            'precio' => 'Precio',
        ],
    ],

    'cotizacion' => [
        'encabezado' => 'Configura tu plan',
        'grupos' => 'Grupos activos',
        'alumnos' => 'Alumnos por grupo',
        'preguntas' => 'Preguntas en el banco',
        'capacidad' => 'Cupo total de alumnos',
        'precio' => 'Precio mensual estimado',
        'moneda' => 'MXN al mes',
        'leyenda' => 'Cotización simulada para el ambiente de pruebas.',
        'calcular' => 'Calcular precio',
    ],

    'resumen_pago' => [
        'titulo' => 'Resumen de pago',
        'descripcion' => 'Revisa la capacidad y el precio mensual de tu plan.',
        'aviso' => 'La pasarela de pago está pendiente de integración. Este resumen es una cotización de prueba.',
        'seleccionar' => 'Selecciona un método de pago',
        'ayuda' => 'Elige un método para probar el flujo de pago.',
        'continuar' => 'Continuar con la simulación',
        'elegir_otro' => 'Elegir otro método',
        'compra' => 'Resumen de compra',
        'referencia_demo' => 'Referencia de demostración',
        'tarjeta_demo' => [
            'titular' => 'Nombre del titular',
            'numero' => 'Número de tarjeta',
            'vencimiento' => 'Vencimiento',
            'cvv' => 'CVV',
            'ayuda' => 'Los campos muestran datos ficticios para la demostración.',
        ],
        'metodos' => [
            'tarjeta' => [
                'titulo' => 'Tarjeta de crédito o débito',
                'descripcion' => 'Pago de ejemplo con una tarjeta ficticia.',
                'resultado' => 'Simulación con tarjeta completada',
                'detalle' => 'Revisa el importe mensual en el resumen de compra.',
            ],
            'spei' => [
                'titulo' => 'Transferencia SPEI',
                'descripcion' => 'Consulta una referencia de ejemplo para una transferencia.',
                'resultado' => 'Transferencia SPEI simulada',
                'detalle' => 'La referencia es ficticia y sirve únicamente para la demostración.',
            ],
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
