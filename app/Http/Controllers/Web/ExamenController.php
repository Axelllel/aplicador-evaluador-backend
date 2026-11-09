<?php

/**
 * Archivo: ExamenController.php
 * Módulo: app/Http/Controllers/Web
 * Descripción: Exámenes del docente: lista paginada, alta como borrador y publicación
 *              (Manual COD-WEB, secciones 6.16, 6.17 y 8.3).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\RespondeConApi;
use App\Http\Requests\ExamenRequest;
use App\Services\ConsultasPanelService;
use App\Services\ExamenesPanelService;
use App\Services\PaginadorPanel;
use App\Services\PanelApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controla la lista, el alta y la publicación de exámenes.
 */
class ExamenController extends Controller
{
    use RespondeConApi;

    public function __construct(
        private readonly PanelApiService $api,
        private readonly ConsultasPanelService $consultas,
        private readonly ExamenesPanelService $examenes,
        private readonly PaginadorPanel $paginador,
    ) {
    }

    /**
     * Muestra la tabla de exámenes (15 filas por página) y el formulario de nuevo examen.
     */
    public function index(Request $solicitud): View
    {
        $grupos = $this->consultas->grupos();

        return view('examenes.index', [
            'examenes' => $this->paginador->paginar(
                $this->examenes->filas($this->consultas->examenes(), $grupos),
                $solicitud->integer('page', 1),
            ),
            'gruposActivos' => $grupos->where('activo', true)->sortBy('materia')->pluck('materia', 'id')->all(),
        ]);
    }

    /**
     * Crea el examen como borrador y abre su pantalla de apartados.
     */
    public function store(ExamenRequest $solicitud): RedirectResponse
    {
        $respuesta = $this->api->post('/teacher/exams', $solicitud->datosParaLaApi());

        $destino = $respuesta->exitosa()
            ? redirect()->route('panel.examenes.apartados', ['examen' => (int) $respuesta->dato('id')])
            : redirect()->route('panel.examenes');

        return $this->redirigirSegunRespuesta(
            $respuesta,
            $destino,
            __('mensajes.exito.examen_creado'),
            null,
            __('mensajes.error.examen_no_guardado'),
        );
    }

    /**
     * Publica el examen; la API revisa que esté completo y devuelve el motivo si no lo está.
     */
    public function publicar(int $examen): RedirectResponse
    {
        $respuesta = $this->api->post('/teacher/exams/'.$examen.'/publish');

        return $this->redirigirSegunRespuesta(
            $respuesta,
            redirect()->back(302, [], route('panel.examenes')),
            __('mensajes.exito.examen_publicado'),
            null,
            __('mensajes.error.examen_no_publicado'),
        );
    }

    public function eliminar(int $examen): RedirectResponse
    {
        return $this->redirigirSegunRespuesta(
            $this->api->solicitar('DELETE', '/teacher/exams/'.$examen),
            redirect()->route('panel.examenes'),
            __('mensajes.exito.examen_eliminado'),
            null,
            __('mensajes.error.examen_no_eliminado'),
        );
    }
}
