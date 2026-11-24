<?php

/**
 * Archivo: AdministracionDisenoTest.php
 * Módulo: tests/Feature
 * Descripción: Verifica indicadores reales, filtros, paginación y confirmaciones del panel administrativo.
 * Autor: Axel Rebollo Zeferino
 * Creado: 24/11/2026
 * Modificado: 24/11/2026 · Equipo BigBitByte · Panel administrativo
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\SesionPanelService;
use Carbon\CarbonImmutable;
use Tests\Support\ConPanelFalso;
use Tests\TestCase;

class AdministracionDisenoTest extends TestCase
{
    use ConPanelFalso;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararPanel();
        $this->withSession([SesionPanelService::CLAVE_ADMIN => SesionPanelService::datosParaSesion([
            'access_token' => 'admin-token', 'docente' => ['id' => '11111111-1111-4111-8111-111111111111', 'nombre' => 'Admin', 'rol' => 'super_admin'],
        ])]);
    }

    private function teachers(): array
    {
        return array_map(fn (int $n): array => ['id' => sprintf('22222222-2222-4222-8222-%012d', $n),
            'nombre' => 'Docente '.$n, 'correo' => 'persona'.$n.'@example.test', 'estado_pago' => 'activo',
            'suspendido_en' => $n === 18 ? '2026-11-20' : null], range(1, 18));
    }

    private function payments(): array
    {
        return [
            ['id' => 1, 'nombre' => 'Ana Pérez', 'monto' => 115, 'estado' => 'confirmado', 'fecha' => '2026-11-23T12:00:00+00:00', 'fecha_inicio_vigencia' => '2026-11-23', 'fecha_fin_vigencia' => '2026-12-23'],
            ['id' => 2, 'nombre' => 'Ana Pérez', 'monto' => 200, 'estado' => 'pendiente', 'fecha' => '2026-11-24T12:00:00+00:00'],
            ['id' => 3, 'nombre' => 'Luis Soto', 'monto' => 900, 'estado' => 'rechazado', 'fecha' => '2026-11-24T12:00:00+00:00'],
            ['id' => 4, 'nombre' => 'Luis Soto', 'monto' => 50, 'estado' => 'confirmado', 'fecha' => '2026-10-01T02:00:00+00:00'],
        ];
    }

    public function test_resumen_y_grafica_solo_suman_pagos_confirmados_y_respetan_el_mes_local(): void
    {
        CarbonImmutable::setTestNow('2026-11-24 12:00:00');
        try {
            $this->api->responder('GET', '/admin/teachers', ['data' => $this->teachers()])
                ->responder('GET', '/admin/payments', ['data' => $this->payments()]);
            $response = $this->get('/administracion')->assertOk()->assertSee('Resumen general')->assertSee('$165.00')
                ->assertDontSee(route('panel.grupos'), false)->assertHeader('Cache-Control', 'no-store, private');
            $this->assertSame(18, $response['resumen']['docentes']);
            $this->assertSame(17, $response['resumen']['habilitados']);
            $this->assertSame(1, $response['resumen']['pendientes']);
            $points = collect($response['serie']['meses'])->keyBy('periodo');
            $this->assertSame(115.0, $points['2026-11']['valor']);
            $this->assertSame(50.0, $points['2026-09']['valor']);
            $this->assertSame(0.0, $points['2026-10']['valor']);
            $this->assertSame([2], $response['pendientes']->pluck('id')->all());
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_el_directorio_filtra_por_nombre_correo_y_estado_y_pagina_de_quince_en_quince(): void
    {
        $this->api->responder('GET', '/admin/teachers', ['data' => $this->teachers()]);
        $page = $this->get('/administracion/docentes?q=docente&estado=habilitado&page=2')->assertOk();
        $this->assertSame(17, $page['docentes']->total());
        $this->assertSame(['Docente 16', 'Docente 17'], $page['docentes']->pluck('nombre')->all());
        $this->assertStringContainsString('estado=habilitado', $page['docentes']->url(1));
        $this->assertStringContainsString('q=docente', $page['docentes']->url(1));
        $filtered = $this->get('/administracion/docentes?q=PERSONA18%40example.test&estado=suspendido')->assertOk();
        $this->assertSame(['Docente 18'], $filtered['docentes']->pluck('nombre')->all());
        $this->get('/administracion/docentes?q=ausente')->assertOk()->assertSee('No encontramos coincidencias')->assertSee('Limpiar filtros');
    }

    public function test_pagos_filtra_estado_mes_y_folio_con_fechas_sin_hora_sin_desplazarlas(): void
    {
        $this->api->responder('GET', '/admin/payments', ['data' => $this->payments()]);
        $page = $this->get('/administracion/pagos?q=PAG-000001&estado=confirmado&mes=2026-11')->assertOk();
        $this->assertSame([1], $page['pagos']->pluck('id')->all());
        $this->assertSame('23/11/2026', $page['pagos']->first()['inicio_texto']);
        $this->assertSame('$165.00', $page['resumen']['monto_confirmado']);
        $this->get('/administracion/pagos?q=nadie')->assertOk()->assertSee('No encontramos coincidencias');
    }

    public function test_los_errores_regresan_al_modal_correcto_sin_enviar_el_cambio(): void
    {
        $id = $this->teachers()[0]['id'];
        $this->api->responder('GET', '/admin/teachers', ['data' => $this->teachers()]);
        $page = $this->followingRedirects()->from('/administracion/docentes?q=persona1')->patch('/administracion/docentes/'.$id, ['suspender' => 1, 'motivo' => 'corto'])->assertOk();
        $this->assertCount(0, $this->api->llamadasA('PATCH', '/admin/teachers/'.$id));
        $this->assertSame(1, substr_count($page->getContent(), 'data-admin-dialog-open'));
        $page->assertSee('aria-invalid="true"', false)->assertSee('corto');
    }

    public function test_actualizar_pago_conserva_filtros_y_solo_envia_los_campos_permitidos(): void
    {
        $this->api->responder('PATCH', '/admin/payments/2', ['data' => ['actualizado' => true]]);
        $this->patch('/administracion/pagos/2?q=Ana&estado=pendiente&mes=2026-11&page=2&redirigir=otro', [
            'estado' => 'confirmado', 'motivo' => 'Verificado por administración', 'monto' => 9999,
        ])->assertRedirect(route('panel.pagos', ['q' => 'Ana', 'estado' => 'pendiente', 'mes' => '2026-11', 'page' => 2]))->assertSessionHas('exito');
        $this->assertSame(['estado' => 'confirmado', 'motivo' => 'Verificado por administración'], $this->api->llamadasA('PATCH', '/admin/payments/2')[0]['datos']);
    }

    public function test_un_error_de_permisos_no_se_disfraza_de_estadisticas_vacias(): void
    {
        $this->api->responder('GET', '/admin/teachers', ['message' => 'No autorizado'], 403);
        $this->get('/administracion')->assertForbidden();
        $this->assertCount(0, $this->api->llamadasA('GET', '/admin/payments'));
    }

    public function test_el_resumen_vacio_no_inventa_actividad(): void
    {
        $this->api->responder('GET', '/admin/teachers', ['data' => []])->responder('GET', '/admin/payments', ['data' => []]);
        $page = $this->get('/administracion')->assertOk()->assertSee('Todavía no hay docentes registrados')->assertSee('Sin pagos pendientes');
        $this->assertSame('$0.00', $page['resumen']['monto_confirmado']);
        $this->assertSame([0.0], array_values(array_unique(array_column($page['serie']['meses'], 'valor'))));
    }
}
