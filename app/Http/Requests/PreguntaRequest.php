<?php

/**
 * Archivo: PreguntaRequest.php
 * Módulo: app/Http/Requests
 * Descripción: Valida el alta de una pregunta del banco y arma el JSON que espera la API según el tipo
 *              (opción múltiple, relación de columnas, abierta o programación)
 *              (Manual COD-WEB, secciones 6.10 y 6.18).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Http\Requests;

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

    private const MAXIMO_DE_OPCIONES = 10;

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

        return [
            'tipo' => ['required', Rule::in(self::TIPOS)],
            'enunciado' => ['required', 'string', 'min:3', 'max:10000'],
            'opciones' => [
                Rule::requiredIf(in_array($tipo, ['opcion_multiple', 'relacion_columnas'], true)),
                'nullable', 'string', 'max:5000',
            ],
            // Sin respuesta modelo el examen no se puede publicar, por eso se pide en todos los tipos
            // (en relación de columnas se arma sola con los pares).
            'respuesta_modelo' => [
                Rule::requiredIf($tipo !== 'relacion_columnas'),
                'nullable', 'string', 'max:20000',
            ],
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

            if ($tipo === 'opcion_multiple') {
                $opciones = $this->lineasDeOpciones();

                if (count($opciones) < 2 || count($opciones) > self::MAXIMO_DE_OPCIONES) {
                    $validador->errors()->add('opciones', __('preguntas.validacion.opciones_cantidad', ['maximo' => self::MAXIMO_DE_OPCIONES]));
                } elseif (! in_array(trim((string) $this->input('respuesta_modelo')), $opciones, true)) {
                    $validador->errors()->add('respuesta_modelo', __('preguntas.validacion.respuesta_en_opciones'));
                }
            }

            if ($tipo === 'relacion_columnas' && $this->paresDeColumnas() === null) {
                $validador->errors()->add('opciones', __('preguntas.validacion.pares_invalidos', ['maximo' => self::MAXIMO_DE_OPCIONES]));
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
            $datos['respuesta_modelo'] = trim((string) $this->input('respuesta_modelo'));
        } elseif ($tipo === 'relacion_columnas') {
            $pares = $this->paresDeColumnas() ?? [];
            $datos['opciones'] = $pares;
            $datos['respuesta_modelo'] = trim((string) $this->input('respuesta_modelo')) !== ''
                ? trim((string) $this->input('respuesta_modelo'))
                : implode("\n", array_map(fn (array $par): string => $par['izquierda'].' = '.$par['derecha'], $pares));
        } else {
            $datos['respuesta_modelo'] = trim((string) $this->input('respuesta_modelo'));
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
