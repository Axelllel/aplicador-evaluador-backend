<?php

/**
 * Archivo: ReglasRegistro.php
 * Módulo: app/Support
 * Descripción: Reglas de validación del registro de docentes compartidas por el formulario web y la API:
 *              nombre solo con letras y espacios, y teléfono solo con números (Manual COD-WEB, secciones 6.10 y 6.18).
 * Autor: Leonardo Ballestero López
 * Creado: 24/11/2026
 * Modificado: 24/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Support;

/**
 * Concentra las reglas del alta de docente para que web y API no se desfasen.
 */
final class ReglasRegistro
{
    /**
     * Nombre: solo letras (mayúsculas y minúsculas, con acentos y ñ) separadas por un solo espacio.
     *
     * @return array<int, string>
     */
    public static function nombre(): array
    {
        return ['required', 'string', 'min:2', 'max:150', 'regex:/^\p{L}+( \p{L}+)*$/u'];
    }

    /**
     * Teléfono opcional: solo dígitos, de 10 a 15.
     *
     * @return array<int, string>
     */
    public static function telefono(): array
    {
        return ['nullable', 'string', 'regex:/^[0-9]{10,15}$/'];
    }

    /**
     * Mensajes en español, sin nombres internos de campos.
     *
     * @return array<string, string>
     */
    public static function mensajes(): array
    {
        return [
            'nombre.regex' => __('registro.validacion.nombre'),
            'telefono.regex' => __('registro.validacion.telefono'),
        ];
    }
}
