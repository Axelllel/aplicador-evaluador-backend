<?php

/**
 * Archivo: ApartadoController.php
 * Módulo: app/Http/Controllers/Web
 * Descripción: Apartados de un examen: muestra sus apartados y preguntas, agrega apartados y vincula
 *              preguntas del banco con tiempo y ponderación (Manual COD-WEB, secciones 6.16 y 8.3).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\RespondeConApi;
use App\Http\Requests\ApartadoRequest;
use App\Http\Requests\AsignarPreguntaRequest;
use App\Services\ConsultasPanelService;
use App\Services\ExamenesPanelService;
use App\Services\PanelApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Controla los apartados y las preguntas de un examen.
 */
class ApartadoController extends Controller
{
    use RespondeConApi;

    public function __construct(
        private readonly PanelApiService $api,
        private readonly ConsultasPanelService $consultas,
        private readonly ExamenesPanelService $examenes,
    ) {
    }

    /**
     * Muestra el examen con sus apartados, el avance de la ponderación y los formularios para completarlo.
     */
    public function show(int $examen): View
    {
        $datosDelExamen = $this->consultas->examenes()->firstWhere('id', $examen);
        $apartados = $datosDelExamen === null ? null : $this->consultas->apartados($examen);

        abort_if($datosDelExamen === null || $apartados === null, 404);

        $fila = $this->examenes->filas(collect([$datosDelExamen]), $this->consultas->grupos())->first();
        $esBorrador = $fila['estado'] === 'borrador';

        $usadas = $apartados->flatMap(fn (array $apartado): array => array_column($apartado['preguntas'] ?? [], 'pregunta_id'));
        $ponderacionTotal = $apartados->sum(fn (array $apartado): float => array_sum(array_map(
            fn (array $pregunta): float => (float) $pregunta['ponderacion'],
            $apartado['preguntas'] ?? [],
        )));

        return view('examenes.apartados', [
            'examen' => $fila,
            'detalle' => $datosDelExamen,
            'apartados' => $apartados,
            'esBorrador' => $esBorrador,
            'ponderacionTotal' => round((float) $ponderacionTotal, 2),
            'apartadosConEspacio' => $esBorrador
                ? $apartados
                    ->filter(fn (array $apartado): bool => count($apartado['preguntas'] ?? []) < (int) $apartado['cantidad_preguntas'])
                    ->mapWithKeys(fn (array $apartado): array => [$apartado['id'] => __('examenes.apartados.opcion_apartado', [
                        'tipo' => __('preguntas.tipos.'.$apartado['tipo']),
                        'usadas' => count($apartado['preguntas'] ?? []),
                        'cantidad' => $apartado['cantidad_preguntas'],
                    ])])
                    ->all()
                : [],
            'preguntasDisponibles' => $esBorrador
                ? $this->consultas->preguntas()
                    ->reject(fn (array $pregunta): bool => $usadas->contains($pregunta['id']))
                    ->mapWithKeys(fn (array $pregunta): array => [$pregunta['id'] => __('examenes.apartados.opcion_pregunta', [
                        'tipo' => __('preguntas.tipos.'.$pregunta['tipo']),
                        'enunciado' => Str::limit((string) $pregunta['enunciado'], 70),
                    ])])
                    ->all()
                : [],
        ]);
    }

    /**
     * Agrega un apartado (tipo y cantidad de preguntas) al examen en borrador.
     */
    public function storeApartado(ApartadoRequest $solicitud, int $examen): RedirectResponse
    {
        $respuesta = $this->api->post('/teacher/exams/'.$examen.'/sections', $solicitud->validated());

        return $this->redirigirSegunRespuesta(
            $respuesta,
            redirect()->route('panel.examenes.apartados', ['examen' => $examen]),
            __('mensajes.exito.apartado_creado'),
            null,
            __('mensajes.error.apartado_no_guardado'),
        );
    }

    /**
     * Vincula una pregunta del banco a un apartado del examen.
     */
    public function asignarPregunta(AsignarPreguntaRequest $solicitud, int $examen): RedirectResponse
    {
        $datos = $solicitud->validated();

        $respuesta = $this->api->post(
            '/teacher/exams/'.$examen.'/sections/'.(int) $datos['apartado_id'].'/questions',
            [
                'pregunta_id' => (int) $datos['pregunta_id'],
                'tiempo_segundos' => (int) $datos['tiempo_segundos'],
                'ponderacion' => (float) $datos['ponderacion'],
            ],
        );

        return $this->redirigirSegunRespuesta(
            $respuesta,
            redirect()->route('panel.examenes.apartados', ['examen' => $examen]),
            __('mensajes.exito.pregunta_asignada'),
            null,
            __('mensajes.error.pregunta_no_asignada'),
        );
    }
}
