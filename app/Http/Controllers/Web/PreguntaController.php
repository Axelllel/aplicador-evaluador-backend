<?php

/**
 * Archivo: PreguntaController.php
 * Módulo: app/Http/Controllers/Web
 * Descripción: Banco de preguntas del docente: lista paginada con filtro por tipo y alta de preguntas
 *              de los cuatro tipos (Manual COD-WEB, secciones 6.16, 6.17 y 8.3).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 19/11/2026 · Axel Rebollo Zeferino · Mejora de espacios de trabajo y editor estructurado
 */

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\RespondeConApi;
use App\Http\Requests\PreguntaRequest;
use App\Services\ConsultasPanelService;
use App\Services\PaginadorPanel;
use App\Services\PanelApiService;
use App\Services\SesionPanelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Controla el banco de preguntas.
 */
class PreguntaController extends Controller
{
    use RespondeConApi;

    public function __construct(
        private readonly PanelApiService $api,
        private readonly ConsultasPanelService $consultas,
        private readonly PaginadorPanel $paginador,
    ) {}

    /**
     * Muestra la lista de preguntas (15 por página) y el formulario de alta.
     */
    public function index(Request $solicitud): View
    {
        $tipo = $solicitud->query('tipo');
        $tipo = is_string($tipo) && in_array($tipo, PreguntaRequest::TIPOS, true) ? $tipo : null;

        $busqueda = $solicitud->query('q');
        $busqueda = is_string($busqueda) ? mb_substr(trim($busqueda), 0, 150) : '';
        $preguntas = $this->consultas->preguntas($tipo);
        if ($busqueda !== '') {
            $preguntas = $preguntas->filter(fn (array $pregunta): bool => mb_stripos($pregunta['enunciado'], $busqueda) !== false)->values();
        }

        return view('preguntas.index', [
            'preguntas' => $this->paginador->paginar($preguntas, $solicitud->integer('page', 1)),
            'tipoFiltrado' => $tipo,
            'busqueda' => $busqueda,
            'iaOperacion' => (string) Str::uuid(),
            'iaBorradores' => $this->borradoresIa($solicitud),
        ]);
    }

    private function borradoresIa(Request $request): array
    {
        $drafts = $request->session()->get('ia_borradores', []);
        if (($drafts['docente'] ?? null) !== (app(SesionPanelService::class)->docente()['id'] ?? null) || ($drafts['expira'] ?? 0) <= time()) {
            return [];
        }

        return $drafts['preguntas'] ?? [];
    }

    /**
     * Agrega la pregunta al banco con la API.
     */
    public function store(PreguntaRequest $solicitud): RedirectResponse
    {
        $respuesta = $this->api->post('/teacher/questions', $solicitud->datosParaLaApi());

        return $this->redirigirSegunRespuesta(
            $respuesta,
            redirect()->route('panel.banco-de-preguntas'),
            __('mensajes.exito.pregunta_creada'),
            'preguntas',
            __('mensajes.error.pregunta_no_guardada'),
        );
    }
}
