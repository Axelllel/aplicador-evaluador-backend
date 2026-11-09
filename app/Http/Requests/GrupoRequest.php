<?php

/**
 * Archivo: GrupoRequest.php
 * Módulo: app/Http/Requests
 * Descripción: Valida el formulario de nuevo grupo (materia de 2 a 150 caracteres)
 *              (Manual COD-WEB, secciones 6.10 y 6.18).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el formulario de nuevo grupo.
 */
class GrupoRequest extends FormRequest
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
            'materia' => ['required', 'string', 'min:2', 'max:150'],
        ];
    }
}
