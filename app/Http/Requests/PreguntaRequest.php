<?php

/**
 * Archivo: PreguntaRequest.php
 * Módulo: app/Http/Requests
 * Descripción: Valida el alta de una pregunta del banco y arma el JSON que espera la API según el tipo
 *              (opción múltiple, relación de columnas, abierta o programación)
 *              (Manual COD-WEB, secciones 6.10 y 6.18).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 19/11/2026 · Axel Rebollo Zeferino · Mejora de espacios de trabajo y editor estructurado
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\ReglasPregunta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Valida el formulario de nueva pregunta.
 *
 * Formato acordado para `opciones` (la API acepta cualquier arreglo; el formato definitivo con la
 * aplicación móvil sigue pendiente):
 *  - opcion_multiple: lista de textos; `respuesta_modelo` es el texto de la opción correcta.
 *  - relacion_columnas: lista de pares {izquierda, derecha}; `respuesta_modelo` resume los pares.
 *  - abierta y programacion: sin opciones; `respuesta_modelo` es la respuesta o el código de referencia.
 */
class PreguntaRequest extends FormRequest
{
    public const TIPOS = ['opcion_multiple', 'relacion_columnas', 'abierta', 'programacion'];

    private const MAXIMO_DE_OPCIONES = ReglasPregunta::MAXIMO_DE_OPCIONES;

    /**
     * La sesión ya la exige el middleware panel.sesion.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Define las reglas de validación; los mensajes salen de lang/es/validation.php.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tipo = $this->input('tipo');
        $estructurado = $this->boolean('editor_estructurado');

        return [
            'tipo' => ['required', Rule::in(self::TIPOS)],
            'editor_estructurado' => ['nullable', 'boolean'],
            'opciones_lista' => [Rule::requiredIf($estructurado && $tipo === 'opcion_multiple'), 'nullable', 'array', 'list', 'min:2', 'max:10'],
            'opciones_lista.*' => ['required', 'string', 'max:2000', 'not_regex:/[\r\n]/'],
            'correcta' => [Rule::requiredIf($estructurado && $tipo === 'opcion_multiple'), 'nullable', 'integer', 'min:0', 'max:9'],
            'pares_lista' => [Rule::requiredIf($estructurado && $tipo === 'relacion_columnas'), 'nullable', 'array', 'list', 'min:2', 'max:10'],
            'pares_lista.*.izquierda' => ['required', 'string', 'max:2000'],
            'pares_lista.*.derecha' => ['required', 'string', 'max:2000'],
            'enunciado' => ReglasPregunta::enunciado(),
            'lenguaje' => [
                Rule::requiredIf($tipo === 'programacion'),
                'nullable', 'string', Rule::in([...ReglasPregunta::LENGUAJES, 'otro', 'libre']),
            ],
            'lenguaje_otro' => [
                Rule::requiredIf($tipo === 'programacion' && $this->input('lenguaje') === 'otro'),
                'nullable', 'string', 'max:40', 'regex:/^[\p{L}\p{N} .+#\-]+$/u',
            ],
            'opciones' => [
                Rule::requiredIf(! $estructurado && in_array($tipo, ['opcion_multiple', 'relacion_columnas'], true)),
                'nullable', 'string', 'max:5000',
            ],
            // Sin respuesta modelo el examen no se puede publicar, por eso se pide en todos los tipos
            // (en relación de columnas se arma sola con los pares).
            'respuesta_modelo' => [
                Rule::requiredIf($tipo !== 'relacion_columnas' && ! ($estructurado && $tipo === 'opcion_multiple')),
                'nullable', 'string', 'max:20000',
            ],
        ];
    }

    /**
     * Mensajes propios: nombran el campo en lenguaje del docente y no por su clave interna.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'opciones_lista.required' => __('preguntas.validacion.opciones_cantidad', ['maximo' => self::MAXIMO_DE_OPCIONES]),
            'opciones_lista.min' => __('preguntas.validacion.opciones_cantidad', ['maximo' => self::MAXIMO_DE_OPCIONES]),
            'opciones_lista.max' => __('preguntas.validacion.opciones_cantidad', ['maximo' => self::MAXIMO_DE_OPCIONES]),
            'opciones_lista.*.required' => __('preguntas.validacion.opcion_vacia', ['posicion' => ':position']),
            'opciones_lista.*.max' => __('preguntas.validacion.opcion_larga', ['posicion' => ':position']),
            'pares_lista.required' => __('preguntas.validacion.pares_invalidos', ['maximo' => self::MAXIMO_DE_OPCIONES]),
            'pares_lista.min' => __('preguntas.validacion.pares_invalidos', ['maximo' => self::MAXIMO_DE_OPCIONES]),
            'pares_lista.max' => __('preguntas.validacion.pares_invalidos', ['maximo' => self::MAXIMO_DE_OPCIONES]),
            'pares_lista.*.izquierda.required' => __('preguntas.validacion.concepto_vacio', ['posicion' => ':position']),
            'pares_lista.*.derecha.required' => __('preguntas.validacion.pareja_vacia', ['posicion' => ':position']),
            'correcta.required' => __('workspace.editor.correct_required'),
            'enunciado.regex' => __('preguntas.validacion.enunciado_sin_contenido'),
            'respuesta_modelo.required' => __('preguntas.validacion.respuesta_requerida'),
            'lenguaje.required' => __('preguntas.validacion.lenguaje_requerido'),
            'lenguaje_otro.required' => __('preguntas.validacion.lenguaje_otro_requerido'),
            'lenguaje_otro.regex' => __('preguntas.validacion.lenguaje_otro_invalido'),
        ];
    }

    /**
     * Valida el contenido de las opciones, que depende del tipo de pregunta.
     */
    public function withValidator(Validator $validador): void
    {
        $validador->after(function (Validator $validador): void {
            if ($validador->errors()->isNotEmpty()) {
                return;
            }

            $tipo = (string) $this->input('tipo');

            if ($this->boolean('editor_estructurado') && $tipo === 'opcion_multiple') {
                $opciones = $this->lineasDeOpciones();
                if (! array_key_exists((int) $this->input('correcta'), $opciones)) {
                    $validador->errors()->add('correcta', __('workspace.editor.correct_required'));
                }
                if (ReglasPregunta::hayRepetidos($opciones)) {
                    $validador->errors()->add('opciones_lista', __('workspace.editor.duplicate'));
                }

                return;
            }

            if ($tipo === 'opcion_multiple') {
                $opciones = $this->lineasDeOpciones();

                if (count($opciones) < 2 || count($opciones) > self::MAXIMO_DE_OPCIONES) {
                    $validador->errors()->add('opciones', __('preguntas.validacion.opciones_cantidad', ['maximo' => self::MAXIMO_DE_OPCIONES]));
                } elseif (ReglasPregunta::hayRepetidos($opciones)) {
                    $validador->errors()->add('opciones', __('preguntas.validacion.opciones_repetidas'));
                } elseif (! in_array(trim((string) $this->input('respuesta_modelo')), $opciones, true)) {
                    $validador->errors()->add('respuesta_modelo', __('preguntas.validacion.respuesta_en_opciones'));
                }
            }

            if ($tipo === 'relacion_columnas') {
                $pares = $this->paresDeColumnas();

                if ($pares === null) {
                    $validador->errors()->add('opciones', __('preguntas.validacion.pares_invalidos', ['maximo' => self::MAXIMO_DE_OPCIONES]));
                } elseif (ReglasPregunta::hayParesRepetidos($pares)) {
                    $validador->errors()->add($this->boolean('editor_estructurado') ? 'pares_lista' : 'opciones', __('preguntas.validacion.pares_repetidos'));
                }
            }
        });
    }

