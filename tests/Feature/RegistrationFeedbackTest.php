<?php

/**
 * Archivo: RegistrationFeedbackTest.php
 * Módulo: tests/Feature
 * Descripción: Comprueba avisos de confirmación, errores externos y manejo seguro de los datos del registro.
 * Autor: Axel Rebollo Zeferino
 * Creado: 20/11/2026
 * Modificado: 20/11/2026 · Estados explícitos del alta de cuenta
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\DatabaseUserContext;
use Illuminate\Support\Facades\Http;
use Mockery;
use RuntimeException;
use Tests\Support\ConPanelFalso;
use Tests\TestCase;

class RegistrationFeedbackTest extends TestCase
{
    use ConPanelFalso;

    private const UID = '11111111-1111-4111-8111-111111111111';

    private const SIGNUP = 'https://auth.example.test/auth/v1/signup';

    private const INPUT = ['nombre' => 'Docente de prueba', 'email' => 'docente@example.test', 'password' => 'password-solo-de-prueba'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['services.supabase.url' => 'https://auth.example.test', 'services.supabase.key' => 'public-test-key']);
        Http::preventStrayRequests();
    }

    public function test_unconfirmed_account_reports_the_requested_email_instead_of_ready_to_login(): void
    {
        $this->profileContext();
        Http::fake([self::SIGNUP => Http::response(['id' => self::UID, 'confirmation_sent_at' => '2026-11-20T00:00:00Z'], 200)]);
        $this->postJson('/api/v1/auth/register', self::INPUT)->assertAccepted()
            ->assertJsonPath('data.registration_status', 'confirmation_required')
            ->assertJsonPath('data.confirmation_email_requested', true)
            ->assertJsonMissingPath('access_token')->assertJsonMissingPath('password');
    }

    public function test_absent_confirmation_timestamp_does_not_claim_an_email_was_requested(): void
    {
        $this->profileContext();
        Http::fake([self::SIGNUP => Http::response(['user' => ['id' => self::UID], 'session' => null], 200)]);
        $this->postJson('/api/v1/auth/register', self::INPUT)->assertAccepted()->assertJsonPath('data.confirmation_email_requested', false);
    }

    public function test_confirmed_account_is_ready_but_profile_failure_is_a_different_pending_state(): void
    {
        $this->profileContext(true);
        Http::fake([self::SIGNUP => Http::response(['user' => ['id' => self::UID, 'email_confirmed_at' => '2026-11-20T00:00:00Z']], 200)]);
        $this->postJson('/api/v1/auth/register', self::INPUT)->assertAccepted()
            ->assertJsonPath('data.registration_status', 'profile_pending')
            ->assertJsonPath('data.email_confirmation_required', false);
    }

    public function test_confirmed_account_with_profile_returns_ready(): void
    {
        $this->profileContext();
        Http::fake([self::SIGNUP => Http::response(['user' => ['id' => self::UID], 'access_token' => 'test-token-never-exposed'], 200)]);
        $this->postJson('/api/v1/auth/register', self::INPUT)->assertCreated()->assertJsonPath('data.registration_status', 'ready')->assertJsonMissingPath('access_token');
    }

    public function test_upstream_failure_does_not_become_a_successful_registration(): void
    {
        Http::fake([self::SIGNUP => Http::response(['message' => 'SMTP unavailable'], 500)]);
        $this->postJson('/api/v1/auth/register', self::INPUT)->assertServiceUnavailable()->assertJsonMissingPath('data.confirmation_email_requested');
    }

    public function test_failed_recovery_is_reported_without_disclosing_account_existence(): void
    {
        Http::fake(['https://auth.example.test/auth/v1/recover' => Http::response([], 500)]);
        $this->postJson('/api/v1/auth/recover', ['email' => 'docente@example.test'])->assertServiceUnavailable();
    }

    public function test_web_registration_shows_persistent_email_instructions_and_keeps_only_the_email(): void
    {
        $this->prepararPanel();
        $this->api->responder('POST', '/auth/register', ['data' => ['registration_status' => 'confirmation_required', 'confirmation_email_requested' => true]], 202);
        $this->post('/registro', self::INPUT)->assertRedirect(route('login'))->assertSessionHas('registro_estado.correo_solicitado', true)
            ->assertSessionHasInput('email', 'docente@example.test')->assertSessionMissing('_old_input.password');
        $this->get('/login')->assertOk()->assertSee('Confirma tu correo electrónico')->assertSee('Se solicitó el correo de confirmación.')
            ->assertSee('carpeta de spam')->assertSee('data-focus-notice', false)->assertDontSee('data-autoocultar="5000"', false);
    }

    public function test_failed_web_registration_preserves_safe_input_without_the_password_or_success_notice(): void
    {
        $this->prepararPanel();
        $this->api->responder('POST', '/auth/register', [], 503);
        $this->post('/registro', self::INPUT)->assertRedirect(route('registro'))->assertSessionHas('error')->assertSessionMissing('registro_estado')
            ->assertSessionHasInput('email', 'docente@example.test')->assertSessionMissing('_old_input.password');
    }

    private function profileContext(bool $fail = false): void
    {
        $context = Mockery::mock(DatabaseUserContext::class);
        $expectation = $context->shouldReceive('ejecutarConDocenteVerificado')->once()->with(self::UID, Mockery::type('callable'));
        if ($fail) {
            $expectation->andThrow(new RuntimeException('profile unavailable'));
        } else {
            $expectation->andReturn(null);
        }
        $this->app->instance(DatabaseUserContext::class, $context);
    }
}
