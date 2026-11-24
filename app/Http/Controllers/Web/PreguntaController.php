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
use App\Support\ReglasPregunta;
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
            'valores' => $solicitud->session()->getOldInput(),
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

    /**
     * Muestra el formulario con los datos de una pregunta propia que todavía no está en ningún examen.
     */
    public function editar(Request $solicitud, int $pregunta): View|RedirectResponse
    {
        $fila = $this->consultas->preguntas()->firstWhere('id', $pregunta);

        if ($fila === null) {
            abort(404);
        }

        if (($fila['en_uso'] ?? false) === true) {
            return redirect()->route('panel.banco-de-preguntas')->with('aviso', __('preguntas.validacion.en_uso'));
        }

        return view('preguntas.editar', [
            'pregunta' => $fila,
            // Tras un error de validación se conserva lo que el docente había escrito.
            'valores' => $solicitud->session()->hasOldInput() ? $solicitud->session()->getOldInput() : $this->valoresParaEditar($fila),
        ]);
    }

    /**
     * Guarda los cambios de la pregunta.
     */
    public function actualizar(PreguntaRequest $solicitud, int $pregunta): RedirectResponse
    {
        $respuesta = $this->api->solicitar('PUT', '/teacher/questions/'.$pregunta, $solicitud->datosParaLaApi());

        return $this->redirigirSegunRespuesta(
            $respuesta,
            redirect()->route('panel.banco-de-preguntas'),
            __('mensajes.exito.pregunta_actualizada'),
            'preguntas',
            __('mensajes.error.pregunta_no_actualizada'),
        );
    }

    /**
     * Elimina la pregunta si no forma parte de ningún examen.
     */
    public function eliminar(int $pregunta): RedirectResponse
    {
        return $this->redirigirSegunRespuesta(
            $this->api->solicitar('DELETE', '/teacher/questions/'.$pregunta),
            redirect()->route('panel.banco-de-preguntas'),
            __('mensajes.exito.pregunta_eliminada'),
            null,
            __('mensajes.error.pregunta_no_eliminada'),
        );
    }

    /**
     * Convierte una pregunta guardada en los valores del formulario del editor.
     *
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private function valoresParaEditar(array $fila): array
    {
        $tipo = (string) $fila['tipo'];
        $opciones = is_array($fila['opciones'] ?? null) ? $fila['opciones'] : [];
        $valores = ['tipo' => $tipo, 'enunciado' => (string) $fila['enunciado'], 'respuesta_modelo' => (string) ($fila['respuesta_modelo'] ?? '')];

        if ($tipo === 'opcion_multiple') {
            $valores['opciones_lista'] = array_values(array_map('strval', $opciones));
            $posicion = array_search($valores['respuesta_modelo'], $valores['opciones_lista'], true);
            $valores['correcta'] = $posicion === false ? null : $posicion;
        } elseif ($tipo === 'relacion_columnas') {
            $valores['pares_lista'] = array_values(array_map(
                fn (mixed $par): array => ['izquierda' => (string) ($par['izquierda'] ?? ''), 'derecha' => (string) ($par['derecha'] ?? '')],
                $opciones,
            ));
        } elseif ($tipo === 'programacion') {
            $lenguaje = ReglasPregunta::eleccionDesdeGuardado($fila['lenguaje'] ?? null);
            $valores['lenguaje'] = $lenguaje['eleccion'];
            $valores['lenguaje_otro'] = $lenguaje['otro'];
        }

        return $valores;
    }
}
