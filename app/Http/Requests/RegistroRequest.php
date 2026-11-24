<?php

/**
 * Archivo: RegistroRequest.php
 * Módulo: app/Http/Requests
 * Descripción: Valida el formulario de registro del docente con las mismas reglas que la API
 *              (nombre, correo, contraseña de 10 caracteres o más y teléfono opcional)
 *              (Manual COD-WEB, secciones 6.10 y 6.18).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\ReglasRegistro;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el formulario de registro.
 */
class RegistroRequest extends FormRequest
{
    /**
     * Permite que cualquier visitante envíe el formulario de registro.
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
            'nombre' => ReglasRegistro::nombre(),
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required', 'string', 'min:10', 'max:200'],
            'telefono' => ReglasRegistro::telefono(),
        ];
    }

    /**
     * Mensajes propios del nombre y del teléfono.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ReglasRegistro::mensajes();
    }

    /**
     * Devuelve los datos que se envían a la API, sin el teléfono cuando viene vacío.
     *
     * @return array<string, string>
     */
    public function datosParaLaApi(): array
    {
        $datos = $this->validated();

        if (($datos['telefono'] ?? null) === null || $datos['telefono'] === '') {
            unset($datos['telefono']);
        }

        return $datos;
    }
}
