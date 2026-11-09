<?php

/**
 * Archivo: PaginadorPanel.php
 * Módulo: app/Services
 * Descripción: Pagina de a 15 filas las listas que entrega la API (que responde sin paginar), para usar
 *              la paginación del panel (Manual COD-WEB, sección 6.17, Tabla 15).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Paginador de listas en memoria para las tablas del panel.
 */
class PaginadorPanel
{
    /** Filas por página de toda tabla de datos. */
    public const FILAS_POR_PAGINA = 15;

    /**
     * Devuelve la página pedida, ajustada al rango válido, conservando el resto de la consulta en los enlaces.
     *
     * @template TValor
     *
     * @param  Collection<int, TValor>  $filas
     */
    public function paginar(Collection $filas, int $pagina, string $nombreDePagina = 'page'): LengthAwarePaginator
    {
        $ultima = max(1, (int) ceil($filas->count() / self::FILAS_POR_PAGINA));
        $actual = min(max(1, $pagina), $ultima);

        return (new LengthAwarePaginator(
            $filas->forPage($actual, self::FILAS_POR_PAGINA)->values(),
            $filas->count(),
            self::FILAS_POR_PAGINA,
            $actual,
            ['path' => url()->current(), 'pageName' => $nombreDePagina],
        ))->withQueryString();
    }
}
