<?php

/**
 * Archivo: ProteccionPanelTest.php
 * Módulo: tests/Feature
 * Descripción: Verifica que el panel solo abre con sesión vigente: sin token o con token vencido redirige al
 *              inicio de sesión, y un 401 de la API cierra la sesión con una advertencia
 *              (Manual COD-WEB, secciones 6.18 y 8.2).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\SesionPanelService;
use Tests\Support\ConPanelFalso;
use Tests\TestCase;

class ProteccionPanelTest extends TestCase
{
    use ConPanelFalso;

    /** Páginas del panel que exigen sesión (GET). */
    private const PAGINAS = [
        '/panel',
        '/grupos',
        '/banco-de-preguntas',
        '/examenes',
        '/examenes/1/apartados',
        '/resultados',
        '/resultados/1/intentos/1',
        '/estadisticas',
        '/suscripcion',
        '/suscripcion/planes',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPanel();
    }

    public function test_sin_sesion_cada_pagina_del_panel_redirige_al_login(): void
    {
        foreach (self::PAGINAS as $pagina) {
            $this->get($pagina)->assertRedirect(route('login'));
        }

        $this->assertSame([], $this->api->llamadas, 'Sin sesión no debe llamarse a la API.');
    }

    public function test_sin_sesion_los_formularios_del_panel_redirigen_al_login(): void
    {
        foreach (['/grupos', '/banco-de-preguntas', '/examenes', '/examenes/1/apartados', '/examenes/1/preguntas', '/examenes/1/publicar'] as $ruta) {
            $this->post($ruta, [])->assertRedirect(route('login'));
        }

        $this->assertSame([], $this->api->llamadas);
    }

    public function test_sin_sesion_el_login_no_muestra_advertencias(): void
    {
        $this->followingRedirects()
            ->get('/panel')
            ->assertOk()
            ->assertDontSee('Tu sesión venció');
    }

    public function test_con_token_vencido_redirige_al_login_con_una_advertencia_y_olvida_la_sesion(): void
    {
        $this->conSesionDelPanel(segundosDeVigencia: -60);

        $this->get('/panel')
            ->assertRedirect(route('login'))
            ->assertSessionHas('aviso', 'Tu sesión venció. Inicia sesión de nuevo para continuar.');

        $this->assertFalse(session()->has(SesionPanelService::CLAVE));
        $this->assertSame([], $this->api->llamadas);
    }

    public function test_la_advertencia_de_sesion_vencida_se_ve_en_el_login(): void
    {
        $this->conSesionDelPanel(segundosDeVigencia: -60);

        $this->followingRedirects()
            ->get('/panel')
            ->assertOk()
            ->assertSee('Tu sesión venció. Inicia sesión de nuevo para continuar.')
            ->assertSee('Advertencia');
    }

    public function test_un_401_de_la_api_cierra_la_sesion_y_vuelve_al_login_con_una_advertencia(): void
    {
        $this->conSesionDelPanel();
        $this->api->responder('GET', '/teacher/groups', ['message' => 'Token inválido o expirado.'], 401);

        $this->get('/grupos')
            ->assertRedirect(route('login'))
            ->assertSessionHas('aviso', 'Tu sesión venció. Inicia sesión de nuevo para continuar.');

        $this->assertFalse(app(SesionPanelService::class)->activa());
    }

    public function test_un_401_al_guardar_un_formulario_tambien_vuelve_al_login(): void
    {
        $this->conSesionDelPanel();
        $this->api->responder('POST', '/teacher/groups', ['message' => 'Token inválido o expirado.'], 401);

        $this->post('/grupos', ['materia' => 'Álgebra'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('aviso');
    }

    public function test_las_respuestas_del_panel_no_se_guardan_en_la_cache_del_navegador(): void
    {
        $this->conSesionDelPanel();
        $this->prepararLecturas();

        $cabecera = (string) $this->get('/grupos')->assertOk()->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $cabecera);
    }

    public function test_la_ruta_rol_super_admin_ya_no_cambia_el_panel(): void
    {
        $this->conSesionDelPanel();
        $this->prepararLecturas();

        $this->get('/panel?rol=super_admin')
            ->assertOk()
            ->assertSee('Resumen del plan')
            ->assertDontSee('Panel de administración')
            ->assertDontSee('Superadministrador');
    }
}
