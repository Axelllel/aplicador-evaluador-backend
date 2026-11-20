<?php

/**
 * Archivo: PonderacionPreguntaRequest.php
 * Módulo: app/Http/Requests
 * Descripción: Valida la ponderación de una pregunta dentro de un apartado de examen en borrador.
 * Autor: Axel (Axelllel)
 * Creado: 08/11/2026
 * Modificado: 20/11/2026 · Leonardo Ballestero López · Prólogo del Manual COD-WEB 6.7
 */

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PonderacionPreguntaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'nueva_ponderacion' => ['required', 'numeric', 'gt:0', 'lte:100', 'decimal:0,2'],
        ];
    }
}
