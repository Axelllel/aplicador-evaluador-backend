<?php

/**
 * Archivo: IniciarSesionRequest.php
 * Módulo: app/Http/Requests
 * Descripción: Valida el correo y la contraseña del formulario de inicio de sesión
 *              (Manual COD-WEB, secciones 6.10 y 6.18).
 * Autor: Leonardo Ballestero López
 * Creado: 27/10/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Sin longitud mínima de contraseña (la valida Supabase)
 */

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el formulario de inicio de sesión.
 */
class IniciarSesionRequest extends FormRequest
{
    /**
     * Permite que cualquier visitante envíe el formulario de acceso.
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
            'email' => ['required', 'email', 'max:255'],
            // Sin longitud mínima: quien valida la contraseña es Supabase Auth, y un mensaje distinto
            // para contraseñas cortas daría pistas sobre la política de claves.
            'password' => ['required', 'string', 'max:200'],
        ];
    }
}