    /**
     * Devuelve el JSON que espera POST /api/v1/teacher/questions.
     *
     * @return array<string, mixed>
     */
    public function datosParaLaApi(): array
    {
        $tipo = (string) $this->input('tipo');
        $datos = [
            'tipo' => $tipo,
            'enunciado' => trim((string) $this->input('enunciado')),
        ];

        if ($tipo === 'opcion_multiple') {
            $datos['opciones'] = $this->lineasDeOpciones();
            $datos['respuesta_modelo'] = $this->boolean('editor_estructurado')
                ? $datos['opciones'][(int) $this->input('correcta')]
                : trim((string) $this->input('respuesta_modelo'));
        } elseif ($tipo === 'relacion_columnas') {
            $pares = $this->paresDeColumnas() ?? [];
            $datos['opciones'] = $pares;
            $datos['respuesta_modelo'] = trim((string) $this->input('respuesta_modelo')) !== ''
                ? trim((string) $this->input('respuesta_modelo'))
                : implode("\n", array_map(fn (array $par): string => $par['izquierda'].' = '.$par['derecha'], $pares));
        } else {
            $datos['respuesta_modelo'] = trim((string) $this->input('respuesta_modelo'));
        }

        if ($tipo === 'programacion') {
            $datos['lenguaje'] = ReglasPregunta::lenguajeFinal(
                (string) $this->input('lenguaje'),
                (string) $this->input('lenguaje_otro'),
            );
        }

        return $datos;
    }

    /**
     * Devuelve las líneas no vacías del campo de opciones.
     *
     * @return list<string>
     */
    private function lineasDeOpciones(): array
    {
        if ($this->boolean('editor_estructurado') && $this->input('tipo') === 'opcion_multiple') {
            return array_map('trim', $this->input('opciones_lista', []));
        }

        $lineas = preg_split('/\R/u', (string) $this->input('opciones')) ?: [];

        return array_values(array_filter(array_map('trim', $lineas), fn (string $linea): bool => $linea !== ''));
    }

    /**
     * Convierte cada línea «izquierda | derecha» en un par; devuelve null si alguna línea es inválida.
     *
     * @return list<array{izquierda: string, derecha: string}>|null
     */
    private function paresDeColumnas(): ?array
    {
        if ($this->boolean('editor_estructurado')) {
            return array_map(fn (array $par): array => [
                'izquierda' => trim($par['izquierda']),
                'derecha' => trim($par['derecha']),
            ], $this->input('pares_lista', []));
        }

        $lineas = $this->lineasDeOpciones();

        if (count($lineas) < 2 || count($lineas) > self::MAXIMO_DE_OPCIONES) {
            return null;
        }

        $pares = [];

        foreach ($lineas as $linea) {
            $partes = array_map('trim', explode('|', $linea, 2));

            if (count($partes) !== 2 || $partes[0] === '' || $partes[1] === '') {
                return null;
            }

            $pares[] = ['izquierda' => $partes[0], 'derecha' => $partes[1]];
        }

        return $pares;
    }
}
