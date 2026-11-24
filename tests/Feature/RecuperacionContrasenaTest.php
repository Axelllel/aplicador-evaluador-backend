<?php

/**
 * Archivo: RecuperacionContrasenaTest.php
 * Módulo: tests/Feature
 * Descripción: Comprueba doble captura del correo, prueba de recuperación válida, caducidad y cambio sin crear sesión.
 * Autor: Axel Rebollo Zeferino
 * Creado: 24/11/2026
 * Modificado: 24/11/2026 · Equipo BigBitByte · Recuperación docente
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Web\RecuperacionContrasenaController as Recovery;
use App\Services\RevocacionSesionService;
use App\Services\SesionPanelService;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\Support\ConPanelFalso;
use Tests\TestCase;

class RecuperacionContrasenaTest extends TestCase
{
    use ConPanelFalso;

    private const UID = '11111111-1111-4111-8111-111111111111';

    private const EMAIL = 'docente@outlook.com';

    private bool $revocado = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['services.supabase.url' => 'https://auth.example.test', 'services.supabase.key' => 'public-key',
            'services.supabase.password_reset_url' => 'https://bigbitbyte.example/restablecer-contrasena']);
        Http::preventStrayRequests();
        $revocacion = Mockery::mock(RevocacionSesionService::class)->makePartial();
        $revocacion->shouldReceive('revoked')->andReturnUsing(fn () => $this->revocado);
        $revocacion->shouldReceive('revoke')->andReturnUsing(function (): void {
            $this->revocado = true;
        });
        $this->app->instance(RevocacionSesionService::class, $revocacion);
    }

    private function token(string $method = 'recovery', int $age = 0): string
    {
        return 'header.'.rtrim(strtr(base64_encode(json_encode(['sub' => self::UID, 'session_id' => '22222222-2222-4222-8222-222222222222', 'exp' => time() + 3600,
            'amr' => [['method' => $method, 'timestamp' => time() - $age]]])), '+/', '-_'), '=').'.verified-by-provider';
    }

    private function proveedor(int $status = 200): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([
            'auth.example.test/auth/v1/user' => Http::response(['id' => self::UID, 'email' => self::EMAIL], $status),
            'auth.example.test/auth/v1/logout*' => Http::response([], 204),
        ]);
    }

    public function test_login_tiene_acceso_y_primero_se_captura_el_correo_sin_solicitar_envio(): void
    {
        $this->get('/login')->assertOk()->assertSee(route('password.request'), false)->assertSee('¿Olvidaste tu contraseña?');
        $this->post('/recuperar-contrasena', ['email' => self::EMAIL])->assertRedirect(route('password.confirm'))
            ->assertSessionHas(Recovery::SOLICITUD.'.email', self::EMAIL);
        $this->get('/recuperar-contrasena/confirmar')->assertOk()->assertSee('…@outlook.com')->assertDontSee(self::EMAIL)
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
        Http::assertNothingSent();
    }

    public function test_solo_solicita_el_correo_si_la_segunda_captura_coincide(): void
    {
        $this->prepararPanel();
        $this->withSession([Recovery::SOLICITUD => ['email' => self::EMAIL, 'expira' => time() + 900, 'enviado' => false]]);
        $this->post('/recuperar-contrasena/confirmar', ['email_confirmation' => 'otra@outlook.com'])->assertRedirect(route('password.confirm'))->assertSessionHasErrors('email_confirmation');
        $this->assertCount(0, $this->api->llamadasA('POST', '/auth/recover'));
        $this->api->responder('POST', '/auth/recover', ['message' => 'Solicitud recibida']);
        $this->post('/recuperar-contrasena/confirmar', ['email_confirmation' => 'DOCENTE@OUTLOOK.COM'])->assertRedirect(route('password.confirm'));
        $this->assertSame(['email' => self::EMAIL, 'web_redirect' => true], $this->api->llamadasA('POST', '/auth/recover')[0]['datos']);
        $this->get('/recuperar-contrasena/confirmar')->assertOk()->assertSee('Si el correo corresponde a una cuenta registrada')->assertDontSee(self::EMAIL);
    }

    public function test_el_destino_del_enlace_es_fijo_y_no_acepta_una_url_del_cliente(): void
    {
        Http::fake(['auth.example.test/auth/v1/recover*' => Http::response([], 200)]);
        $this->postJson('/api/v1/auth/recover', ['email' => self::EMAIL, 'web_redirect' => true, 'redirect_to' => 'https://evil.example'])->assertOk();
        Http::assertSent(fn ($request) => $request->url() === 'https://auth.example.test/auth/v1/recover?redirect_to='.urlencode('https://bigbitbyte.example/restablecer-contrasena') && $request['email'] === self::EMAIL);
    }

    public function test_el_error_del_correo_no_se_presenta_como_envio_exitoso(): void
    {
        $this->prepararPanel();
        $this->withSession([Recovery::SOLICITUD => ['email' => self::EMAIL, 'expira' => time() + 900, 'enviado' => false]]);
        $this->api->responder('POST', '/auth/recover', [], 503);
        $this->post('/recuperar-contrasena/confirmar', ['email_confirmation' => self::EMAIL])->assertSessionHas('error')->assertSessionHas(Recovery::SOLICITUD.'.enviado', false);
    }

    public function test_un_jwt_normal_falsificado_o_viejo_no_autoriza_el_cambio(): void
    {
        $this->proveedor();
        foreach ([$this->token('password'), $this->token('recovery', 901)] as $token) {
            $this->post('/restablecer-contrasena/verificar', ['access_token' => $token])->assertRedirect(route('password.reset'))->assertSessionMissing(Recovery::AUTORIZACION)->assertSessionMissing('_old_input.access_token');
        }
        $this->proveedor(401);
        $this->post('/restablecer-contrasena/verificar', ['access_token' => $this->token()])->assertSessionMissing(Recovery::AUTORIZACION);
        $this->post('/restablecer-contrasena', ['password' => 'clave-nueva-segura', 'password_confirmation' => 'clave-nueva-segura'])->assertRedirect(route('password.reset'));
    }

    public function test_el_enlace_crea_solo_una_autorizacion_temporal_y_la_contrasena_se_actualiza_una_vez(): void
    {
        $this->proveedor();
        $token = $this->token();
        $this->post('/restablecer-contrasena/verificar', ['access_token' => $token])->assertRedirect(route('password.reset'))
            ->assertSessionMissing(SesionPanelService::CLAVE)->assertSessionMissing(SesionPanelService::CLAVE_ADMIN);
        $cipher = session(Recovery::AUTORIZACION.'.token');
        $this->assertNotSame($token, $cipher);
        $this->assertSame($token, Crypt::decryptString($cipher));
        $this->get('/restablecer-contrasena')->assertOk()->assertSee('Crea una nueva contraseña')->assertDontSee($token)->assertDontSee(self::EMAIL);
        $this->post('/restablecer-contrasena', ['password' => 'corta', 'password_confirmation' => 'distinta'])->assertSessionHasErrors('password')
            ->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation');
        $this->post('/restablecer-contrasena', ['password' => 'clave-nueva-segura', 'password_confirmation' => 'clave-nueva-segura'])
            ->assertRedirect(route('login'))->assertSessionHas('exito')->assertSessionMissing(Recovery::AUTORIZACION)->assertSessionMissing(SesionPanelService::CLAVE);
        Http::assertSent(fn ($request) => $request->method() === 'PUT' && $request->url() === 'https://auth.example.test/auth/v1/user' && $request['password'] === 'clave-nueva-segura');
        $this->assertTrue($this->revocado);
        $this->post('/restablecer-contrasena/verificar', ['access_token' => $token])->assertSessionMissing(Recovery::AUTORIZACION)->assertSessionHas('error');
    }

    public function test_la_autorizacion_caducada_no_envia_ningun_cambio_al_proveedor(): void
    {
        $this->withSession([Recovery::AUTORIZACION => ['id' => self::UID, 'email' => self::EMAIL, 'expira' => time() - 1, 'token' => Crypt::encryptString($this->token())]]);
        $this->post('/restablecer-contrasena', ['password' => 'clave-nueva-segura', 'password_confirmation' => 'clave-nueva-segura'])->assertRedirect(route('password.reset'))->assertSessionMissing(Recovery::AUTORIZACION);
        Http::assertNothingSent();
    }
}
