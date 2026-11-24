<?php

/**
 * Archivo: SimularPagoRequest.php
 * Módulo: app/Http/Requests
 * Descripción: Gestiona y verifica el módulo SimularPagoRequest.
 * Autor: Axel Rebollo Zeferino
 * Creado: 22/11/2026
 * Modificado: 22/11/2026 · Equipo BigBitByte · Integración de requisitos pendientes
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\FormatoNumeroTarjeta;
use App\Services\CotizacionPlanService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SimularPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'grupos' => ['required', 'integer', 'between:1,'.CotizacionPlanService::MAX_GRUPOS],
            'alumnos' => ['required', 'integer', Rule::in(CotizacionPlanService::ALUMNOS_POR_GRUPO)],
            'preguntas' => ['required', 'integer', Rule::in(CotizacionPlanService::PREGUNTAS)],
            'metodo_pago' => ['required', Rule::in(['tarjeta', 'spei'])],
            'titular' => ['required', 'string', 'min:2', 'max:150'],
            'correo_facturacion' => ['required', 'email', 'max:254'],
            'numero_tarjeta' => ['exclude_unless:metodo_pago,tarjeta', 'bail', 'required', 'string', new FormatoNumeroTarjeta],
            'vencimiento' => ['exclude_unless:metodo_pago,tarjeta', 'required', 'string', 'regex:/^(0[1-9]|1[0-2])\/\d{2}$/'],
            'cvv' => ['exclude_unless:metodo_pago,tarjeta', 'required', 'string', 'regex:/^[0-9]{3,4}$/D'],
            'banco' => ['exclude_unless:metodo_pago,spei', 'required', Rule::in(['Banco de prueba A', 'Banco de prueba B'])],
            'clabe' => ['exclude_unless:metodo_pago,spei', 'required', 'string', 'regex:/^\d{18}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('numero_tarjeta'))) {
            $this->merge(['numero_tarjeta' => str_replace([' ', '-'], '', $this->input('numero_tarjeta'))]);
        }
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('metodo_pago') === 'tarjeta' && ! $validator->errors()->has('vencimiento')) {
                [$month, $year] = array_map('intval', explode('/', (string) $this->input('vencimiento')));
                if ((2000 + $year) * 12 + $month < now()->year * 12 + now()->month) {
                    $validator->errors()->add('vencimiento', 'La tarjeta está vencida. Usa el mes actual o uno posterior.');
                }
            }
            if ($this->input('metodo_pago') === 'tarjeta' && ! $validator->errors()->has('numero_tarjeta') && ! $validator->errors()->has('cvv')) {
                $amex = preg_match('/^3[47][0-9]{13}$/D', (string) $this->input('numero_tarjeta')) === 1;
                $length = $amex ? 4 : 3;
                if (strlen((string) $this->input('cvv')) !== $length) {
                    $validator->errors()->add('cvv', 'Esta tarjeta requiere un código de seguridad de '.$length.' dígitos.');
                }
            }
            if ($this->input('metodo_pago') === 'spei' && ! $validator->errors()->has('clabe')) {
                $clabe = (string) $this->input('clabe');
                $sum = 0;
                for ($i = 0; $i < 17; $i++) {
                    $sum += ((int) $clabe[$i] * [3, 7, 1][$i % 3]) % 10;
                }
                if ((10 - $sum % 10) % 10 !== (int) $clabe[17]) {
                    $validator->errors()->add('clabe', 'La CLABE de prueba no tiene un dígito de control válido.');
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            '*.required' => 'Completa este campo.',
            'vencimiento.regex' => 'Usa el formato MM/AA, por ejemplo 12/30.',
            'cvv.regex' => 'El código de seguridad debe tener 3 o 4 dígitos, según la tarjeta.',
            'clabe.regex' => 'La CLABE de prueba debe tener 18 dígitos.',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('panel.suscripcion.planes.pago', $this->only(['grupos', 'alumnos', 'preguntas']));
    }
}
