<?php

/**
 * Archivo: GuiaEstiloController.php
 * Módulo: app/Http/Controllers/Web
 * Descripción: Muestra la guía de estilo con todos los elementos visuales del panel
 *              para auditarlos (Manual COD-WEB, secciones 6.14 a 6.19).
 * Autor: Leonardo Ballestero López
 * Creado: 27/10/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Restringida al entorno local
 */

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\AccionPanel;
use App\Http\Controllers\Controller;
use App\Services\CodigoQrService;
use App\Services\PaletaColoresService;
use App\Services\PanelEjemploService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controla la guía de estilo del panel.
 */
class GuiaEstiloController extends Controller
{
    private const FOLIO_DE_EJEMPLO = 'ABC-1234';

    public function __construct(
        private readonly PaletaColoresService $paleta,
        private readonly CodigoQrService $codigoQr,
        private readonly PanelEjemploService $ejemplo,
    ) {
    }

    /**
     * Muestra la guía de estilo.
     */
    public function index(Request $request): View
    {
        // Herramienta de diseño: solo existe en desarrollo (APP_ENV=local) y en las pruebas.
        abort_unless(app()->environment(['local', 'testing']), 404);

        $svg = $this->codigoQr->generarSvg(self::FOLIO_DE_EJEMPLO);

        return view('guia-estilo.index', [
            'colores' => $this->paleta->colores(),
            'pares' => $this->paleta->pares(),
            'hallazgos' => $this->paleta->hallazgos(),
            'acciones' => AccionPanel::cases(),
            'folio' => self::FOLIO_DE_EJEMPLO,
            'qrSvg' => $svg,
            'qrDescarga' => $this->codigoQr->comoUriDeDescarga($svg),
            'examenes' => $this->ejemplo->examenes($request->integer('pagina_tabla', 1), 'pagina_tabla')
                ->withQueryString()
                ->fragment('tabla'),
        ]);
    }
}
