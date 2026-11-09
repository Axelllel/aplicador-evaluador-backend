<?php

/**
 * Archivo: ResultadoController.php
 * Módulo: app/Http/Controllers/Web
 * Descripción: Resultados de los exámenes: tabla de intentos por examen, detalle de un intento con sus respuestas e
 *              incidencias, calificación manual con motivo y autocalificación de opción múltiple
 *              (Manual COD-WEB, secciones 6.16, 6.17, 6.18 y 8.3).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\RespondeConApi;
use App\Http\Requests\CalificarRespuestaRequest;
use App\Services\ConsultasPanelService;
use App\Services\PaginadorPanel;
use App\Services\PanelApiService;
use App\Services\ResultadosPanelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Controla las páginas de Resultados y la calificación de respuestas.
 */
class ResultadoController extends Controller
{
    use RespondeConApi;

    public function __construct(
        private readonly PanelApiService $api,
        private readonly ConsultasPanelService $consultas,
        private readonly ResultadosPanelService $resultados,
        private readonly PaginadorPanel $paginador,
    ) {
    }

    /**
     * Muestra el selector de examen y la tabla de intentos del examen elegido (15 filas por página); sin examen
     * elegido usa el más reciente de los publicados.
     */
    public function index(Request $solicitud): View
    {
        $grupos = $this->consultas->grupos()->pluck('materia', 'id');
        $publicados = $this->consultas->examenes()->where('publicado', true)->values();

        $examenId = $solicitud->filled('examen') ? $solicitud->integer('examen') : (int) ($publicados->first()['id'] ?? 0);
        $examen = $publicados->firstWhere('id', $examenId);

        abort_if($solicitud->filled('examen') && $examen === null, 404);

        $filas = collect();
        $datos = $examen !== null ? $this->consultas->resultadosDelExamen($examenId) : null;

        if ($datos !== null) {
            $filas = $this->resultados->filas($datos['resultados'], $this->consultas->intentosDelExamen($examenId));
        }

        return view('resultados.index', [
            'opciones' => $publicados->mapWithKeys(fn (array $fila): array => [
                $fila['id'] => __('resultados.opcion_examen', ['examen' => $fila['nombre'], 'grupo' => $grupos[$fila['grupo_id']] ?? '—']),
            ])->all(),
            'examen' => $examen,
            'resumen' => $this->resultados->resumen($filas),
            'intentos' => $this->paginador->paginar($filas, $solicitud->integer('page', 1)),
        ]);
    }

    /**
     * Muestra el detalle de un intento: nota actual, respuestas con su formulario de calificación e incidencias.
     */
    public function intento(Request $solicitud, int $examen, int $intento): View
    {
        $detalle = $this->resultados->intento($examen, $intento);

        abort_if($detalle === null, 404);

        return view('resultados.intento', [
            'examenId' => $examen,
            'intentoId' => $intento,
            'detalle' => $detalle,
            'incidencias' => $this->paginador->paginar(
                $detalle['incidencias'],
                $solicitud->integer('pagina_incidencias', 1),
                'pagina_incidencias',
            ),
        ]);
    }

    /**
     * Guarda la calificación de una respuesta; si ya tenía nota, el motivo del cambio es obligatorio.
     *
     * @throws ValidationException
     */
    public function calificar(CalificarRespuestaRequest $solicitud, int $examen, int $intento, int $respuesta): RedirectResponse
    {
        $actual = $this->resultados->respuestaDelIntento($examen, $intento, $respuesta);

        abort_if($actual === null, 404);

        $datos = $solicitud->validated();
        $motivo = trim((string) ($datos['motivo'] ?? ''));

        if ($actual['calificacion_final'] !== null && $motivo === '') {
            throw ValidationException::withMessages(['motivo' => __('resultados.errores.motivo_obligatorio')])
                ->errorBag(CalificarRespuestaRequest::PREFIJO_DE_BOLSA.$respuesta);
        }

        $cuerpo = ['calificacion' => (float) $datos['calificacion']] + ($motivo !== '' ? ['motivo' => $motivo] : []);

        return $this->redirigirSegunRespuesta(
            $this->api->patch('/teacher/answers/'.$respuesta.'/grade', $cuerpo),
            redirect(route('panel.resultados.intento', ['examen' => $examen, 'intento' => $intento]).'#respuesta-'.$respuesta),
            __('mensajes.exito.calificacion_guardada'),
            null,
            __('mensajes.error.calificacion_no_guardada'),
        );
    }

    /**
     * Califica de forma automática las respuestas de opción múltiple que aún no tienen nota.
     */
    public function autocalificar(int $examen, int $intento): RedirectResponse
    {
        $respuesta = $this->api->post('/teacher/attempts/'.$intento.'/auto-grade');
        $cantidad = (int) $respuesta->dato('preguntas_calificadas', 0);

        return $this->redirigirSegunRespuesta(
            $respuesta,
            redirect()->route('panel.resultados.intento', ['examen' => $examen, 'intento' => $intento]),
            trans_choice('mensajes.exito.autocalificado', $cantidad, ['cantidad' => $cantidad]),
            null,
            __('mensajes.error.autocalificacion_fallida'),
        );
    }
}
