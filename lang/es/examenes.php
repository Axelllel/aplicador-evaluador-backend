<?php

/**
 * Archivo: examenes.php
 * Módulo: lang/es
 * Descripción: Textos de las páginas de exámenes y de apartados: formulario de alta, opciones de configuración
 *              y avance de la ponderación (Manual COD-WEB, secciones 6.17 y 6.18).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

return [
    'titulo' => 'Exámenes',
    'descripcion' => 'Crea exámenes en borrador, arma sus apartados y publícalos cuando estén completos.',

    'nuevo' => [
        'encabezado' => 'Nuevo examen',
        'grupo' => 'Grupo',
        'grupo_vacio' => 'Elige un grupo',
        'sin_grupos' => 'Necesitas al menos un grupo activo para crear un examen.',
        'ir_a_grupos' => 'Ir a Grupos',
        'nombre' => 'Nombre del examen',
        'fecha_apertura' => 'Fecha de apertura',
        'fecha_cierre' => 'Fecha de cierre',
        'orden' => 'Orden de las preguntas',
        'escala' => 'Escala de calificación',
        'plataforma' => 'Plataforma permitida',
        'resultado_visible' => 'Resultado visible para el alumno',
        'ayuda_fechas' => 'El examen solo estará disponible entre la apertura y el cierre.',
    ],

    'opciones' => [
        'orden' => [
            'aleatorio' => 'Aleatorio',
            'fijo' => 'Fijo',
        ],
        'escala' => [
            '0-10' => 'De 0 a 10',
            '0-100' => 'De 0 a 100',
        ],
        'plataforma' => [
            'web' => 'Web',
            'movil' => 'Móvil',
            'ambas' => 'Web y móvil',
        ],
        'resultado_visible' => [
            'oculto' => 'Oculto',
            'aproximado' => 'Aproximado',
            'final' => 'Final',
        ],
    ],

    'apartados' => [
        'titulo' => 'Apartados: :examen',
        'descripcion' => 'Grupo :grupo · Apertura :apertura · Cierre :cierre',
        'volver' => 'Volver a Exámenes',
        'publicado_aviso' => 'Este examen ya está publicado y no se puede modificar.',
        'ponderacion' => 'Ponderación asignada',
        'ponderacion_valor' => ':total de 100 %',
        'ponderacion_ayuda' => 'Para publicar, las ponderaciones de todas las preguntas deben sumar exactamente 100 %.',
        'editar_ponderacion' => 'Ponderación de :pregunta en porcentaje',
        'guardar_ponderacion' => 'Guardar ponderación',
        'lista' => 'Apartados del examen',
        'sin_apartados' => 'Este examen aún no tiene apartados. Agrega el primero con el formulario de abajo.',
        'sin_preguntas' => 'Sin preguntas asignadas.',
        'avance' => ':usadas de :cantidad preguntas',
        'tiempo' => ':segundos s',
        'tabla' => [
            'titulo' => 'Preguntas del apartado',
            'columnas' => [
                'pregunta' => 'Pregunta',
                'tiempo' => 'Tiempo',
                'ponderacion' => 'Ponderación',
            ],
        ],
        'nuevo' => [
            'encabezado' => 'Nuevo apartado',
            'tipo' => 'Tipo de pregunta',
            'tipo_vacio' => 'Elige un tipo',
            'cantidad' => 'Cantidad de preguntas',
        ],
        'asignar' => [
            'encabezado' => 'Agregar una pregunta',
            'apartado' => 'Apartado',
            'apartado_vacio' => 'Elige un apartado',
            'pregunta' => 'Pregunta del banco',
            'pregunta_vacia' => 'Elige una pregunta',
            'tiempo' => 'Tiempo en segundos',
            'ponderacion' => 'Ponderación (%)',
            'ayuda' => 'La pregunta debe ser del mismo tipo que el apartado.',
            'sin_espacio' => 'Todos los apartados ya tienen sus preguntas, o aún no hay apartados.',
            'sin_preguntas' => 'No hay preguntas disponibles en tu banco. Agrégalas en Banco de preguntas.',
        ],
        'opcion_apartado' => ':tipo · :usadas de :cantidad',
        'opcion_pregunta' => '[:tipo] :enunciado',
    ],
];
