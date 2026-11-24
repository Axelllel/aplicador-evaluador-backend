<?php

/**
 * Archivo: RegistroValidacionTest.php
 * Módulo: tests/Feature
 * Descripción: Valida que el registro de docentes acepte solo letras y espacios en el nombre y solo números en el
 *              teléfono, tanto en el formulario web como en la API.
 * Autor: Leonardo Ballestero López
 * Creado: 24/11/2026
 * Modificado: 24/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistroValidacionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['services.supabase.url' => 'https://auth.example.test', 'services.supabase.key' => 'public-test-key']);
        Http::preventStrayRequests();
    }

    /**
     * @return array<string, string>
     */
    private function datos(array $cambios = []): array
    {
        return array_replace(['nombre' => 'José Núñez Peña', 'email' => 'jose@example.test', 'password' => 'ClaveSegura2026', 'telefono' => '5512345678'], $cambios);
    }

    #[DataProvider('nombresInvalidos')]
    public function test_la_api_rechaza_nombres_con_numeros_o_signos(string $nombre): void
    {
        $this->postJson('/api/v1/auth/register', $this->datos(['nombre' => $nombre]))->assertStatus(422)->assertJsonValidationErrors('nombre');
    }

    #[DataProvider('nombresInvalidos')]
    public function test_la_web_rechaza_nombres_con_numeros_o_signos(string $nombre): void
    {
        $this->post('/registro', $this->datos(['nombre' => $nombre]))->assertSessionHasErrors('nombre');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function nombresInvalidos(): array
    {
        return [
            'con números' => ['Ana 123'],
            'número dentro' => ['J0sé'],
            'con guion' => ['Ana-María'],
            'con punto' => ['Ana M.'],
            'con apóstrofo' => ["O'Brien"],
            'con emoji' => ['Ana 😀'],
            'doble espacio' => ['Ana  López'],
            'espacio al final con símbolo' => ['Ana López @'],
        ];
    }

    #[DataProvider('nombresValidos')]
    public function test_los_nombres_solo_con_letras_pasan_la_validacion(string $nombre): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        // Con un nombre válido la validación pasa y la petición llega a Supabase (aquí simulado con error): no es 422 de nombre.
        $respuesta = $this->postJson('/api/v1/auth/register', $this->datos(['nombre' => $nombre]));

        $this->assertArrayNotHasKey('nombre', $respuesta->json('errors') ?? []);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function nombresValidos(): array
    {
        return [
            'con acentos y ñ' => ['José Núñez Peña'],
            'minúsculas' => ['ana lópez'],
            'mayúsculas' => ['ANA LÓPEZ'],
            'una sola palabra' => ['Ana'],
        ];
    }

    #[DataProvider('telefonosInvalidos')]
    public function test_la_api_rechaza_telefonos_que_no_son_solo_numeros(string $telefono): void
    {
        $this->postJson('/api/v1/auth/register', $this->datos(['telefono' => $telefono]))->assertStatus(422)->assertJsonValidationErrors('telefono');
    }

    #[DataProvider('telefonosInvalidos')]
    public function test_la_web_rechaza_telefonos_que_no_son_solo_numeros(string $telefono): void
    {
        $this->post('/registro', $this->datos(['telefono' => $telefono]))->assertSessionHasErrors('telefono');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function telefonosInvalidos(): array
    {
        return [
            'letras' => ['abcdefghij'],
            'con guiones' => ['55-1234-5678'],
            'con espacios' => ['55 1234 5678'],
            'con signo más' => ['+525512345678'],
            'nueve dígitos' => ['551234567'],
            'dieciséis dígitos' => ['5512345678901234'],
            'mezcla' => ['55123abc78'],
        ];
    }

    #[DataProvider('telefonosValidos')]
    public function test_los_telefonos_solo_con_numeros_o_vacios_pasan_la_validacion(string $telefono): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $respuesta = $this->postJson('/api/v1/auth/register', $this->datos(['telefono' => $telefono]));

        $this->assertArrayNotHasKey('telefono', $respuesta->json('errors') ?? []);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function telefonosValidos(): array
    {
        return [
            'diez dígitos' => ['5512345678'],
            'quince dígitos' => ['521551234567890'],
            'vacío' => [''],
        ];
    }

    public function test_el_mensaje_del_teléfono_no_muestra_nombres_internos(): void
    {
        $respuesta = $this->postJson('/api/v1/auth/register', $this->datos(['telefono' => 'abc']));

        $this->assertSame('El teléfono debe tener solo números, entre 10 y 15 dígitos.', $respuesta->json('errors.telefono.0'));
    }
}
