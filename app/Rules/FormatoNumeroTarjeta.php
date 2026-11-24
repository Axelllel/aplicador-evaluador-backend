<?php

/**
 * Archivo: FormatoNumeroTarjeta.php
 * Módulo: app/Rules
 * Descripción: Comprueba longitud y dígito de control; no consulta bancos ni confirma emisión.
 * Autor: Axel Rebollo Zeferino
 * Creado: 23/11/2026
 * Modificado: 23/11/2026 · Equipo BigBitByte · Validación de pagos simulados
 */

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class FormatoNumeroTarjeta implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^[0-9]{13,19}$/D', $value) || preg_match('/^([0-9])\1+$/D', $value)) {
            $fail('Escribe un número de tarjeta válido de 13 a 19 dígitos.');

            return;
        }
        $sum = 0;
        $double = false;
        for ($index = strlen($value) - 1; $index >= 0; $index--) {
            $digit = (int) $value[$index];
            if ($double) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
            $double = ! $double;
        }
        if ($sum % 10 !== 0) {
            $fail('Revisa el número de tarjeta: el dígito de control no es válido.');
        }
    }
}
