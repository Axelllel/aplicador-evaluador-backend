<?php

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
        return [
            'grupos' => ['sometimes', 'required', 'integer', 'between:1,'.CotizacionPlanService::MAX_GRUPOS],
            'alumnos' => ['sometimes', 'required', 'integer', Rule::in(CotizacionPlanService::ALUMNOS_POR_GRUPO)],
            'preguntas' => ['sometimes', 'required', 'integer', Rule::in(CotizacionPlanService::PREGUNTAS)],
        ];
    }
}
