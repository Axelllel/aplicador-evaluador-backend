<?php

/**
 * Archivo: GenerarPreguntasIaRequest.php
 * Módulo: app/Http/Requests
 * Descripción: Valida la solicitud de generación de preguntas con IA.
 * Autor: Axel Rebollo Zeferino
 * Creado: 20/11/2026
 * Modificado: 23/11/2026 · Leonardo Ballestero López · Cabecera de archivo
 */

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerarPreguntasIaRequest extends FormRequest
{
    protected $errorBag = 'ia';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'tema' => ['required', 'string', 'min:3', 'max:200'],
            'tipo' => ['required', Rule::in(PreguntaRequest::TIPOS)],
            'nivel' => ['required', Rule::in(['basico', 'intermedio', 'avanzado'])],
            'cantidad' => ['required', 'integer', 'between:1,5'],
            'contexto' => ['nullable', 'string', 'max:6000'],
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('panel.banco-de-preguntas').'#generar-con-ia';
    }
}
