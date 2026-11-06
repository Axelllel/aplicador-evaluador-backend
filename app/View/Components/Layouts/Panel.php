<?php

/**
 * Archivo: Panel.php
 * Módulo: app/View/Components/Layouts
 * Descripción: Componente de diseño del panel: barra superior de 64 px, barra lateral de 256 px
 *              y área de contenido (Manual COD-WEB, sección 6.16).
 * Autor: Leonardo Ballestero López
 * Creado: 27/10/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · El nombre del usuario sale de la sesión
 */

declare(strict_types=1);

namespace App\View\Components\Layouts;

use App\Enums\RolPanel;
use App\Services\MenuLateralService;
use App\Services\SesionPanelService;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Componente de diseño del panel con barra superior, menú lateral y contenido.
 */
class Panel extends Component
{
    public function __construct(
        private readonly MenuLateralService $menu,
        public string $titulo,
        public RolPanel $rol = RolPanel::Docente,
        public ?string $descripcion = null,
    ) {
    }

    /**
     * Devuelve los destinos del menú lateral del rol, con el activo marcado.
     *
     * @return list<array{clave: string, etiqueta: string, url: string, icono: string, activo: bool}>
     */
    public function destinos(): array
    {
        return $this->menu->destinos($this->rol, request());
    }

    /**
     * Devuelve el nombre del usuario que se muestra en la barra superior.
     */
    public function nombreDelUsuario(): string
    {
        // El nombre sale de la sesión; el de ejemplo solo aparece en la guía de estilo (sin sesión).
        return app(SesionPanelService::class)->nombre() ?? $this->rol->nombreDeEjemplo();
    }

    public function render(): View
    {
        return view('components.layouts.panel');
    }
}
