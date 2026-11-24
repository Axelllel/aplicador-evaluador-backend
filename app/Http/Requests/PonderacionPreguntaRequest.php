<?php

/**
 * Archivo: PonderacionPreguntaRequest.php
 * Módulo: app/Http/Requests
 * Descripción: Gestiona y verifica el módulo PonderacionPreguntaRequest.
 * Autor: Axel Rebollo Zeferino
 * Creado: 22/11/2026
 * Modificado: 22/11/2026 · Equipo BigBitByte · Integración de requisitos pendientes
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
