<?php

/**
 * Archivo: resultados.php
 * Módulo: lang/es
 * Descripción: Textos de las páginas de resultados: selector de examen, tabla de intentos, detalle de un intento,
 *              calificación manual, autocalificación e incidencias (Manual COD-WEB, secciones 6.17 y 6.18).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

return [
    'titulo' => 'Resultados',
    'descripcion' => 'Revisa los intentos de tus alumnos y califica sus respuestas.',
    'opcion_examen' => ':examen · :grupo',
    'sin_dato' => '—',

    'vacio' => 'Aún no hay resultados para mostrar.',
    'vacio_sin_examenes' => 'Los resultados aparecerán cuando publiques un examen y tus alumnos lo entreguen.',
    'vacio_sin_intentos' => 'Todavía ningún alumno ha presentado este examen.',
    'ir_a_examenes' => 'Ir a Exámenes',

    'selector' => [
        'encabezado' => 'Elige un examen',
        'examen' => 'Examen',
        'ver' => 'Ver resultados',
    ],

    'intentos' => [
        'encabezado' => 'Intentos de :examen',
    ],

    'resumen' => [
        'etiqueta' => 'Resumen de intentos',
        'intentos' => 'Intentos: :cantidad',
        'calificados' => 'Calificados: :cantidad',
        'pendientes' => 'Pendientes de calificar: :cantidad',
    ],

    'tabla' => [
        'titulo' => 'Intentos de los alumnos',
        'columnas' => [
            'alumno' => 'Alumno',
            'estado' => 'Estado',
            'entrega' => 'Fecha de entrega',
            'nota' => 'Nota',
            'acciones' => 'Acciones',
        ],
    ],

    'estados' => [
        'en_progreso' => 'En progreso',
        'finalizado' => 'Finalizado',
        'anulado' => 'Anulado',
    ],

    'nota' => [
        'valor' => ':nota de :maximo',
        'pendiente' => 'Pendiente de calificar',
        'sin_entregar' => 'Sin entregar',
    ],

    'intento' => [
        'titulo' => 'Intento de :alumno',
        'descripcion' => 'Examen :examen · Intento :numero',
        'volver' => 'Volver a Resultados',
        'resumen' => 'Resumen del intento',
        'nota_actual' => 'Nota actual',
        'estado' => 'Estado',
        'entrega' => 'Fecha de entrega',
        'escala' => 'Escala de calificación',
        'avance' => ':calificadas de :total preguntas calificadas.',
        'sin_responder' => '{1} El alumno no respondió 1 pregunta. No se puede calificar, así que la nota final seguirá pendiente.|[2,*] El alumno no respondió :cantidad preguntas. No se pueden calificar, así que la nota final seguirá pendiente.',
        'no_calificable' => 'Podrás calificar este intento cuando el alumno lo entregue.',
        'autocalificar' => 'Autocalificar opción múltiple',
        'autocalificar_ayuda' => 'Compara cada respuesta de opción múltiple con la respuesta modelo. Las preguntas abiertas se califican a mano.',
        'autocalificar_sin_pendientes' => 'No hay respuestas de opción múltiple sin calificar.',
        'respuestas' => 'Respuestas del alumno',
        'sin_respuestas' => 'El alumno no registró respuestas en este intento.',
        'pregunta' => 'Pregunta :numero',
        'respuesta_alumno' => 'Respuesta del alumno',
        'sin_respuesta' => 'Sin respuesta.',
        'respuesta_modelo' => 'Respuesta modelo (solo visible para ti)',
        'ponderacion' => 'Ponderación: :valor %',
        'calificacion_actual' => 'Calificación actual: :valor de 100',
        'calificacion_ninguna' => 'Sin calificar',
        'aporte' => 'Aporta :valor de :maximo a la nota final',
        'origen' => [
            'manual' => 'Calificada a mano',
            'automatica' => 'Calificada automáticamente',
            'sin_calificar' => 'Sin calificar',
        ],
        'form' => [
            'calificacion' => 'Calificación de la pregunta (0 a 100)',
            'calificacion_ayuda' => 'Porcentaje de acierto de esta pregunta; la nota final usa su ponderación.',
            'motivo' => 'Motivo (opcional)',
            'motivo_obligatorio' => 'Motivo del cambio',
            'motivo_ayuda_nueva' => 'Puedes explicar por qué asignas esta calificación.',
            'motivo_ayuda_cambio' => 'Obligatorio porque la pregunta ya tiene calificación. Queda en el historial.',
            'guardar_pregunta' => 'Guardar calificación de la pregunta :numero',
        ],
    ],

    'incidencias' => [
        'encabezado' => 'Incidencias del intento',
        'vacia' => 'Este intento no tiene incidencias.',
        'tabla' => [
            'titulo' => 'Incidencias registradas durante el intento',
            'columnas' => [
                'tipo' => 'Tipo',
                'severidad' => 'Severidad',
                'fecha' => 'Fecha',
            ],
        ],
        'tipos' => [
            'cambio_pestana' => 'Cambio de pestaña',
            'salida_pantalla' => 'Salida de pantalla',
            'conexion' => 'Problema de conexión',
            'otro' => 'Otra incidencia',
        ],
        'severidades' => [
            'baja' => 'Baja',
            'media' => 'Media',
            'alta' => 'Alta',
        ],
    ],

    'errores' => [
        'motivo_obligatorio' => 'Escribe el motivo del cambio, porque la pregunta ya tiene calificación.',
    ],
];
