<?php

/**
 * Archivo: grupos.php
 * Módulo: lang/es
 * Descripción: Textos de la página de grupos: formulario de nuevo grupo, folio y código QR
 *              (Manual COD-WEB, secciones 6.17 y 6.18).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

return [
    'eliminacion' => [
        'acciones' => 'Acciones',
        'eliminar_grupo' => 'Eliminar grupo',
        'eliminar_alumno' => 'Eliminar del grupo',
        'eliminar_nombre' => 'Eliminar a :nombre de este grupo',
        'titulo_grupo' => 'Eliminar grupo definitivamente',
        'titulo_alumno' => 'Eliminar alumno de este grupo',
        'grupo_aviso' => 'Se eliminarán el grupo, sus inscripciones, exámenes, intentos, respuestas, incidencias y calificaciones, incluido el historial de revisión. Esta acción no se puede deshacer.',
        'alumno_aviso' => 'Se eliminarán su inscripción, intentos, respuestas, incidencias y calificaciones de este grupo, incluido el historial de revisión. Esta acción no se puede deshacer.',
        'identidad' => 'El perfil, el PIN y los datos del alumno en otros grupos se conservan.',
        'banco' => 'Tu banco de preguntas y tus pagos se conservan. El grupo dejará de ocupar capacidad de tu plan.',
        'en_curso' => 'Hay un examen en curso. Podrás eliminar cuando los intentos hayan terminado.',
        'confirmacion' => 'Escribe ELIMINAR para confirmar',
        'confirmacion_ayuda' => 'Comprueba el nombre y los datos que se eliminarán antes de confirmar.',
        'cancelar' => 'Cancelar',
        'confirmar_grupo' => 'Eliminar grupo definitivamente',
        'confirmar_alumno' => 'Eliminar alumno de este grupo',
        'grupo_eliminado' => 'El grupo y sus datos asociados se eliminaron correctamente.',
        'alumno_eliminado' => 'El alumno y su historial de este grupo se eliminaron correctamente.',
        'alumnos' => 'Alumnos inscritos', 'examenes' => 'Exámenes', 'intentos' => 'Intentos registrados', 'evaluaciones' => 'Evaluaciones',
    ],
    'titulo' => 'Grupos',
    'descripcion' => 'Crea grupos y comparte su folio o su código QR con tus alumnos.',

    'nuevo' => [
        'encabezado' => 'Nuevo grupo',
        'materia' => 'Materia',
        'ayuda' => 'El folio se genera automáticamente.',
    ],

    'lista' => [
        'encabezado' => 'Tus grupos',
        'vacia' => 'Aún no tienes grupos. Crea el primero con el formulario de arriba.',
        'folio' => 'Folio',
        'activo' => 'Activo',
        'inactivo' => 'Inactivo',
    ],
];
