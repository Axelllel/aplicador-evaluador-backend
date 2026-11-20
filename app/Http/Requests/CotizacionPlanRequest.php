<?php

/**
 * Archivo: CotizacionPlanRequest.php
 * Módulo: app/Http/Requests
 * Descripción: Valida la cantidad de grupos y de alumnos por grupo elegidos para cotizar un plan.
 * Autor: Axel (Axelllel)
 * Creado: 08/11/2026
 * Modificado: 20/11/2026 · Leonardo Ballestero López · Prólogo del Manual COD-WEB 6.7
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\CotizacionPlanService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

class CotizacionPlanRequest extends FormRequest
{
    protected $redirectRoute = 'panel.suscripcion.planes';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|In>> */
    public function rules(): array
    {
        $presencia = $this->routeIs('panel.suscripcion.planes.pago', 'panel.suscripcion.planes.pago.revisar')
            ? ['required']
            : ['sometimes', 'required'];

        return [
            'grupos' => [...$presencia, 'integer', 'between:1,'.CotizacionPlanService::MAX_GRUPOS],
            'alumnos' => [...$presencia, 'integer', Rule::in(CotizacionPlanService::ALUMNOS_POR_GRUPO)],
            'preguntas' => [...$presencia, 'integer', Rule::in(CotizacionPlanService::PREGUNTAS)],
        ];
    }
}
