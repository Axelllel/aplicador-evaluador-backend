<?php

/**
 * Archivo: MenuLateralService.php
 * Módulo: app/Services
 * Descripción: Construye los destinos del menú lateral según el rol
 *              (Manual COD-WEB, sección 6.16, Tabla 14).
 * Autor: Leonardo Ballestero López
 * Creado: 27/10/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Marca activas también las páginas hijas
 */

declare(strict_types=1);

namespace App\Services;

use App\Enums\RolPanel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Construye el menú lateral de cada rol.
 */
class MenuLateralService
{
    /**
     * Devuelve los destinos del menú del rol, en orden, marcando el activo.
     *
     * @return list<array{clave: string, etiqueta: string, url: string, icono: string, activo: bool}>
     */
    public function destinos(RolPanel $rol, Request $request): array
    {
        $destinos = [];

        foreach ($this->definir($rol) as $destino) {
            $destinos[] = [
                'clave' => $destino['clave'],
                'etiqueta' => __('panel.menu.'.$destino['clave']),
                // Los destinos del superadministrador no tienen ruta mientras la API no tenga ese rol.
                'url' => Route::has($destino['ruta']) ? route($destino['ruta']) : '#',
                'icono' => $destino['icono'],
                // Las páginas hijas (p. ej. panel.examenes.apartados) mantienen marcado su destino.
                'activo' => $request->routeIs($destino['ruta'], $destino['ruta'].'.*'),
            ];
        }

        return $destinos;
    }

    /**
     * Define los destinos de cada rol en el orden de la Tabla 14.
     *
     * @return list<array{clave: string, ruta: string, icono: string}>
     */
    private function definir(RolPanel $rol): array
    {
        return match ($rol) {
            RolPanel::Docente => [
                ['clave' => 'panel', 'ruta' => 'panel.inicio', 'icono' => 'squares-2x2'],
                ['clave' => 'grupos', 'ruta' => 'panel.grupos', 'icono' => 'user-group'],
                ['clave' => 'banco_preguntas', 'ruta' => 'panel.banco-de-preguntas', 'icono' => 'question-mark-circle'],
                ['clave' => 'examenes', 'ruta' => 'panel.examenes', 'icono' => 'document-text'],
                ['clave' => 'resultados', 'ruta' => 'panel.resultados', 'icono' => 'clipboard-document-check'],
                ['clave' => 'estadisticas', 'ruta' => 'panel.estadisticas', 'icono' => 'chart-bar'],
                ['clave' => 'suscripcion', 'ruta' => 'panel.suscripcion', 'icono' => 'credit-card'],
            ],
            RolPanel::SuperAdmin => [
                ['clave' => 'docentes', 'ruta' => 'panel.docentes', 'icono' => 'users'],
                ['clave' => 'pagos', 'ruta' => 'panel.pagos', 'icono' => 'banknotes'],
            ],
        };
    }
}
