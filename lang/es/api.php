<?php

/**
 * Archivo: api.php
 * Módulo: lang/es
 * Descripción: Traduce los mensajes de negocio que devuelve la API (409 y 422 sin campo) a textos claros,
 *              con acentos y sin detalles técnicos (Manual COD-WEB, sección 6.18).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

return [
    // La clave es el texto exacto que responde la API; solo se muestran los que aparecen aquí.
    'mensajes' => [
        'Grupo no disponible.' => 'El grupo elegido no está disponible. Elige otro grupo.',
        'Examen no disponible.' => 'No encontramos ese examen.',
        'Apartado no disponible.' => 'No encontramos ese apartado.',
        'Pregunta no disponible.' => 'No encontramos esa pregunta en tu banco.',
        'Se necesitan al menos dos opciones.' => 'Una pregunta de opción múltiple necesita al menos dos opciones.',
        'No se puede modificar un examen publicado.' => 'Este examen ya está publicado y no se puede modificar.',
        'Grupo o suscripcion no disponible.' => 'El grupo está inactivo o tu suscripción no está vigente.',
        'El tipo de pregunta no coincide con el apartado.' => 'El tipo de la pregunta no coincide con el del apartado.',
        'El apartado ya tiene la cantidad configurada.' => 'Ese apartado ya tiene todas sus preguntas.',
        'La pregunta ya pertenece a este examen.' => 'Esa pregunta ya está en el examen.',
        'El grupo esta inactivo.' => 'El grupo de este examen está inactivo.',
        'Suscripcion no vigente.' => 'Tu suscripción no está vigente. Revisa tu plan para publicar.',
        'No se puede publicar un examen cuya fecha de cierre ya paso.' => 'La fecha de cierre del examen ya pasó. Crea un examen con fechas nuevas.',
        'El examen necesita al menos un apartado.' => 'El examen necesita al menos un apartado antes de publicarse.',
        'Hay preguntas ajenas o de un tipo incorrecto.' => 'Hay preguntas que no coinciden con el tipo de su apartado.',
        'Hay preguntas incompletas o con tiempo o ponderacion invalidos.' => 'Hay preguntas incompletas o con tiempo o ponderación no válidos.',
        'Hay preguntas sin respuesta modelo de referencia.' => 'Hay preguntas sin respuesta modelo. Crea esas preguntas de nuevo con su respuesta modelo.',
        'Una pregunta de opcion multiple no tiene suficientes opciones.' => 'Una pregunta de opción múltiple no tiene suficientes opciones.',
        'Una pregunta aparece mas de una vez en el examen.' => 'Una pregunta aparece más de una vez en el examen.',
        'La ponderacion total de las preguntas debe sumar 100.00%.' => 'La ponderación total de las preguntas debe sumar 100 %.',
        'Intento no disponible.' => 'No encontramos ese intento.',
        'El intento no se puede calificar.' => 'Este intento todavía no se puede calificar. Debe estar entregado.',
        'Respuesta no disponible o correccion invalida.' => 'No se pudo guardar la calificación. Revisa que el intento esté entregado y que, si ya tenía nota, escribas el motivo del cambio.',
        'No se pudo completar la publicacion.' => 'No se pudo completar la publicación. Inténtalo de nuevo.',
    ],

    'apartado_incompleto' => 'Un apartado todavía no tiene todas las preguntas que configuraste.',
];
