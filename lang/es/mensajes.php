<?php

/**
 * Archivo: mensajes.php
 * Módulo: lang/es
 * Descripción: Mensajes de éxito, error, advertencia, información y confirmación
 *              (Manual COD-WEB, sección 6.18, Tabla 16).
 * Autor: Leonardo Ballestero López
 * Creado: 27/10/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Mensajes de calificación y autocalificación
 */

declare(strict_types=1);

return [
    'tipos' => [
        'exito' => 'Éxito',
        'error' => 'Error',
        'aviso' => 'Advertencia',
        'info' => 'Información',
    ],

    'exito' => [
        'grupo_creado' => 'Grupo creado correctamente.',
        'sesion_cerrada' => 'Sesión cerrada correctamente.',
        'folio_copiado' => 'Folio copiado correctamente.',
        'pregunta_creada' => 'Pregunta agregada al banco correctamente.',
        'examen_creado' => 'Examen creado como borrador. Agrega sus apartados y preguntas para publicarlo.',
        'examen_publicado' => 'Examen publicado correctamente.',
        'examen_eliminado' => 'Examen eliminado correctamente.',
        'apartado_creado' => 'Apartado agregado correctamente.',
        'pregunta_asignada' => 'Pregunta agregada al examen correctamente.',
        'ponderacion_guardada' => 'Ponderación actualizada correctamente.',
        'calificacion_guardada' => 'Calificación guardada correctamente.',
        'autocalificado' => '{0} No había respuestas de opción múltiple por calificar.|{1} Se calificó 1 respuesta de opción múltiple correctamente.|[2,*] Se calificaron :cantidad respuestas de opción múltiple correctamente.',
    ],

    'error' => [
        'examen_no_guardado' => 'No se pudo guardar el examen. Revisa los datos e inténtalo de nuevo.',
        'examen_no_publicado' => 'No se pudo publicar el examen. Revisa que esté completo e inténtalo de nuevo.',
        'examen_no_eliminado' => 'No se pudo eliminar el examen.',
        'pregunta_no_guardada' => 'No se pudo guardar la pregunta. Revisa los datos e inténtalo de nuevo.',
        'apartado_no_guardado' => 'No se pudo guardar el apartado. Revisa los datos e inténtalo de nuevo.',
        'pregunta_no_asignada' => 'No se pudo agregar la pregunta al examen. Revisa los datos e inténtalo de nuevo.',
        'ponderacion_no_guardada' => 'No se pudo actualizar la ponderación.',
        'calificacion_no_guardada' => 'No se pudo guardar la calificación. Revisa los datos e inténtalo de nuevo.',
        'autocalificacion_fallida' => 'No se pudo autocalificar el intento. Inténtalo de nuevo.',
        'servicio' => 'No pudimos completar la acción. Inténtalo de nuevo en unos minutos.',
    ],

    'aviso' => [
        'limite_grupos' => 'Alcanzaste el límite de grupos de tu plan. Actualiza tu suscripción para crear más.',
        'limite_preguntas' => 'Alcanzaste el límite de preguntas de tu plan. Actualiza tu suscripción para crear más.',
        'limite_alumnos' => 'Alcanzaste el límite de alumnos en al menos un grupo. Actualiza tu suscripción para ampliar su cupo.',
        'limite_generico' => 'Alcanzaste el límite de tu plan. Actualiza tu suscripción para continuar.',
        'sin_plan' => 'Tu cuenta no tiene un plan activo o tu pago ya venció. Revisa tu suscripción para continuar.',
        'demasiados_intentos' => 'Hiciste demasiados intentos seguidos. Espera un minuto e inténtalo de nuevo.',
    ],

    'info' => [
        'resultados_pendientes' => 'Los resultados se actualizarán cuando termine el examen.',
    ],

    'confirmar' => [
        'eliminar_grupo' => [
            'titulo' => 'Eliminar grupo',
            'mensaje' => '¿Eliminar este grupo? Esta acción no se puede deshacer.',
        ],
        'eliminar_examen' => [
            'titulo' => 'Eliminar examen',
            'mensaje' => '¿Eliminar este examen? Esta acción no se puede deshacer.',
            'mensaje_con_nombre' => '¿Eliminar «:examen»? Se borrarán el borrador y sus apartados.',
        ],
        'publicar_examen' => [
            'titulo' => 'Publicar examen',
            'mensaje' => '¿Publicar este examen? Los alumnos podrán presentarlo con el folio del grupo.',
        ],
    ],
];
