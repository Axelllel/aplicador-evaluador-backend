<?php

namespace App\Services;

use RuntimeException;

/** Genera el codigo del alumno y calcula un HMAC que no permite recuperar el PIN desde la BD. */
class CredencialAlumnoService
{
    private const LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public function generarCodigo(): string
    {
        $letters = '';
        for ($i = 0; $i < 4; $i++) {
            $letters .= self::LETTERS[random_int(0, strlen(self::LETTERS) - 1)];
        }

        return $letters.str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT);
    }

    public function hashCodigo(string $codigo): string
    {
        $pepper = (string) config('services.student_pin.pepper');
        if ($pepper === '') {
            $pepper = (string) config('app.key');
        }
        if ($pepper === '') {
            throw new RuntimeException('No hay una clave de servidor para proteger el código del alumno.');
        }

        return hash_hmac('sha256', mb_strtoupper(trim($codigo)), $pepper);
    }
}
