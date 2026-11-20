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
    'encabezado' => 'TU ESPACIO PARA ENSEÑAR',
    'subtitulo' => 'Un solo lugar para tus grupos, preguntas y evaluaciones.',
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
    'confirmacion' => [
        'titulo' => 'Confirma tu correo electrónico',
        'solicitado' => 'Se solicitó el correo de confirmación. Revisa tu bandeja de entrada y la carpeta de spam.',
        'pendiente' => 'Tu registro fue recibido y requiere confirmar el correo. Revisa tu bandeja de entrada; el servicio no confirmó todavía el envío.',
        'paso_uno' => 'Abre el mensaje de confirmación de tu cuenta.',
        'paso_dos' => 'Presiona el enlace para verificar tu correo.',
        'paso_tres' => 'Vuelve aquí e inicia sesión.',
        'sin_correo' => 'Si no encuentras el mensaje, espera unos minutos y revisa spam. Si ya tenías cuenta, puedes intentar iniciar sesión.',
        'lista_titulo' => 'Tu cuenta está lista',
        'lista' => 'Ya puedes iniciar sesión. Para crear grupos y exámenes necesitarás un plan activo.',
        'procesando_titulo' => 'Recibimos tu registro',
        'procesando' => 'Tu cuenta sigue en preparación. Intenta iniciar sesión en unos minutos; si el problema continúa, contacta a soporte.',
    ],

    'estado_creando' => 'Creando tu cuenta…',

];
