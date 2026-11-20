<?php

declare(strict_types=1);

namespace App\Http\Requests;

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
            'numero_tarjeta' => ['exclude_unless:metodo_pago,tarjeta', 'required', 'string', 'regex:/^\d{16}$/', Rule::in(['4242424242424242', '5555555555554444'])],
            'vencimiento' => ['exclude_unless:metodo_pago,tarjeta', 'required', 'string', 'regex:/^(0[1-9]|1[0-2])\/\d{2}$/'],
            'cvv' => ['exclude_unless:metodo_pago,tarjeta', 'required', 'string', 'regex:/^\d{3}$/'],
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
                $date = \DateTimeImmutable::createFromFormat('!m/y', (string) $this->input('vencimiento'));
                if (! $date || $date < now()->startOfMonth()->toDateTimeImmutable()) {
                    $validator->errors()->add('vencimiento', 'La tarjeta de prueba está vencida. Usa el mes actual o uno posterior.');
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
            'numero_tarjeta.in' => 'Usa una tarjeta de prueba: 4242 4242 4242 4242 o 5555 5555 5555 4444.',
            'numero_tarjeta.regex' => 'Escribe los 16 dígitos de la tarjeta de prueba.',
            'vencimiento.regex' => 'Usa el formato MM/AA, por ejemplo 12/30.',
            'cvv.regex' => 'El CVV de prueba debe tener 3 dígitos.',
            'clabe.regex' => 'La CLABE de prueba debe tener 18 dígitos.',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('panel.suscripcion.planes.pago', $this->only(['grupos', 'alumnos', 'preguntas']));
    }
}
