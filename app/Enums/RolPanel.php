<?php

/**
 * Archivo: RolPanel.php
 * Módulo: app/Enums
 * Descripción: Roles que determinan el menú lateral y el panel inicial
 *              (Manual COD-WEB, sección 6.16, Tabla 14).
 * Autor: Leonardo Ballestero López
 * Creado: 27/10/2026
 * Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Roles del panel web: determinan el menú lateral y el panel inicial.
 */
enum RolPanel: string
{
    case Docente = 'docente';
    case SuperAdmin = 'super_admin';

    /**
     * Devuelve el rol al que pertenece una sección del menú lateral.
     */
    public static function deSeccion(string $seccion): self
    {
        $seccionesDeAdministracion = ['docentes', 'pagos'];

        if (in_array($seccion, $seccionesDeAdministracion, true)) {
            return self::SuperAdmin;
        }

        return self::Docente;
    }

    /**
     * Devuelve el nombre legible del rol para mostrarlo en el menú de cuenta.
     */
    public function etiqueta(): string
    {
        return __('panel.roles.'.$this->value);
    }

    /**
     * Devuelve el nombre del usuario de ejemplo de este rol.
     */
    public function nombreDeEjemplo(): string
    {
        return __('ejemplo.usuario.'.$this->value);
    }

    /**
     * Devuelve los parámetros de ruta que conservan el rol al navegar.
     *
     * @return array<string, string>
     */
    public function parametrosDeRuta(): array
    {
        return match ($this) {
            self::Docente => [],
            self::SuperAdmin => ['rol' => $this->value],
        };
    }

    /**
     * Devuelve la URL del panel inicial del rol.
     */
    public function urlDeInicio(): string
    {
        return route('panel.inicio', $this->parametrosDeRuta());
    }
}
