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
        'sin_plan_ayuda' => 'Configura tu plan y completa los datos de pago para activar su capacidad.',
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
        'leyenda' => 'El importe estimado corresponde a la capacidad seleccionada.',
        'calcular' => 'Calcular precio',
    ],

    'resumen_pago' => [
        'titulo' => 'Resumen de pago',
        'descripcion' => 'Revisa la capacidad y el precio mensual de tu plan.',
        'estado' => 'Pago en línea pendiente',
        'aviso' => 'El cobro en línea estará disponible cuando se habilite la pasarela de pago.',
        'compra' => 'Resumen de compra',
        'tarjeta' => [
            'titular' => 'Nombre del titular',
            'correo' => 'Correo de facturación',
            'ayuda' => 'Completa los datos de facturación. La pasarela habilitará los campos de la tarjeta.',
            'revisar' => 'Revisar datos',
            'revisados' => 'Datos de facturación completos.',
        ],
        'spei_ayuda' => 'La referencia de transferencia estará disponible al habilitar el cobro.',
        'metodos' => [
            'tarjeta' => 'Tarjeta de crédito o débito',
            'spei' => 'Transferencia SPEI',
        ],
    ],

    'simulacion' => [
        'etiqueta' => 'ACTIVACIÓN DEL PLAN',
        'titulo' => 'Activa tu plan',
        'aviso' => 'Esta es una operación de prueba. El plan se activará en tu cuenta sin realizar ningún cargo.',
        'metodo' => 'Elige tu método de pago',
        'datos_tarjeta' => 'Datos de la tarjeta',
        'tarjeta_prueba' => 'Se valida el formato de los datos. No se realizará ningún cargo.',
        'numero' => 'Número de tarjeta',
        'vencimiento' => 'Vencimiento (MM/AA)',
        'cvv' => 'Código de seguridad (CVV/CID)',
        'datos_spei' => 'Transferencia de prueba',
        'spei_prueba' => 'CLABE de prueba: 032180000118359719. El sistema generará una referencia de prueba, sin hacer una transferencia bancaria.',
        'banco' => 'Banco de prueba',
        'elegir_banco' => 'Elige un banco de prueba',
        'clabe' => 'CLABE de origen de prueba',
        'capacidad' => 'El plan tendrá la capacidad total elegida durante un mes. No se crearán grupos automáticamente.',
        'confirmar' => 'Activar plan',
        'procesando' => 'Activando plan…',
        'error' => 'No se pudo activar el plan. Reintenta con la misma operación.',
        'exito' => 'Plan activado exitosamente',
        'detalle' => 'Tu plan permite :grupos grupos activos, :alumnos alumnos por grupo y :preguntas preguntas en el banco.',
        'referencia' => 'Referencia: :referencia',
        'monto' => 'Importe del plan: $:monto MXN',
        'sin_cobro' => 'No se realizó ningún cobro real.',
        'historial' => 'Pago de prueba',
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
