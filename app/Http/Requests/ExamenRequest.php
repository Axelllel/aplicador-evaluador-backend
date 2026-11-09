<?php

/**
 * Archivo: ExamenRequest.php
 * Módulo: app/Http/Requests
 * Descripción: Valida el alta de un examen (grupo, nombre, fechas, orden, escala, plataforma y resultado
 *              visible) con las mismas reglas que la API (Manual COD-WEB, secciones 6.10 y 6.18).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el formulario de nuevo examen.
 */
class ExamenRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'grupo_id' => ['required', 'integer', 'min:1'],
            'nombre' => ['required', 'string', 'min:2', 'max:150'],
            'fecha_apertura' => ['required', 'date'],
            'fecha_cierre' => ['required', 'date', 'after:fecha_apertura'],
            'orden' => ['required', Rule::in(['aleatorio', 'fijo'])],
            'escala' => ['required', Rule::in(['0-10', '0-100'])],
            'plataforma_permitida' => ['required', Rule::in(['web', 'movil', 'ambas'])],
            'resultado_visible' => ['required', Rule::in(['final', 'aproximado', 'oculto'])],
        ];
    }

    /**
     * Devuelve el JSON que espera POST /api/v1/teacher/exams, con las fechas en «Y-m-d H:i:s».
     *
     * @return array<string, mixed>
     */
    public function datosParaLaApi(): array
    {
        $datos = $this->validated();
        $datos['grupo_id'] = (int) $datos['grupo_id'];
        $datos['fecha_apertura'] = CarbonImmutable::parse($datos['fecha_apertura'])->format('Y-m-d H:i:s');
        $datos['fecha_cierre'] = CarbonImmutable::parse($datos['fecha_cierre'])->format('Y-m-d H:i:s');

        return $datos;
    }
}
