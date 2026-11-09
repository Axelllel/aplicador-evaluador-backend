<?php

/**
 * Archivo: ApartadoRequest.php
 * Módulo: app/Http/Requests
 * Descripción: Valida el alta de un apartado de examen (tipo de pregunta y cantidad de preguntas)
 *              (Manual COD-WEB, secciones 6.10 y 6.18).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el formulario de nuevo apartado.
 */
class ApartadoRequest extends FormRequest
{
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
        return [
            'tipo' => ['required', Rule::in(PreguntaRequest::TIPOS)],
            'cantidad_preguntas' => ['required', 'integer', 'min:1', 'max:200'],
        ];
    }
}
