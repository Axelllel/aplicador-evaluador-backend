<?php

/**
 * Archivo: SegundoFactorController.php
 * Módulo: app/Http/Controllers/Web
 * Descripción: Gestiona el alta y la verificación del segundo factor del docente.
 * Autor: Axel Rebollo Zeferino
 * Creado: 22/11/2026
 * Modificado: 22/11/2026 · Equipo BigBitByte · Autenticación
 */

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\PanelApiService;
use App\Services\SesionPanelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SegundoFactorController extends Controller
{
    public function __construct(private readonly PanelApiService $api, private readonly SesionPanelService $session) {}

    public function show(): View
    {
        $response = $this->api->get('/auth/mfa');
        abort_unless($response->exitosa(), $response->estado);

        return view('auth.segundo-factor', ['datos' => $response->cuerpo['data']]);
    }

    public function enroll(Request $request): RedirectResponse
    {
        $data = $request->validate(['telefono' => ['required', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/']]);
        $response = $this->api->post('/auth/mfa/enroll', $data);

        return redirect()->route(SesionPanelService::rutaMfa())->with($response->exitosa() ? 'exito' : 'error',
            $response->exitosa() ? 'Teléfono agregado. Solicita el código para confirmar su activación.' : 'No se pudo agregar el teléfono. Revisa la configuración del servicio.');
    }

    public function challenge(Request $request): RedirectResponse
    {
        $data = $request->validate(['factor' => ['required', 'uuid']]);
        $response = $this->api->post('/auth/mfa/'.$data['factor'].'/challenge');
        if ($response->exitosa()) {
            $request->session()->put(SesionPanelService::claveReto(), ['factor' => $data['factor'], 'id' => $response->cuerpo['data']['id']]);
        }

        return redirect()->route(SesionPanelService::rutaMfa())->with($response->exitosa() ? 'exito' : 'error',
            $response->exitosa() ? 'Verificación preparada. Si elegiste teléfono, el código fue enviado por SMS; si elegiste autenticador, usa el código de tu aplicación.' : 'No se pudo preparar la verificación. Intenta de nuevo.');
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate(['codigo' => ['required', 'string', 'regex:/^[0-9]{6,10}$/']]);
        $challenge = $request->session()->get(SesionPanelService::claveReto());
        if (! $challenge) {
            return redirect()->route(SesionPanelService::rutaMfa())->with('error', 'Solicita primero un código.');
        }
        $response = $this->api->post('/auth/mfa/'.$challenge['factor'].'/verify', ['challenge_id' => $challenge['id']] + $data);
        if (! $response->exitosa()) {
            return redirect()->route(SesionPanelService::rutaMfa())->with('error', 'Código inválido o vencido. Intenta de nuevo.');
        }
        $tokens = $response->cuerpo['data'];
        $this->session->iniciar($request, $tokens + ['docente' => $this->session->docente(), 'mfa_required' => false]);
        $request->session()->forget(SesionPanelService::claveReto());

        return redirect()->route(SesionPanelService::rutaInicio())->with('exito', 'Segundo factor verificado.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validate(['factor' => ['required', 'uuid']]);
        $response = $this->api->solicitar('DELETE', '/auth/mfa/'.$data['factor']);

        return redirect()->route(SesionPanelService::rutaMfa())->with($response->exitosa() ? 'exito' : 'error', $response->exitosa() ? 'Factor retirado.' : 'Confirma el segundo factor antes de retirarlo.');
    }
}
