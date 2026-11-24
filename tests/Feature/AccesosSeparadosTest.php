<?php

/**
 * Archivo: AccesosSeparadosTest.php
 * Módulo: tests/Feature
 * Descripción: Comprueba que los accesos docente y administrativo conservan sesiones y menús independientes.
 * Autor: Axel Rebollo Zeferino
 * Creado: 23/11/2026
 * Modificado: 23/11/2026 · Equipo BigBitByte · Regresión de separación de portales
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\SesionPanelService;
use Tests\Support\ConPanelFalso;
use Tests\TestCase;

class AccesosSeparadosTest extends TestCase
{
    use ConPanelFalso;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararPanel();
    }

    private function loginData(string $role = 'super_admin'): array
    {
        return ['access_token' => 'admin-access', 'refresh_token' => 'admin-refresh', 'expires_in' => 3600,
            'docente' => ['id' => '11111111-1111-4111-8111-111111111111', 'nombre' => 'Axel', 'correo' => 'axel@example.test', 'rol' => $role]];
    }

    private function credentials(): array
    {
        return ['email' => 'axel@example.test', 'password' => 'a-secret-password'];
    }

    public function test_public_entry_keeps_only_teacher_and_student_and_admin_has_its_own_page(): void
    {
        $this->get('/')->assertOk()->assertSee(route('login'), false)->assertSee(route('student.entry'), false)
            ->assertDontSee('/administracion')->assertDontSee('superadministrador');
        $this->get('/administracion/ingreso')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('Administración del sistema')
            ->assertSee(route('admin.login.store'), false)->assertDontSee(route('registro'), false);
    }

    public function test_teacher_login_of_an_admin_account_stays_teacher_and_keeps_the_teacher_menu(): void
    {
        $this->api->responder('POST', '/auth/login', $this->loginData());
        $this->prepararLecturas();
        $this->post('/login', $this->credentials() + ['rol' => 'super_admin'])->assertRedirect(route('panel.inicio'))
            ->assertSessionHas(SesionPanelService::CLAVE.'.docente.rol', 'docente')->assertSessionMissing(SesionPanelService::CLAVE_ADMIN);
        $this->get('/panel')->assertOk()->assertSee('Portal del docente')->assertSee(route('panel.grupos'), false)
            ->assertDontSee(route('panel.docentes'), false);
    }

    public function test_existing_teacher_sessions_marked_as_admin_recover_the_teacher_panel_without_logging_out(): void
    {
        $this->prepararLecturas();
        $this->withSession([SesionPanelService::CLAVE => SesionPanelService::datosParaSesion($this->loginData())]);
        $this->get('/panel')->assertOk()->assertSee('Portal del docente')->assertSee(route('panel.grupos'), false)
            ->assertDontSee(route('panel.docentes'), false);
    }

    public function test_admin_login_uses_a_separate_session_and_does_not_replace_the_teacher(): void
    {
        $this->conSesionDelPanel('Docente existente');
        $this->api->responder('POST', '/auth/login', $this->loginData())->responder('GET', '/admin/teachers', ['data' => []]);
        $this->post('/administracion/ingreso', $this->credentials())->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas(SesionPanelService::CLAVE_ADMIN.'.docente.rol', 'super_admin')
            ->assertSessionHas(SesionPanelService::CLAVE.'.docente.nombre', 'Docente existente');
        $this->get('/administracion/docentes')->assertOk()->assertSee('Administración del sistema')
            ->assertSee(route('panel.pagos'), false)->assertDontSee(route('panel.grupos'), false)
            ->assertSee(route('admin.logout'), false);
        $this->get('/login')->assertRedirect(route('panel.inicio'));
    }

    public function test_teacher_session_cannot_be_used_as_an_administrative_login(): void
    {
        $this->conSesionDelPanel();
        $this->get('/administracion/docentes')->assertRedirect(route('admin.login'));
        $this->get('/administracion')->assertRedirect(route('admin.login'));
        $this->assertCount(0, $this->api->llamadasA('GET', '/admin/payments'));
        $this->get('/administracion/ingreso')->assertOk()->assertSee('Administración del sistema');
        $this->assertCount(0, $this->api->llamadasA('GET', '/admin/teachers'));
    }

    public function test_an_unprivileged_account_cannot_create_admin_session_by_submitting_a_role(): void
    {
        $this->conSesionDelPanel('Docente existente');
        $this->api->responder('POST', '/auth/login', $this->loginData('docente'));
        $this->post('/administracion/ingreso', $this->credentials() + ['rol' => 'super_admin'])
            ->assertRedirect(route('admin.login'))->assertSessionHasErrors('email')
            ->assertSessionMissing(SesionPanelService::CLAVE_ADMIN)
            ->assertSessionHas(SesionPanelService::CLAVE.'.docente.nombre', 'Docente existente');
    }

    public function test_admin_logout_revokes_only_its_token_and_preserves_teacher_session(): void
    {
        $this->conSesionDelPanel('Docente existente');
        $this->withSession([SesionPanelService::CLAVE_ADMIN => SesionPanelService::datosParaSesion($this->loginData())]);
        $this->api->responder('POST', '/auth/logout', ['data' => ['token_revocado' => true]]);
        $this->post('/administracion/salir')->assertRedirect(route('admin.login'))
            ->assertSessionMissing(SesionPanelService::CLAVE_ADMIN)
            ->assertSessionHas(SesionPanelService::CLAVE.'.docente.nombre', 'Docente existente');
        $this->assertSame('admin-access', $this->api->llamadasA('POST', '/auth/logout')[0]['token']);
    }

    public function test_teacher_logout_preserves_the_independent_admin_session(): void
    {
        $this->conSesionDelPanel();
        $this->withSession([SesionPanelService::CLAVE_ADMIN => SesionPanelService::datosParaSesion($this->loginData())]);
        $this->api->responder('POST', '/auth/logout', ['data' => ['token_revocado' => true]]);
        $this->post('/logout')->assertRedirect(route('login'))->assertSessionMissing(SesionPanelService::CLAVE)
            ->assertSessionHas(SesionPanelService::CLAVE_ADMIN.'.docente.rol', 'super_admin');
        $this->get('/administracion/ingreso')->assertRedirect(route('admin.dashboard'));
        $this->get('/login')->assertOk()->assertSee('Inicia sesión en tu espacio docente');
    }

    public function test_teacher_login_does_not_follow_an_administrative_intended_destination(): void
    {
        $this->api->responder('POST', '/auth/login', $this->loginData());
        $this->withSession(['url.intended' => route('panel.docentes')]);
        $this->post('/login', $this->credentials())->assertRedirect(route('panel.inicio'));
    }

    public function test_invalid_admin_login_stays_on_the_administrative_entry(): void
    {
        $this->post('/administracion/ingreso', ['email' => 'invalid'])->assertRedirect(route('admin.login'))->assertSessionHasErrors(['email', 'password']);
    }

    public function test_admin_second_factor_uses_its_own_challenge_and_token_session(): void
    {
        $factor = '22222222-2222-4222-8222-222222222222';
        $data = SesionPanelService::datosParaSesion($this->loginData() + ['mfa_required' => true]);
        $this->withSession([SesionPanelService::CLAVE_ADMIN => $data, 'admin_mfa_challenge' => ['factor' => $factor, 'id' => 'challenge'], 'mfa_challenge' => ['factor' => 'teacher-factor', 'id' => 'teacher-challenge']]);
        $this->api->responder('POST', '/auth/mfa/'.$factor.'/verify', ['data' => ['access_token' => 'admin-aal2', 'expires_in' => 3600]]);
        $this->post('/administracion/seguridad/doble-factor/verificar', ['codigo' => '123456'])
            ->assertRedirect(route('admin.dashboard'))->assertSessionMissing('admin_mfa_challenge')
            ->assertSessionHas('mfa_challenge.id', 'teacher-challenge')
            ->assertSessionHas(SesionPanelService::CLAVE_ADMIN.'.mfa_pendiente', false);
    }
}
