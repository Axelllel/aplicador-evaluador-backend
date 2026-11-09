<?php

/**
 * Archivo: MenuLateralServiceTest.php
 * Módulo: tests/Feature
 * Descripción: Verifica los destinos del menú lateral de cada rol y su orden (Manual COD-WEB, sección 6.16, Tabla 14).
 * Autor: Leonardo Ballestero López
 * Creado: 27/10/2026
 * Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RolPanel;
use App\Services\MenuLateralService;
use Illuminate\Http\Request;
use Tests\TestCase;

class MenuLateralServiceTest extends TestCase
{
    public function test_el_docente_tiene_siete_destinos_en_el_orden_de_la_tabla_14(): void
    {
        $destinos = app(MenuLateralService::class)->destinos(RolPanel::Docente, Request::create('/panel'));

        $this->assertSame(
            ['Panel', 'Grupos', 'Banco de preguntas', 'Exámenes', 'Resultados', 'Estadísticas', 'Suscripción'],
            array_column($destinos, 'etiqueta'),
        );
    }

    public function test_el_superadministrador_tiene_dos_destinos(): void
    {
        $destinos = app(MenuLateralService::class)->destinos(RolPanel::SuperAdmin, Request::create('/panel'));

        $this->assertSame(['Docentes', 'Pagos y suscripciones'], array_column($destinos, 'etiqueta'));
    }

    public function test_cada_destino_trae_una_url_y_un_icono(): void
    {
        foreach (RolPanel::cases() as $rol) {
            foreach (app(MenuLateralService::class)->destinos($rol, Request::create('/panel')) as $destino) {
                $this->assertNotSame('', $destino['url']);
                $this->assertFileExists(resource_path('views/components/icon/'.$destino['icono'].'.blade.php'));
            }
        }
    }
}
