<?php

/**
 * Archivo: AccionPanel.php
 * Módulo: app/Enums
 * Descripción: Acciones del panel con su texto e ícono Heroicons, de modo que
 *              cada acción use siempre el mismo ícono (Manual COD-WEB, sección 6.18, Tabla 17).
 * Autor: Leonardo Ballestero López
 * Creado: 27/10/2026
 * Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Acciones del panel con su texto e ícono Heroicons fijos.
 */
enum AccionPanel: string
{
    case Abrir = 'abrir';
    case Nuevo = 'nuevo';
    case Guardar = 'guardar';
    case Editar = 'editar';
    case Eliminar = 'eliminar';
    case Cancelar = 'cancelar';
    case Aceptar = 'aceptar';
    case Buscar = 'buscar';
    case Publicar = 'publicar';
    case Resultados = 'resultados';
    case Estadisticas = 'estadisticas';
    case DescargarQr = 'descargar_qr';
    case CopiarFolio = 'copiar_folio';
    case Reintentar = 'reintentar';
    case CerrarSesion = 'cerrar_sesion';

    /**
     * Devuelve el texto del botón o del menú, tomado de lang/es/acciones.php.
     */
    public function texto(): string
    {
        return __('acciones.'.$this->value);
    }

    /**
     * Devuelve el nombre del ícono Heroicons asociado a la acción.
     */
    public function icono(): string
    {
        return match ($this) {
            self::Abrir => 'folder-open',
            self::Nuevo => 'plus',
            self::Guardar => 'check',
            self::Editar => 'pencil-square',
            self::Eliminar => 'trash',
            self::Cancelar => 'x-mark',
            self::Aceptar => 'check',
            self::Buscar => 'magnifying-glass',
            self::Publicar => 'paper-airplane',
            self::Resultados => 'clipboard-document-check',
            self::Estadisticas => 'chart-bar',
            self::DescargarQr => 'qr-code',
            self::CopiarFolio => 'clipboard-document',
            self::Reintentar => 'arrow-path',
            self::CerrarSesion => 'arrow-right-on-rectangle',
        };
    }

    /**
     * Indica si la acción es destructiva (color de error y confirmación previa).
     */
    public function esDestructiva(): bool
    {
        return $this === self::Eliminar;
    }
}
