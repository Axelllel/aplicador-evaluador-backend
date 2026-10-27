<?php

namespace App\Services;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseUserContext
{
    public function ejecutarComoDocente(callable $operacion): mixed
    {
        $docenteId = Auth::id();

        if (!is_string($docenteId)) {
            throw new AuthenticationException('Docente no autenticado.');
        }

        return $this->ejecutarConDocenteVerificado($docenteId, $operacion);
    }

    /**
     * $docenteId debe provenir de una identidad validada con Supabase Auth.
     * Nunca aceptar aqui un ID proporcionado directamente por el cliente.
     */
    public function ejecutarConDocenteVerificado(string $docenteId, callable $operacion): mixed
    {
        if (!Str::isUuid($docenteId)) {
            throw new AuthenticationException('Identidad de docente invalida.');
        }

        return DB::transaction(function () use ($docenteId, $operacion) {

            DB::selectOne(
                "SELECT set_config('app.docente_id', ?, true)",
                [$docenteId]
            );

            return $operacion();
        });
    }
}