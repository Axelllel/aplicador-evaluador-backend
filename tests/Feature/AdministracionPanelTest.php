<?php

/**
 * Archivo: AdministracionPanelTest.php
 * Módulo: tests/Feature
 * Descripción: Verifica vistas y acciones administrativas con respuestas controladas de la API.
 * Autor: Axel Rebollo Zeferino
 * Creado: 22/11/2026
 * Modificado: 22/11/2026 · Equipo BigBitByte · Administración
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\SesionPanelService;
use Tests\Support\ConPanelFalso;
use Tests\TestCase;

class AdministracionPanelTest extends TestCase
{
    use ConPanelFalso;

    public function test_las_vistas_administrativas_muestran_docentes_y_pagos_sin_datos_bancarios(): void
    {
        $this->prepararPanel();
        $data = SesionPanelService::datosParaSesion(['access_token' => 'admin-token', 'docente' => ['id' => '11111111-1111-4111-8111-111111111111', 'nombre' => 'Admin', 'rol' => 'super_admin']]);
        $this->withSession([SesionPanelService::CLAVE_ADMIN => $data]);
        $this->api->responder('GET', '/admin/teachers', ['data' => [['id' => '11111111-1111-4111-8111-111111111111', 'nombre' => 'Ana Pérez', 'correo' => 'ana@example.test', 'suspendido_en' => null, 'estado_pago' => 'activo']]])
            ->responder('GET', '/admin/payments', ['data' => [['id' => 1, 'nombre' => 'Ana Pérez', 'monto' => 115, 'estado' => 'confirmado', 'fecha_inicio_vigencia' => '2026-11-22', 'fecha_fin_vigencia' => '2026-12-22']]]);
        $this->get('/administracion/docentes')->assertOk()->assertSee('Ana Pérez')->assertSee('Suspender cuenta');
        $this->get('/administracion/pagos')->assertOk()->assertSee('Ana Pérez')->assertSee('115.00')->assertDontSee('numero_tarjeta');
    }

    public function test_un_cambio_administrativo_envia_el_motivo_a_la_api_y_muestra_confirmacion(): void
    {
        $this->prepararPanel();
        $data = SesionPanelService::datosParaSesion(['access_token' => 'admin-token', 'docente' => ['id' => '11111111-1111-4111-8111-111111111111', 'nombre' => 'Admin', 'rol' => 'super_admin']]);
        $this->withSession([SesionPanelService::CLAVE_ADMIN => $data]);
        $id = '11111111-1111-4111-8111-111111111111';
        $this->api->responder('PATCH', '/admin/teachers/'.$id, ['data' => ['actualizado' => true]]);
        $this->patch('/administracion/docentes/'.$id, ['suspender' => true, 'motivo' => 'Suspensión autorizada por administración'])
            ->assertRedirect(route('panel.docentes'))->assertSessionHas('exito');
        $this->assertTrue($this->api->llamadasA('PATCH', '/admin/teachers/'.$id)[0]['datos']['suspender']);
    }
}
