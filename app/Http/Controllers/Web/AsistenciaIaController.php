<?php

/**
 * Archivo: AsistenciaIaController.php
 * Módulo: app/Http/Controllers/Web
 * Descripción: Atiende las solicitudes web de asistencia de IA del panel docente.
 * Autor: Axel Rebollo Zeferino
 * Creado: 20/11/2026
 * Modificado: 23/11/2026 · Leonardo Ballestero López · Cabecera de archivo
 */

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerarPreguntasIaRequest;
use App\Services\PanelApiService;
use App\Services\ResultadosPanelService;
use App\Services\SesionPanelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AsistenciaIaController extends Controller
{
    public function generate(GenerarPreguntasIaRequest $request, PanelApiService $api, SesionPanelService $session): RedirectResponse
    {
        $response = $api->post('/teacher/ai/questions', $request->validated());
        $back = redirect()->to(route('panel.banco-de-preguntas').'#generar-con-ia');
        if ($response->exitosa() && is_array($response->dato('preguntas'))) {
            $request->session()->put('ia_borradores', ['docente' => $session->docente()['id'], 'expira' => time() + 1200, 'preguntas' => $response->dato('preguntas')]);

            return $back;
        }

        return $back->withInput($request->validated())->with('ia_error', $response->cuerpo['message'] ?? __('ia.error'));
    }

    public function useDraft(Request $request, int $indice, SesionPanelService $session): RedirectResponse
    {
        $drafts = $request->session()->get('ia_borradores', []);
        abort_unless(($drafts['docente'] ?? null) === ($session->docente()['id'] ?? null) && ($drafts['expira'] ?? 0) > time(), 404);
        $question = $drafts['preguntas'][$indice] ?? null;
        abort_unless(is_array($question), 404);
        $options = $question['opciones'] ?? [];
        $fields = ['tipo' => $question['tipo'], 'enunciado' => $question['enunciado'], 'respuesta_modelo' => $question['respuesta_modelo']];
        if ($question['tipo'] === 'opcion_multiple') {
            $fields['opciones'] = implode("\n", $options);
            $fields['opciones_lista'] = $options;
            $fields['correcta'] = array_search($question['respuesta_modelo'], $options, true);
        } elseif ($question['tipo'] === 'relacion_columnas') {
            $fields['opciones'] = implode("\n", array_map(fn (array $pair): string => $pair['izquierda'].' | '.$pair['derecha'], $options));
            $fields['pares_lista'] = $options;
        }

        return redirect()->to(route('panel.banco-de-preguntas').'#nueva-pregunta')->withInput($fields);
    }

    public function suggest(Request $request, int $examen, int $intento, int $respuesta, PanelApiService $api, ResultadosPanelService $results): RedirectResponse
    {
        abort_if($results->respuestaDelIntento($examen, $intento, $respuesta) === null, 404);
        $response = $api->post('/teacher/ai/answers/'.$respuesta);
        $back = redirect()->to(route('panel.resultados.intento', ['examen' => $examen, 'intento' => $intento]).'#respuesta-'.$respuesta);
        if (! $response->exitosa()) {
            return $back->with('ia_respuesta', $respuesta)->with('ia_error', $response->cuerpo['message'] ?? __('ia.error'));
        }

        return $back;
    }
}
