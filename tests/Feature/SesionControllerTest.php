<?php

/**
 * Archivo: SesionControllerTest.php
 * Módulo: tests/Feature
 * Descripción: Verifica el inicio y el cierre de sesión del panel con la API sustituida: formulario sin enlaces
 *              de demostración, login correcto e incorrecto, token cifrado en la sesión, id de sesión nuevo,
 *              límite de intentos, fallos del servicio y cierre de sesión (Manual COD-WEB, secciones 6.14, 6.16, 6.18 y 8.2).
 * Autor: Leonardo Ballestero López
 * Creado: 27/10/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Autenticación real con PanelApiService fingido
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\SesionPanelService;
use Illuminate\Support\Str;
use Tests\Support\ConPanelFalso;
use Tests\TestCase;

class SesionControllerTest extends TestCase
{
    use ConPanelFalso;

    private const RESPUESTA_DE_LOGIN = [
        'message' => 'Inicio de sesión exitoso.',
        'access_token' => 'token-secreto-de-acceso',
        'refresh_token' => 'token-secreto-de-refresco',
        'token_type' => 'bearer',
        'expires_in' => 3600,
        'docente' => [
            'id' => '94902bd0-fc37-4728-a6e3-6d34b804f100',
            'nombre' => 'Laura Méndez Ríos',
            'correo' => 'laura@example.com',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPanel();
    }

    public function test_login_responde_200_con_los_textos_clave(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('<html lang="es">', false)
            ->assertSee('Iniciar sesión')
            ->assertSee('Correo electrónico')
            ->assertSee('Contraseña')
            ->assertSee('Saltar al contenido')
            ->assertSee('<title>Iniciar sesión · Sistema Aplicador / Evaluador de Exámenes</title>', false);
    }

    public function test_login_docente_reutiliza_la_identidad_visual_del_portal_alumno(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('width="42"', false)
            ->assertSee('height="42"', false)
            ->assertSee('images/bigbitbyte-logo.png', false)
            ->assertSee('alt="Logotipo de BigBitByte: un cubo con tres letras B"', false)
            ->assertSee('Portal del docente')
            ->assertSee('student-entry.css')
            ->assertSee('id="toggle-theme"', false);
    }

    public function test_login_tiene_el_boton_principal_de_ancho_completo(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('type="submit"', false)
            ->assertSee('w-full', false);
    }

    public function test_login_no_tiene_avisos_de_maqueta_ni_enlaces_de_demostracion(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('maqueta')
            ->assertDontSee('Ver el panel del docente')
            ->assertDontSee('superadministrador')
            ->assertDontSee('super_admin')
            ->assertDontSee('Ver la guía de estilo')
            ->assertSee('Crear una cuenta')
            ->assertSee(route('registro'), false);
    }

    public function test_el_enlace_a_la_guia_de_estilo_solo_aparece_en_local(): void
    {
        $this->app['env'] = 'local';

        $this->get('/login')->assertOk()->assertSee('Ver la guía de estilo');
    }

    public function test_login_muestra_los_errores_de_validacion_bajo_cada_campo(): void
    {
        $this->from('/login')
            ->followingRedirects()
            ->post('/login', ['email' => '', 'password' => ''])
            ->assertOk()
            ->assertSee('Completa correo electrónico.')
            ->assertSee('Completa contraseña.')
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('border-error', false);

        $this->assertSame([], $this->api->llamadas, 'Un formulario inválido no debe llegar a la API.');
    }

    public function test_login_pone_en_mayuscula_la_primera_letra_del_mensaje_de_error(): void
    {
        $this->from('/login')
            ->followingRedirects()
            ->post('/login', ['email' => 'no-es-un-correo', 'password' => 'abcdefgh'])
            ->assertOk()
            ->assertSee('Correo electrónico debe ser un correo electrónico válido.');
    }

    public function test_login_correcto_guarda_el_token_cifrado_y_abre_el_panel(): void
    {
        $this->api->responder('POST', '/auth/login', self::RESPUESTA_DE_LOGIN);

        $this->post('/login', ['email' => 'laura@example.com', 'password' => 'una-clave-larga-123'])
            ->assertRedirect(route('panel.inicio'));

        $sesion = app(SesionPanelService::class);

        $this->assertTrue($sesion->activa());
        $this->assertSame('token-secreto-de-acceso', $sesion->token());
        $this->assertSame('Laura Méndez Ríos', $sesion->nombre());
        $this->assertSame('laura@example.com', $sesion->docente()['correo']);

        $guardado = json_encode(session(SesionPanelService::CLAVE), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('token-secreto-de-acceso', $guardado, 'El token debe guardarse cifrado.');
        $this->assertStringNotContainsString('token-secreto-de-refresco', $guardado, 'El token de refresco debe guardarse cifrado.');

        $llamada = $this->api->llamadasA('POST', '/auth/login')[0];
        $this->assertSame(['email' => 'laura@example.com', 'password' => 'una-clave-larga-123'], $llamada['datos']);
        $this->assertNull($llamada['token'], 'El login es una ruta pública: no lleva token.');
    }

    public function test_login_correcto_renueva_el_identificador_de_sesion(): void
    {
        $this->api->responder('POST', '/auth/login', self::RESPUESTA_DE_LOGIN);

        // El visitante llega con un id de sesión ya fijado (cookie): tras iniciar sesión debe ser otro.
        $antes = Str::random(40);

        $this->disableCookieEncryption()
            ->withCookie(config('session.cookie'), $antes)
            ->post('/login', ['email' => 'laura@example.com', 'password' => 'una-clave-larga-123'])
            ->assertRedirect(route('panel.inicio'));

        $this->assertNotSame($antes, session()->getId(), 'Debe regenerarse el id de sesión (anti fijación).');
    }

    public function test_login_correcto_vuelve_a_la_pagina_que_se_pidio_antes(): void
    {
        $this->api->responder('POST', '/auth/login', self::RESPUESTA_DE_LOGIN);

        $this->get('/grupos')->assertRedirect(route('login'));

        $this->post('/login', ['email' => 'laura@example.com', 'password' => 'una-clave-larga-123'])
            ->assertRedirect(route('panel.grupos'));
    }

    public function test_login_con_credenciales_incorrectas_muestra_un_error_sin_detalles_tecnicos(): void
    {
        $this->api->responder('POST', '/auth/login', ['message' => 'Credenciales inválidas o cuenta no habilitada.'], 401);

        $this->from('/login')
            ->followingRedirects()
            ->post('/login', ['email' => 'laura@example.com', 'password' => 'incorrecta'])
            ->assertOk()
            ->assertSee('El correo o la contraseña no son correctos, o tu cuenta aún no está habilitada.')
            ->assertSee('laura@example.com')
            ->assertDontSee('Credenciales inválidas')
            ->assertDontSee('401');

        $this->assertFalse(app(SesionPanelService::class)->activa());
    }

    public function test_login_con_demasiados_intentos_muestra_una_advertencia(): void
    {
        $this->api->responder('POST', '/auth/login', ['message' => 'Too Many Attempts.'], 429);

        $this->from('/login')
            ->followingRedirects()
            ->post('/login', ['email' => 'laura@example.com', 'password' => 'incorrecta'])
            ->assertOk()
            ->assertSee('Hiciste demasiados intentos seguidos. Espera un minuto e inténtalo de nuevo.')
            ->assertDontSee('Too Many Attempts');
    }

    public function test_login_con_la_api_caida_muestra_un_error_generico(): void
    {
        $this->api->responder('POST', '/auth/login', ['message' => 'SQLSTATE[08006] connection refused'], 503);

        $this->from('/login')
            ->followingRedirects()
            ->post('/login', ['email' => 'laura@example.com', 'password' => 'una-clave-larga-123'])
            ->assertOk()
            ->assertSee('No pudimos completar la acción. Inténtalo de nuevo en unos minutos.')
            ->assertDontSee('SQLSTATE')
            ->assertSee('role="alert"', false);
    }

    public function test_login_de_un_usuario_que_no_es_docente_avisa_que_no_esta_registrado(): void
    {
        $this->api->responder('POST', '/auth/login', ['message' => 'Docente no registrado en el sistema.'], 403);

        $this->from('/login')
            ->followingRedirects()
            ->post('/login', ['email' => 'alguien@example.com', 'password' => 'una-clave-larga-123'])
            ->assertOk()
            ->assertSee('Tu cuenta no está registrada como docente.');
    }

    public function test_con_sesion_vigente_el_login_lleva_directo_al_panel(): void
    {
        $this->conSesionDelPanel()->get('/login')->assertRedirect(route('panel.inicio'));
    }

    public function test_cerrar_sesion_invalida_la_sesion_renueva_el_token_csrf_y_avisa(): void
    {
        $this->conSesionDelPanel()->withSession(['otro_dato' => 'x']);
        $this->startSession();
        $tokenCsrfAnterior = csrf_token();

        $this->post('/logout')
            ->assertRedirect('/login')
            ->assertSessionHas('exito', 'Sesión cerrada correctamente.');

        $this->assertFalse(app(SesionPanelService::class)->activa());
        $this->assertFalse(session()->has('otro_dato'), 'La sesión debe quedar vacía.');
        $this->assertNotSame($tokenCsrfAnterior, csrf_token(), 'Debe renovarse el token CSRF.');
    }

    public function test_despues_de_cerrar_sesion_el_panel_ya_no_abre(): void
    {
        $this->conSesionDelPanel()->post('/logout');

        $this->get('/panel')->assertRedirect(route('login'));
    }
}
