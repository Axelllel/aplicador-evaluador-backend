<?php

/**
 * Archivo: preguntas.php
 * Módulo: lang/es
 * Descripción: Textos del banco de preguntas: tipos, formulario de alta, tabla y validaciones propias
 *              (Manual COD-WEB, secciones 6.17 y 6.18).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

return [
    'titulo' => 'Banco de preguntas',
    'descripcion' => 'Guarda tus preguntas para reutilizarlas en tus exámenes.',

    'tipos' => [
        'opcion_multiple' => 'Opción múltiple',
        'relacion_columnas' => 'Relación de columnas',
        'abierta' => 'Abierta',
        'programacion' => 'Programación',
    ],

    'nueva' => [
        'encabezado' => 'Nueva pregunta',
        'tipo' => 'Tipo de pregunta',
        'tipo_vacio' => 'Elige un tipo',
        'enunciado' => 'Enunciado',
        'opciones' => 'Opciones',
        'ayuda_opciones' => 'Solo para opción múltiple y relación de columnas. Opción múltiple: una opción por línea. Relación de columnas: un par por línea, con el formato izquierda | derecha.',
        'lenguaje' => 'Lenguaje de programación',
        'lenguaje_vacio' => 'Elige un lenguaje',
        'lenguaje_otro' => 'Otro lenguaje',
        'lenguaje_nombre' => 'Nombre del lenguaje',
        'lenguaje_otro_ayuda' => 'Escribe el nombre del lenguaje (máximo 40 caracteres).',
        'lenguaje_libre' => 'Libre (el alumno elige el lenguaje)',
        'lenguaje_ayuda' => 'El alumno verá en qué lenguaje debe responder.',
        'respuesta_modelo' => 'Respuesta modelo',
        'ayuda_respuesta' => 'Opción múltiple: escribe el texto exacto de la opción correcta. Relación de columnas: opcional, se arma con los pares. Abierta y programación: la respuesta o el código de referencia.',
    ],

    'editar' => [
        'titulo' => 'Editar pregunta',
        'descripcion' => 'Modifica la pregunta; solo es posible mientras no forme parte de un examen.',
        'volver' => 'Volver al banco',
        'guardar' => 'Guardar cambios',
        'en_uso' => 'En uso en un examen',
    ],

    'confirmar_eliminar' => [
        'titulo' => 'Eliminar pregunta',
        'mensaje' => '¿Eliminar la pregunta #:id? Esta acción no se puede deshacer.',
    ],

    'filtro' => [
        'etiqueta' => 'Filtrar por tipo',
        'todos' => 'Todos los tipos',
    ],

    'tabla' => [
        'titulo' => 'Lista de preguntas',
        'vacia' => 'No hay preguntas con ese filtro. Agrega una con el formulario de arriba.',
        'columnas' => [
            'enunciado' => 'Enunciado',
            'tipo' => 'Tipo',
            'opciones' => 'Opciones',
        ],
    ],

    'validacion' => [
        'opciones_cantidad' => 'Escribe entre 2 y :maximo opciones, una por línea.',
        'respuesta_en_opciones' => 'La respuesta modelo debe ser el texto exacto de una de las opciones.',
        'pares_invalidos' => 'Escribe entre 2 y :maximo pares, uno por línea, con el formato izquierda | derecha.',
        'opciones_repetidas' => 'Usa opciones distintas: dos opciones no pueden ser iguales aunque cambien las mayúsculas.',
        'pares_repetidos' => 'Cada concepto debe aparecer una sola vez; hay parejas repetidas.',
        'enunciado_repetido' => 'Ya tienes una pregunta de este tipo con el mismo enunciado.',
        'enunciado_sin_contenido' => 'El enunciado debe tener texto legible y no puede ser solo signos ni caracteres invisibles.',
        'en_uso' => 'Esta pregunta ya forma parte de un examen y no se puede modificar ni eliminar.',
        'opcion_vacia' => 'Completa la opción :posicion.',
        'opcion_larga' => 'La opción :posicion es demasiado larga.',
        'concepto_vacio' => 'Completa el concepto de la pareja :posicion.',
        'pareja_vacia' => 'Completa la correspondencia de la pareja :posicion.',
        'respuesta_requerida' => 'Escribe la respuesta modelo.',
        'lenguaje_requerido' => 'Elige el lenguaje de la pregunta.',
        'lenguaje_otro_requerido' => 'Escribe el nombre del lenguaje.',
        'lenguaje_otro_invalido' => 'El nombre del lenguaje solo puede llevar letras, números y los signos . + # -',
    ],
];
