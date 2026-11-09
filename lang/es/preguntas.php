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
        'respuesta_modelo' => 'Respuesta modelo',
        'ayuda_respuesta' => 'Opción múltiple: escribe el texto exacto de la opción correcta. Relación de columnas: opcional, se arma con los pares. Abierta y programación: la respuesta o el código de referencia.',
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
    ],
];
