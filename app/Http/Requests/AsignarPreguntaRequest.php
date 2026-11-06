<?php

/**
 * Archivo: AsignarPreguntaRequest.php
 * Módulo: app/Http/Requests
 * Descripción: Valida la asignación de una pregunta del banco a un apartado del examen
 *              (apartado, pregunta, tiempo en segundos y ponderación) (Manual COD-WEB, secciones 6.10 y 6.18).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el formulario para agregar una pregunta a un apartado.
 */
class AsignarPreguntaRequest extends FormRequest
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
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'apartado_id' => ['required', 'integer', 'min:1'],
            'pregunta_id' => ['required', 'integer', 'min:1'],
            'tiempo_segundos' => ['required', 'integer', 'min:1', 'max:7200'],
            'ponderacion' => ['required', 'numeric', 'gt:0', 'lte:100', 'decimal:0,2'],
        ];
    }
}
