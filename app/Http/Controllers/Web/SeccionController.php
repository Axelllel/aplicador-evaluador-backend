<?php

/**
 * Archivo: SeccionController.php
 * Módulo: app/Http/Controllers/Web
 * Descripción: Muestra una página provisional («Sección en construcción») para los destinos del menú
 *              lateral que aún no tienen módulo: Resultados y Estadísticas (Manual COD-WEB, sección 6.16).
 * Autor: Leonardo Ballestero López
 * Creado: 27/10/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Solo secciones del docente
 */

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\RolPanel;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Controla las páginas provisionales de los destinos del menú.
 */
class SeccionController extends Controller
{
    /**
     * Muestra la sección indicada por el valor por defecto de la ruta.
     */
    public function show(string $seccion): View
    {
        // TODO(Leonardo Ballestero López): Resultados puede construirse con GET /api/v1/teacher/exams/{id}/results
        // y /attempts; Estadísticas espera el endpoint de analíticas del grupo, que la API aún no tiene.
        return view('panel.seccion', [
            'titulo' => __('panel.menu.'.$seccion),
            'rol' => RolPanel::Docente,
        ]);
    }
}
