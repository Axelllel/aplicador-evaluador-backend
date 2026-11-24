<?php

/**
 * Archivo: ExamenFechasTest.php
 * Módulo: tests/Feature
 * Descripción: Valida las reglas de fecha y nombre al crear un examen en la web: apertura sin pasado ni años
 *              antiguos, cierre posterior y con ventana máxima, conversión de zona horaria y ancla al formulario.
 * Autor: Leonardo Ballestero López
 * Creado: 23/11/2026
 * Modificado: 23/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ConPanelFalso;
use Tests\TestCase;

class ExamenFechasTest extends TestCase
{
    use ConPanelFalso;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone_docente' => 'America/Mexico_City']);
        $this->prepararPanel();
        $this->conSesionDelPanel();
        $this->prepararLecturas();
        $this->api->responder('POST', '/teacher/exams', ['data' => ['id' => 7]], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function datos(array $cambios = []): array
    {
        $apertura = CarbonImmutable::now('America/Mexico_City')->addHour();

        return array_merge([
            'grupo_id' => 1,
            'nombre' => 'Parcial 1',
            'fecha_apertura' => $apertura->format('Y-m-d\TH:i'),
            'fecha_cierre' => $apertura->addHours(2)->format('Y-m-d\TH:i'),
            'orden' => 'fijo',
            'escala' => '0-10',
            'plataforma_permitida' => 'ambas',
            'resultado_visible' => 'oculto',
        ], $cambios);
    }

    public function test_un_examen_valido_se_envia_con_el_desfase_de_la_zona_del_docente(): void
    {
        $this->post('/examenes', $this->datos())->assertSessionHasNoErrors();

        $enviado = $this->api->llamadasA('POST', '/teacher/exams')[0]['datos'];
        $this->assertMatchesRegularExpression('/-0[56]:00$/', $enviado['fecha_apertura']);
    }

    public function test_apertura_en_1900_se_rechaza_aunque_el_cierre_sea_actual(): void
    {
        $this->post('/examenes', $this->datos(['fecha_apertura' => '1900-01-01T08:00']))
            ->assertSessionHasErrors('fecha_apertura');
        $this->assertSame([], $this->api->llamadasA('POST', '/teacher/exams'));
    }

    public function test_apertura_en_el_pasado_se_rechaza(): void
    {
        $ayer = CarbonImmutable::now('America/Mexico_City')->subDay();
        $this->post('/examenes', $this->datos([
            'fecha_apertura' => $ayer->format('Y-m-d\TH:i'),
            'fecha_cierre' => $ayer->addHours(2)->format('Y-m-d\TH:i'),
        ]))->assertSessionHasErrors('fecha_apertura');
    }

    public function test_cierre_igual_o_anterior_a_la_apertura_se_rechaza(): void
    {
        $datos = $this->datos();
        $this->post('/examenes', $datos + [])->assertSessionHasNoErrors();
        $this->post('/examenes', array_merge($datos, ['fecha_cierre' => $datos['fecha_apertura']]))
            ->assertSessionHasErrors('fecha_cierre');
    }

    public function test_una_ventana_mayor_a_treinta_dias_se_rechaza(): void
    {
        $apertura = CarbonImmutable::now('America/Mexico_City')->addHour();
        $this->post('/examenes', $this->datos(['fecha_cierre' => $apertura->addDays(31)->format('Y-m-d\TH:i')]))
            ->assertSessionHasErrors('fecha_cierre');
    }

    #[DataProvider('nombresInvalidos')]
    public function test_nombres_sin_contenido_legible_se_rechazan(string $nombre): void
    {
        $this->post('/examenes', $this->datos(['nombre' => $nombre]))->assertSessionHasErrors('nombre');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function nombresInvalidos(): array
    {
        return [
            'solo signos' => ['!!!???'],
            'con salto de línea' => ["Parcial\n1"],
            'solo invisibles' => ["\u{200C}\u{200C}"],
        ];
    }

    public function test_el_nombre_no_puede_superar_treinta_caracteres(): void
    {
        $this->post('/examenes', $this->datos(['nombre' => str_repeat('a', 31)]))->assertSessionHasErrors('nombre');
        $this->post('/examenes', $this->datos(['nombre' => str_repeat('a', 30)]))->assertSessionHasNoErrors();
    }

    public function test_un_error_de_publicar_no_abre_el_recuadro_del_alta(): void
    {
        $this->api->responder('POST', '/teacher/exams/1/publish', ['message' => 'El examen necesita al menos un apartado.'], 409);

        $this->from('/examenes')->post('/examenes/1/publicar')->assertSessionHas('error');

        $this->get('/examenes')->assertOk()->assertDontSee('data-error-summary', false)->assertDontSee('No se pudo crear el examen.');
    }

    public function test_un_error_del_alta_si_se_muestra_dentro_del_formulario(): void
    {
        $this->api->responder('POST', '/teacher/exams', ['message' => 'Grupo no disponible.'], 404);

        $this->from('/examenes')->post('/examenes', $this->datos())->assertSessionHas('alta_fallida');

        $this->get('/examenes')->assertOk()->assertSee('data-error-summary', false)->assertSee('No se pudo crear el examen.');
    }

    public function test_el_error_regresa_al_formulario_y_no_al_inicio(): void
    {
        $respuesta = $this->from('/examenes')->post('/examenes', $this->datos(['fecha_apertura' => '1900-01-01T08:00']));

        $this->assertStringEndsWith('#nuevo-examen', (string) $respuesta->headers->get('Location'));
    }
}
