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
    'exportar_excel' => 'Descargar Excel',
    'exportar_pdf' => 'Descargar PDF',
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
        'sin_responder' => '{1} El alumno no respondió 1 pregunta; cuenta como cero en la nota final.|[2,*] El alumno no respondió :cantidad preguntas; cuentan como cero en la nota final.',
        'no_calificable' => 'Podrás calificar este intento cuando el alumno lo entregue.',
        'autocalificar' => 'Calificar preguntas objetivas',
        'autocalificar_ayuda' => 'Compara las opciones múltiples y calcula el porcentaje de pares correctos en relación de columnas. Las propuestas de IA quedan para tu revisión.',
        'autocalificar_sin_pendientes' => 'No hay preguntas objetivas pendientes de calificar.',
        'revision_columnas' => 'Revisión de relación de columnas',
        'elemento' => 'Elemento',
        'relacion_correcta' => 'Relación correcta',
        'formato_historico' => 'Esta respuesta usa un formato anterior que requiere revisión manual.',
        'ver_original' => 'Ver respuesta original',
        'referencia_manual' => 'Referencia histórica: revisar manualmente',
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
            'motivo_ayuda_cambio' => 'Solo es obligatorio si cambias una calificación ya revisada por el docente. Queda en el historial.',
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
            'rostro_no_detectado' => 'Rostro no detectado',
            'multiples_rostros' => 'Varios rostros detectados',
            'camara_desactivada' => 'Cámara desactivada',
            'movimiento' => 'Movimiento detectado',
        ],
        'severidades' => [
            'baja' => 'Baja',
            'media' => 'Media',
            'alta' => 'Alta',
        ],
    ],

    'revision' => [
        'titulo' => 'Revisar y guardar',
        'ayuda' => 'Revisa las calificaciones y guárdalas juntas. El motivo solo se pide al corregir una nota ya revisada por ti.',
        'guardar' => 'Guardar toda la revisión',
        'guardar_una' => 'Guardar solo esta pregunta',
        'guardada' => 'La revisión quedó guardada correctamente.',
        'sin_cambios' => 'No hay calificaciones nuevas ni cambios que guardar.',
    ],

    'errores' => [
        'motivo_obligatorio' => 'Escribe el motivo del cambio, porque esta calificación ya fue revisada por el docente.',
    ],
];
