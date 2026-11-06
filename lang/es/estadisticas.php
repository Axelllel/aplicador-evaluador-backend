<?php

/**
 * Archivo: estadisticas.php
 * Módulo: lang/es
 * Descripción: Textos de la página de estadísticas y de las gráficas de barras: resumen, tablas, títulos de las
 *              gráficas, ejes, leyenda y fuente de los datos (Manual COD-WEB, sección 8.3; Manual de Calidad, 3.10).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

return [
    'titulo' => 'Estadísticas',
    'descripcion' => 'Promedios, aprobación y preguntas con más errores, calculados con los resultados de tus exámenes.',
    'vacio' => 'Aún no hay resultados para mostrar.',
    'vacio_ayuda' => 'Las estadísticas aparecerán cuando tus alumnos entreguen sus exámenes y tengan todas sus preguntas calificadas.',
    'porcentaje' => ':valor %',
    'fuente' => 'Resultados de los exámenes publicados (solo intentos con todas sus preguntas calificadas)',
    'fuente_respuestas' => 'Respuestas calificadas de los intentos entregados del examen',
    'eje_porcentaje' => 'Porcentaje (%)',
    'eje_alumnos' => 'Alumnos (cantidad)',

    'series' => [
        'promedio' => 'Promedio (% de la escala)',
        'aprobacion' => 'Aprobación (%)',
        'alumnos' => 'Alumnos',
        'error' => 'Error promedio (%)',
    ],

    'grafica' => [
        'leyenda' => 'Leyenda de la gráfica',
        'fuente' => 'Fuente: :fuente.',
    ],

    'resumen' => [
        'encabezado' => 'Resumen general',
        'intentos' => 'Intentos registrados',
        'pendientes' => '{1} :cantidad pendiente de calificar|[0,*] :cantidad pendientes de calificar',
        'calificados' => 'Intentos calificados',
        'promedio' => 'Promedio (% de la escala)',
        'aprobacion' => 'Aprobación',
        'nota' => 'El promedio y la aprobación solo cuentan intentos calificados por completo. Se aprueba con 60 % de la escala o más.',
    ],

    'examenes' => [
        'encabezado' => 'Por examen',
        'grafica' => 'Promedio y aprobación por examen',
        'grafica_ayuda' => 'Muestra hasta los 10 exámenes más recientes con intentos calificados.',
        'eje_x' => 'Examen',
        'tabla' => [
            'titulo' => 'Estadísticas por examen',
            'columnas' => [
                'examen' => 'Examen',
                'grupo' => 'Grupo',
                'escala' => 'Escala',
                'intentos' => 'Intentos',
                'calificados' => 'Calificados',
                'promedio' => 'Promedio',
                'minimo' => 'Mínimo',
                'maximo' => 'Máximo',
                'aprobacion' => 'Aprobación',
            ],
        ],
    ],

    'grupos' => [
        'encabezado' => 'Por grupo',
        'grafica' => 'Promedio y aprobación por grupo',
        'grafica_ayuda' => 'Junta los exámenes de cada grupo, con cada nota como porcentaje de su escala.',
        'eje_x' => 'Grupo',
        'tabla' => [
            'titulo' => 'Estadísticas por grupo',
            'columnas' => [
                'grupo' => 'Grupo',
                'examenes' => 'Exámenes',
                'intentos' => 'Intentos',
                'calificados' => 'Calificados',
                'promedio' => 'Promedio',
                'minimo' => 'Mínimo',
                'maximo' => 'Máximo',
                'aprobacion' => 'Aprobación',
            ],
        ],
    ],

    'detalle' => [
        'encabezado' => 'Detalle de un examen',
        'examen' => 'Examen',
        'ver' => 'Ver detalle',
        'sin_notas' => 'Este examen todavía no tiene intentos calificados por completo.',
        'sin_respuestas' => 'Todavía no hay respuestas calificadas para mostrar errores por pregunta.',
        'distribucion' => 'Distribución de notas: :examen',
        'distribucion_ayuda' => 'Cada rango incluye su límite inferior; el último incluye también la nota máxima.',
        'eje_rangos' => 'Rango de nota (:escala)',
        'errores' => 'Preguntas con mayor error: :examen',
        'errores_ayuda' => 'El error de una pregunta es 100 menos el promedio de calificación de sus respuestas.',
        'eje_preguntas' => 'Pregunta',
        'tabla' => [
            'titulo' => 'Preguntas con mayor error',
            'columnas' => [
                'clave' => 'Clave',
                'pregunta' => 'Pregunta',
                'tipo' => 'Tipo',
                'respuestas' => 'Respuestas calificadas',
                'reprobadas' => 'Con menos de 60',
                'error' => 'Error promedio',
            ],
        ],
    ],
];
