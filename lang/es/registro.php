<?php

/**
 * Archivo: registro.php
 * Módulo: lang/es
 * Descripción: Textos de la pantalla de registro de docentes (Manual COD-WEB, sección 6.18).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

return [
    'titulo' => 'Crear cuenta',
    'descripcion' => 'Tu cuenta quedará inactiva hasta que se te asigne un plan.',

    'campos' => [
        'nombre' => 'Nombre completo',
        'email' => 'Correo electrónico',
        'password' => 'Contraseña',
        'telefono' => 'Teléfono',
    ],

    'ayudas' => [
        'password' => 'Usa 10 caracteres o más.',
        'telefono' => 'Opcional.',
    ],

    'ya_tengo_cuenta' => '¿Ya tienes una cuenta?',
    'ir_a_iniciar_sesion' => 'Iniciar sesión',

    'creada' => 'Cuenta creada correctamente.',
    'sin_plan' => 'Tu cuenta está inactiva hasta que se te asigne un plan. Podrás iniciar sesión, pero no crear grupos, preguntas ni exámenes hasta entonces.',
    'pendiente' => 'Recibimos tu registro. Si no puedes iniciar sesión en unos minutos, revisa tu correo o contacta a soporte.',
    'no_posible' => 'No pudimos crear tu cuenta. Revisa los datos; si ya tienes una cuenta, inicia sesión.',
];
