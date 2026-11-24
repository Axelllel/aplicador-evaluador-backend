<?php

/**
 * Archivo: StudentDashboardTest.php
 * Módulo: tests/Feature
 * Descripción: Gestiona y verifica el módulo StudentDashboardTest.
 * Autor: Axel Rebollo Zeferino
 * Creado: 22/11/2026
 * Modificado: 22/11/2026 · Equipo BigBitByte · Integración de requisitos pendientes
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\CredencialAlumnoService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::shouldReceive('getConnections')->andReturn([]);
    }

    public function test_el_panel_resuelve_la_identidad_con_pin_y_no_con_el_id_del_cliente(): void
    {
        config(['services.student_pin.pepper' => 'clave-de-prueba']);
        $hash = app(CredencialAlumnoService::class)->hashCodigo('ABCD27');
        DB::shouldReceive('selectOne')->once()->with(
            'SELECT public.fn_panel_alumno_movil(CAST(? AS text), CAST(? AS text)) AS data',
            ['FOLIO123', $hash],
            false,
        )->andReturn((object) ['data' => json_encode([
            'alumno' => ['id' => 14], 'grupos' => [], 'examenes' => [], 'entregas' => [],
        ])]);

        $this->postJson('/api/v1/student/dashboard', [
            'folio_codigo' => 'folio123', 'codigo_acceso' => 'ABCD27', 'alumno_id' => 11,
        ])->assertOk()->assertJsonPath('data.alumno.id', 14)
            ->assertJsonMissingPath('data.codigo_acceso');
    }

    public function test_el_panel_rechaza_un_pin_revocado(): void
    {
        config(['services.student_pin.pepper' => 'clave-de-prueba']);
        $error = new \PDOException('CREDENCIAL_NO_VALIDA');
        $error->errorInfo = ['P1004', null, 'CREDENCIAL_NO_VALIDA'];
        DB::shouldReceive('selectOne')->once()->andThrow(new QueryException('pgsql', 'select', [], $error));

        $this->postJson('/api/v1/student/dashboard', [
            'folio_codigo' => 'FOLIO123', 'codigo_acceso' => 'ABCD27',
        ])->assertUnauthorized()->assertJsonPath('message', 'El código personal no es válido.');
    }

    public function test_el_panel_exige_folio_y_pin(): void
    {
        DB::shouldReceive('selectOne')->never();
        $this->postJson('/api/v1/student/dashboard', ['alumno_id' => 14])
            ->assertUnprocessable()->assertJsonValidationErrors(['folio_codigo', 'codigo_acceso']);
    }
}
