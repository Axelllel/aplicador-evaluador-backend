<?php

/**
 * Archivo: PreguntaController.php
 * Módulo: app/Http/Controllers/Web
 * Descripción: Banco de preguntas del docente: lista paginada con filtro por tipo y alta de preguntas
 *              de los cuatro tipos (Manual COD-WEB, secciones 6.16, 6.17 y 8.3).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\RespondeConApi;
use App\Http\Requests\PreguntaRequest;
use App\Services\ConsultasPanelService;
use App\Services\PaginadorPanel;
use App\Services\PanelApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    ) {
    }

    /**
     * Muestra la lista de preguntas (15 por página) y el formulario de alta.
     */
    public function index(Request $solicitud): View
    {
        $tipo = $solicitud->query('tipo');
        $tipo = is_string($tipo) && in_array($tipo, PreguntaRequest::TIPOS, true) ? $tipo : null;

        return view('preguntas.index', [
            'preguntas' => $this->paginador->paginar($this->consultas->preguntas($tipo), $solicitud->integer('page', 1)),
            'tipoFiltrado' => $tipo,
        ]);
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
