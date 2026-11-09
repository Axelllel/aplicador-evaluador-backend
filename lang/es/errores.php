<?php

/**
 * Archivo: errores.php
 * Módulo: lang/es
 * Descripción: Textos de las páginas de error, sin códigos ni detalles técnicos
 *              (Manual COD-WEB, sección 6.18).
 * Autor: Leonardo Ballestero López
 * Creado: 28/10/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Páginas de servicio no disponible, sesión expirada y error general
 */

declare(strict_types=1);

return [
    'no_encontrada' => [
        'titulo' => 'No encontramos la página',
        'mensaje' => 'La dirección que abriste no existe. Revisa el enlace o vuelve al inicio.',
        'volver' => 'Volver al inicio',
    ],

    'servicio' => [
        'titulo' => 'No pudimos cargar la página',
        'mensaje' => 'Ocurrió un problema al consultar tus datos. Inténtalo de nuevo en unos minutos.',
    ],

    'pagina_expirada' => [
        'titulo' => 'La página expiró',
        'mensaje' => 'Tardaste demasiado en enviar el formulario. Vuelve a cargar la página e inténtalo de nuevo.',
    ],

    'general' => [
        'titulo' => 'Algo salió mal',
        'mensaje' => 'No pudimos completar la solicitud. Inténtalo de nuevo en unos minutos.',
    ],
];
