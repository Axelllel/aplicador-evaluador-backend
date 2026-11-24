<?php

/**
 * Archivo: ConfirmarRetiroRequest.php
 * Módulo: app/Http/Requests
 * Descripción: Exige confirmación explícita antes de retirar alumnos o grupos propios.
 * Autor: Axel Rebollo Zeferino
 * Creado: 24/11/2026
 * Modificado: 24/11/2026 · Equipo BigBitByte · Gestión de grupos
 */

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmarRetiroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['confirmacion' => ['required', 'string', Rule::in(['ELIMINAR'])]];
    }

    public function messages(): array
    {
        return ['confirmacion.required' => 'Escribe ELIMINAR para confirmar.', 'confirmacion.in' => 'Escribe exactamente ELIMINAR para confirmar.'];
    }
}
