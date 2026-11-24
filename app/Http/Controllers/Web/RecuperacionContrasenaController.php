<?php

/**
 * Archivo: RecuperacionContrasenaController.php
 * Módulo: app/Http/Controllers/Web
 * Descripción: Solicitud con doble captura del correo y restablecimiento sin iniciar sesión en el portal.
 * Autor: Axel Rebollo Zeferino
 * Creado: 24/11/2026
 * Modificado: 24/11/2026 · Equipo BigBitByte · Recuperación docente
 */

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\PanelApiService;
use App\Services\RecuperacionContrasenaService;
use App\Services\SesionPanelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RecuperacionContrasenaController extends Controller
{
    public const SOLICITUD = 'bbb.password_recovery';

    public const AUTORIZACION = 'bbb.password_reset';

    public function create(): View
    {
        return view('auth.recuperar-contrasena', ['modo' => 'correo']);
    }

    public function preparar(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:150']]);
        $request->session()->put(self::SOLICITUD, ['email' => trim($data['email']), 'expira' => time() + 900, 'enviado' => false]);

        return redirect()->route('password.confirm');
    }

    public function confirmar(Request $request): View|RedirectResponse
    {
        $estado = $request->session()->get(self::SOLICITUD);
        if (! is_array($estado) || ($estado['expira'] ?? 0) <= time()) {
            return redirect()->route('password.request');
        }

        return view('auth.recuperar-contrasena', ['modo' => $estado['enviado'] ? 'enviado' : 'confirmar', 'pista' => $this->pista($estado['email'])]);
    }

    public function enviar(Request $request, PanelApiService $api): RedirectResponse
    {
        $data = $request->validate(['email_confirmation' => ['required', 'email', 'max:150']]);
        $estado = $request->session()->get(self::SOLICITUD);
        if (! is_array($estado) || ($estado['expira'] ?? 0) <= time() || $estado['enviado']) {
            return redirect()->route('password.request');
        }
        if (! hash_equals(mb_strtolower($estado['email']), mb_strtolower(trim($data['email_confirmation'])))) {
            return redirect()->route('password.confirm')->withErrors(['email_confirmation' => __('recuperacion.no_coincide')]);
        }
        $response = $api->publico('/auth/recover', ['email' => $estado['email'], 'web_redirect' => true]);
        if (! $response->exitosa()) {
            return redirect()->route('password.confirm')->with('error', $response->estado === 429 ? __('mensajes.aviso.demasiados_intentos') : __('recuperacion.servicio'));
        }
        $estado['enviado'] = true;
        $request->session()->put(self::SOLICITUD, $estado);

        return redirect()->route('password.confirm');
    }

    public function reset(Request $request): View
    {
        $grant = $request->session()->get(self::AUTORIZACION);
        if (! is_array($grant) || ($grant['expira'] ?? 0) <= time()) {
            $request->session()->forget(self::AUTORIZACION);
            $grant = null;
        }

        return view('auth.recuperar-contrasena', ['modo' => $grant ? 'restablecer' : 'enlace', 'pista' => $grant ? $this->pista($grant['email']) : null]);
    }

    public function verificar(Request $request, RecuperacionContrasenaService $recovery): RedirectResponse
    {
        $data = $request->validate(['access_token' => ['required', 'string', 'max:12000']]);
        $request->session()->forget(self::AUTORIZACION);
        try {
            $user = $recovery->validar($data['access_token']);
        } catch (ValidationException) {
            return redirect()->route('password.reset')->with('error', __('recuperacion.enlace_invalido'));
        } catch (HttpException) {
            return redirect()->route('password.reset')->with('error', __('recuperacion.servicio'));
        }
        $request->session()->regenerate();
        $request->session()->put(self::AUTORIZACION, $user + ['token' => Crypt::encryptString($data['access_token'])]);

        return redirect()->route('password.reset');
    }

    public function actualizar(Request $request, RecuperacionContrasenaService $recovery): RedirectResponse
    {
        $grant = $request->session()->get(self::AUTORIZACION);
        if (! is_array($grant) || ($grant['expira'] ?? 0) <= time()) {
            $request->session()->forget(self::AUTORIZACION);

            return redirect()->route('password.reset')->with('error', __('recuperacion.enlace_invalido'));
        }
        $data = $request->validate(['password' => ['required', 'string', 'min:10', 'max:200', 'confirmed']]);
        try {
            $recovery->cambiar(Crypt::decryptString($grant['token']), $data['password'], $grant['email']);
        } catch (ValidationException|HttpException) {
            $request->session()->forget(self::AUTORIZACION);

            return redirect()->route('password.reset')->with('error', __('recuperacion.nuevo_enlace'));
        }
        $request->session()->forget([self::SOLICITUD, self::AUTORIZACION]);
        foreach ([SesionPanelService::CLAVE, SesionPanelService::CLAVE_ADMIN] as $key) {
            if ($request->session()->get($key.'.docente.id') === $grant['id']) {
                $request->session()->forget($key);
            }
        }
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('exito', __('recuperacion.exito'));
    }

    private function pista(string $email): string
    {
        return mb_substr($email, 0, 1).'*****'.mb_strtolower(mb_substr($email, mb_strrpos($email, '@')));
    }
}
