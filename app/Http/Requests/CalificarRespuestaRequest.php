<?php

/**
 * Archivo: CalificarRespuestaRequest.php
 * Módulo: app/Http/Requests
 * Descripción: Valida la calificación manual de una respuesta (calificación de 0 a 100 y motivo opcional) con las
 *              mismas reglas que la API; los errores se guardan en una bolsa propia de cada respuesta para
 *              mostrarlos junto a su formulario (Manual COD-WEB, secciones 6.10 y 6.18).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el formulario de calificación manual de una respuesta.
 */
class CalificarRespuestaRequest extends FormRequest
{
    /** Prefijo de la bolsa de errores; cada respuesta tiene la suya (por ejemplo «respuesta_12»). */
    public const PREFIJO_DE_BOLSA = 'respuesta_';

    /**
     * La sesión ya la exige el middleware panel.sesion.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Separa los errores por respuesta, porque la página del intento tiene un formulario por cada una.
     */
    protected function prepareForValidation(): void
    {
        $this->errorBag = self::PREFIJO_DE_BOLSA.(int) $this->route('respuesta');
    }

    /**
     * Define las reglas de validación; los mensajes salen de lang/es/validation.php.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'calificacion' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'],
            'motivo' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
