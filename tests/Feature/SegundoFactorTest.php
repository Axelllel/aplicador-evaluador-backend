<?php

/**
 * Archivo: SegundoFactorTest.php
 * Módulo: tests/Feature
 * Descripción: Verifica la exigencia de MFA y la actualización segura de la sesión web.
 * Autor: Axel Rebollo Zeferino
 * Creado: 22/11/2026
 * Modificado: 22/11/2026 · Equipo BigBitByte · Autenticación
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\DatabaseUserContext;
use App\Services\SesionPanelService;
use Illuminate\Support\Facades\Http;
use Tests\Support\ConPanelFalso;
use Tests\TestCase;

class SegundoFactorTest extends TestCase
{
    use ConPanelFalso;

    public function test_verified_phone_blocks_teacher_data_until_aal2_without_disclosing_sql(): void
    {
        config(['services.supabase.url' => 'https://auth.example.test', 'services.supabase.key' => 'public-test-key']);
        Http::preventStrayRequests();
        Http::fake(['auth.example.test/*' => Http::response(['id' => '11111111-1111-4111-8111-111111111111',
            'factors' => [['id' => '22222222-2222-4222-8222-222222222222', 'factor_type' => 'phone', 'status' => 'verified']]])]);
        $this->mock(DatabaseUserContext::class)->shouldNotReceive('ejecutarConDocenteVerificado');
        $this->withToken('validated-by-provider-aal1')->getJson('/api/v1/teacher/groups')->assertForbidden()->assertJsonPath('code', 'MFA_REQUIRED');
        Http::assertSentCount(1);
    }

    public function test_pending_mfa_web_session_redirects_to_verification_instead_of_loading_teacher_data(): void
    {
        $this->withSession([SesionPanelService::CLAVE => SesionPanelService::datosParaSesion([
            'access_token' => 'test-token', 'expires_in' => 3600, 'mfa_required' => true,
            'docente' => ['id' => '11111111-1111-4111-8111-111111111111'],
        ])])->get('/panel')->assertRedirect(route('panel.mfa'));
    }

    public function test_successful_verification_replaces_tokens_and_preserves_the_teacher_identity(): void
    {
        $this->prepararPanel();
        $this->conSesionDelPanel();
        $factor = '22222222-2222-4222-8222-222222222222';
        $challenge = '33333333-3333-4333-8333-333333333333';
        $teacher = app(SesionPanelService::class)->docente();
        $this->withSession(['mfa_challenge' => ['factor' => $factor, 'id' => $challenge]]);
        $this->api->responder('POST', '/auth/mfa/'.$factor.'/verify', ['data' => ['access_token' => 'new-aal2-token', 'refresh_token' => 'new-refresh', 'expires_in' => 3600]]);
        $this->post('/seguridad/doble-factor/verificar', ['codigo' => '123456'])
            ->assertRedirect(route('panel.inicio'))->assertSessionMissing('mfa_challenge')
            ->assertSessionHas(SesionPanelService::CLAVE.'.mfa_pendiente', false);
        $this->assertSame($teacher, app(SesionPanelService::class)->docente());
        $this->assertSame('new-aal2-token', app(SesionPanelService::class)->token());
        $this->assertSame($challenge, $this->api->llamadasA('POST', '/auth/mfa/'.$factor.'/verify')[0]['datos']['challenge_id']);
    }

    public function test_la_pantalla_de_mfa_muestra_el_factor_y_la_accion_para_solicitar_el_codigo(): void
    {
        $this->prepararPanel();
        $this->conSesionDelPanel();
        $this->api->responder('GET', '/auth/mfa', ['data' => ['factores' => [[
            'id' => '22222222-2222-4222-8222-222222222222', 'tipo' => 'phone', 'estado' => 'verified', 'telefono_final' => '5678',
        ]], 'nivel' => 'aal1', 'alta_telefono_habilitada' => false]]);
        $this->get('/seguridad/doble-factor')->assertOk()->assertSee('5678')->assertSee('Solicitar verificación');
    }
}
