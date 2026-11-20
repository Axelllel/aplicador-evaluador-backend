<?php

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
