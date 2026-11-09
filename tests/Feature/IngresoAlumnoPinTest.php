<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IngresoAlumnoPinTest extends TestCase
{
    public function test_el_portal_crea_el_perfil_y_muestra_el_pin(): void
    {
        config(['services.student_pin.pepper' => 'clave-local-de-prueba']);

        DB::shouldReceive('selectOne')
            ->once()
            ->withArgs(fn (string $sql, array $datos) => str_contains($sql, 'fn_crear_alumno_con_pin')
                && $datos[0] === 'GRUPO1'
                && $datos[1] === 'Ana Pérez'
                && preg_match('/^[0-9a-f]{64}$/', $datos[2]) === 1)
            ->andReturn($this->perfil());

        $respuesta = $this->postJson(route('student.join'), [
            'folio_codigo' => 'GRUPO1',
            'nombre' => 'Ana Pérez',
        ]);

        $respuesta->assertCreated()
            ->assertJsonPath('data.alumno_id', 17)
            ->assertJsonPath('data.modo', 'nuevo')
            ->assertSessionHas('bbb.student.alumno_id', 17);
        $this->assertMatchesRegularExpression('/^[A-Z]{4}[0-9]{2}$/', $respuesta->json('data.codigo_acceso'));
    }

    public function test_el_portal_reingresa_al_mismo_perfil_con_el_pin(): void
    {
        config(['services.student_pin.pepper' => 'clave-local-de-prueba']);

        DB::shouldReceive('selectOne')
            ->once()
            ->withArgs(fn (string $sql, array $datos) => str_contains($sql, 'fn_ingresar_alumno_con_pin')
                && $datos === ['GRUPO1', hash_hmac('sha256', 'ABCD27', 'clave-local-de-prueba')])
            ->andReturn($this->perfil());

        $this->postJson(route('student.join'), [
            'folio_codigo' => 'GRUPO1',
            'codigo_acceso' => 'ABCD27',
        ])->assertOk()
            ->assertJsonPath('data.alumno_id', 17)
            ->assertJsonPath('data.modo', 'reingreso')
            ->assertJsonMissingPath('data.codigo_acceso')
            ->assertSessionHas('bbb.student.alumno_id', 17);
    }

    private function perfil(): object
    {
        return (object) [
            'alumno_id' => 17,
            'grupo_id' => 4,
            'materia' => 'Historia',
            'nombre' => 'Ana Pérez',
        ];
    }
}
