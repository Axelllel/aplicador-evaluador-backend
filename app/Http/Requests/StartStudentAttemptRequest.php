<?php

/**
 * Archivo: StartStudentAttemptRequest.php
 * Módulo: API de alumnos
 * Descripción: Valida la credencial personal usada para iniciar un examen móvil.
 * Autor: Axel Rebollo Zeferino
 * Creado: 20/11/2026
 */

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StartStudentAttemptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'folio_codigo' => ['required', 'string', 'regex:/\A[A-Za-z0-9]{4,20}\z/'],
            'codigo_acceso' => ['required', 'string', 'regex:/\A[A-Za-z]{4}[0-9]{2}\z/'],
        ];
    }
}
