<?php

/**
 * Archivo: sesion.php
 * Módulo: lang/es
 * Descripción: Textos de las pantallas de inicio de sesión y de sesión vencida (Manual COD-WEB, sección 6.18).
 * Autor: Leonardo Ballestero López
 * Creado: 27/10/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Textos de la autenticación real
 */

declare(strict_types=1);

return [
    'titulo' => 'Iniciar sesión',

    'campos' => [
        'email' => 'Correo electrónico',
        'password' => 'Contraseña',
    ],

    'credenciales_invalidas' => 'El correo o la contraseña no son correctos, o tu cuenta aún no está habilitada.',
    'cuenta_no_registrada' => 'Tu cuenta no está registrada como docente. Crea una cuenta o contacta a soporte.',
    'vencida' => 'Tu sesión venció. Inicia sesión de nuevo para continuar.',

    'sin_cuenta' => '¿Aún no tienes cuenta?',
    'crear_cuenta' => 'Crear una cuenta',

    // Solo se muestra con APP_ENV=local.
    'demostracion' => [
        'ver_guia_de_estilo' => 'Ver la guía de estilo',
    ],
];
