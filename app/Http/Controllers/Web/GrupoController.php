<?php

/**
 * Archivo: GrupoController.php
 * Módulo: app/Http/Controllers/Web
 * Descripción: Lista los grupos del docente con su folio y su código QR, y crea grupos nuevos;
 *              maneja el límite del plan (402) y la falta de plan activo (403)
 *              (Manual COD-WEB, secciones 6.16, 6.17 y 8.4).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\RespondeConApi;
use App\Http\Requests\GrupoRequest;
use App\Services\CodigoQrService;
use App\Services\ConsultasPanelService;
use App\Services\PanelApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Controla la lista y el alta de grupos.
 */
class GrupoController extends Controller
{
    use RespondeConApi;

    public function __construct(
        private readonly PanelApiService $api,
        private readonly ConsultasPanelService $consultas,
        private readonly CodigoQrService $codigoQr,
    ) {
    }

    /**
     * Muestra el formulario de nuevo grupo y los grupos con su folio y QR.
     */
    public function index(): View
    {
        $grupos = $this->consultas->grupos()
            ->sortByDesc('id')
            ->values()
            ->map(function (array $grupo): array {
                $svg = $this->codigoQr->generarSvg((string) $grupo['folio_codigo']);

                return $grupo + [
                    'qr_svg' => $svg,
                    'qr_descarga' => $this->codigoQr->comoUriDeDescarga($svg),
                ];
            });

        return view('grupos.index', ['grupos' => $grupos]);
    }

    /**
     * Crea el grupo con la API; el folio lo genera el backend.
     */
    public function store(GrupoRequest $solicitud): RedirectResponse
    {
        $respuesta = $this->api->post('/teacher/groups', $solicitud->validated());

        return $this->redirigirSegunRespuesta(
            $respuesta,
            redirect()->route('panel.grupos'),
            __('mensajes.exito.grupo_creado'),
            'grupos',
        );
    }
}
