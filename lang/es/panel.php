<?php

/**
 * Archivo: panel.php
 * Módulo: lang/es
 * Descripción: Textos del diseño del panel: barra superior, menú lateral, paginación y páginas
 *              del docente y del superadministrador (Manual COD-WEB, sección 6.18).
 * Autor: Leonardo Ballestero López
 * Creado: 27/10/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Textos del panel sin plan y de errores
 */

declare(strict_types=1);

return [
    'nombre_sistema' => 'Sistema Aplicador / Evaluador de Exámenes',
    'saltar_al_contenido' => 'Saltar al contenido',

    'logo' => [
        'alt' => 'Logotipo de BigBitByte: un cubo con tres letras B',
    ],

    'barra_superior' => [
        'abrir_menu' => 'Abrir menú',
        'ir_al_panel' => 'Ir al panel',
        'menu_de_cuenta' => 'Menú de cuenta',
        'cuenta' => 'Cuenta',
    ],

    'menu' => [
        'etiqueta' => 'Menú principal',
        'pagina_actual' => '(página actual)',
        'panel' => 'Panel',
        'grupos' => 'Grupos',
        'banco_preguntas' => 'Banco de preguntas',
        'examenes' => 'Exámenes',
        'resultados' => 'Resultados',
        'estadisticas' => 'Estadísticas',
        'suscripcion' => 'Suscripción',
        'docentes' => 'Docentes',
        'pagos' => 'Pagos y suscripciones',
    ],

    'roles' => [
        'docente' => 'Docente',
        'super_admin' => 'Superadministrador',
    ],

    'seccion_pendiente' => [
        'encabezado' => 'Sección en construcción',
        'mensaje' => 'Aquí aparecerá el módulo «:seccion» en una próxima entrega.',
    ],

    'inicio' => [
        'titulo' => 'Panel',
        'descripcion' => 'Resumen de tu plan y de tus exámenes.',
        'resumen_del_plan' => 'Resumen del plan',
        'plan_actual' => 'Plan actual: :plan',
        'uso' => ':usado de :limite',
        'porcentaje_usado' => ':valor % usado',
        'etiqueta_del_uso' => 'Uso de :recurso',
        'estado_alcanzado' => 'Límite alcanzado',
        'estado_cercano' => 'Cerca del límite',
        'estado_normal' => 'Dentro del límite',
        'examenes' => 'Exámenes',
        'sin_plan_encabezado' => 'Sin plan activo',
        'sin_plan_mensaje' => 'Mientras no tengas un plan activo no puedes crear grupos, preguntas ni exámenes.',
        'sin_examenes' => 'Aún no tienes exámenes. Crea el primero con el botón Nuevo.',
    ],

    'error' => [
        'reintentar' => 'Vuelve a intentarlo con el botón Reintentar.',
    ],

    'inicio_administracion' => [
        'titulo' => 'Panel de administración',
        'descripcion' => 'Resumen de docentes, suspensiones y pagos.',
        'resumen' => 'Resumen de la plataforma',
        'docentes' => 'Docentes',
        'docentes_registrados' => 'Docentes registrados',
    ],

    'recursos' => [
        'grupos' => 'Grupos',
        'preguntas' => 'Preguntas',
        'alumnos' => 'Alumnos',
    ],

    'resumen_administracion' => [
        'activos' => 'Docentes activos',
        'suspendidos' => 'Docentes suspendidos',
        'pagos_pendientes' => 'Pagos pendientes',
    ],

    'estados' => [
        'borrador' => 'Borrador',
        'publicado' => 'Publicado',
        'cerrado' => 'Cerrado',
        'activo' => 'Activo',
        'suspendido' => 'Suspendido',
    ],

    'tabla_examenes' => [
        'titulo' => 'Lista de exámenes',
        'columnas' => [
            'examen' => 'Examen',
            'grupo' => 'Grupo',
            'estado' => 'Estado',
            'fecha' => 'Fecha de aplicación',
            'acciones' => 'Acciones',
        ],
    ],

    'tabla_docentes' => [
        'titulo' => 'Lista de docentes',
        'columnas' => [
            'docente' => 'Docente',
            'correo' => 'Correo electrónico',
            'plan' => 'Plan',
            'estado' => 'Estado',
            'acciones' => 'Acciones',
        ],
    ],

    'paginacion' => [
        'etiqueta' => 'Paginación',
        'resumen' => 'Mostrando :desde a :hasta de :total',
        'anterior' => 'Anterior',
        'siguiente' => 'Siguiente',
        'pagina' => 'Ir a la página :numero',
    ],

    'qr' => [
        'descripcion' => 'Código QR del grupo con folio :folio',
    ],
];
