<?php

/**
 * Archivo: PreguntaCalidadTest.php
 * Módulo: tests/Feature
 * Descripción: Valida las reglas de calidad del banco de preguntas: enunciado legible, repetidos sin distinguir
 *              mayúsculas, lenguaje de programación, mensajes claros y edición o eliminación de preguntas.
 * Autor: Leonardo Ballestero López
 * Creado: 24/11/2026
 * Modificado: 24/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ConPanelFalso;
use Tests\TestCase;

class PreguntaCalidadTest extends TestCase
{
    use ConPanelFalso;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararPanel();
        $this->conSesionDelPanel();
        $this->prepararLecturas(['preguntas' => [
            ['id' => 7, 'tipo' => 'opcion_multiple', 'enunciado' => '¿Capital de Francia?', 'respuesta_modelo' => 'París', 'opciones' => ['París', 'Roma'], 'lenguaje' => null, 'en_uso' => false],
            ['id' => 8, 'tipo' => 'programacion', 'enunciado' => 'Suma dos números', 'respuesta_modelo' => 'a+b', 'opciones' => null, 'lenguaje' => 'Rust', 'en_uso' => false],
            ['id' => 9, 'tipo' => 'abierta', 'enunciado' => 'Explica la fotosíntesis', 'respuesta_modelo' => 'Luz a energía', 'opciones' => null, 'lenguaje' => null, 'en_uso' => true],
        ]]);
        $this->api->responder('POST', '/teacher/questions', ['data' => ['id' => 25]], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function abierta(array $cambios = []): array
    {
        return array_replace(['tipo' => 'abierta', 'enunciado' => 'Explica la fotosíntesis', 'respuesta_modelo' => 'Luz a energía'], $cambios);
    }

    #[DataProvider('enunciadosSinContenido')]
    public function test_enunciados_sin_contenido_real_se_rechazan(string $enunciado): void
    {
        $this->post('/banco-de-preguntas', $this->abierta(['enunciado' => $enunciado]))->assertSessionHasErrors('enunciado');
        $this->assertSame([], $this->api->llamadasA('POST', '/teacher/questions'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function enunciadosSinContenido(): array
    {
        return [
            'solo signos' => ['???'],
            'caracter de control' => ["Pregunta\x07 uno"],
            'solo invisibles' => ["\u{200B}\u{200B}\u{200B}"],
        ];
    }

    public function test_un_enunciado_con_saltos_de_linea_y_emoji_se_acepta(): void
    {
        $this->post('/banco-de-preguntas', $this->abierta(['enunciado' => "Línea uno\nLínea dos 😀"]))->assertSessionHasNoErrors();
    }

    public function test_opciones_repetidas_con_otras_mayusculas_se_rechazan(): void
    {
        $this->post('/banco-de-preguntas', [
            'tipo' => 'opcion_multiple', 'enunciado' => '¿Aceptas?', 'editor_estructurado' => '1',
            'opciones_lista' => ['Si', ' si ', 'No'], 'correcta' => '0',
        ])->assertSessionHasErrors('opciones_lista');
    }

    public function test_parejas_con_el_mismo_concepto_se_rechazan(): void
    {
        $this->post('/banco-de-preguntas', [
            'tipo' => 'relacion_columnas', 'enunciado' => 'Relaciona', 'editor_estructurado' => '1',
            'pares_lista' => [['izquierda' => 'Perro', 'derecha' => 'Animal'], ['izquierda' => 'perro', 'derecha' => 'Mamífero']],
        ])->assertSessionHasErrors('pares_lista');
    }

    public function test_los_mensajes_no_muestran_nombres_internos(): void
    {
        $this->post('/banco-de-preguntas', [
            'tipo' => 'relacion_columnas', 'enunciado' => 'Relaciona', 'editor_estructurado' => '1',
            'pares_lista' => [['izquierda' => 'A', 'derecha' => ''], ['izquierda' => 'B', 'derecha' => 'C']],
        ])->assertSessionHasErrors('pares_lista.0.derecha');

        $mensajes = collect(session('errors')->getBag('default')->all())->implode(' ');
        $this->assertStringNotContainsString('pares_lista', $mensajes);
        $this->assertStringContainsString('pareja 1', $mensajes);
    }

    public function test_programacion_exige_lenguaje(): void
    {
        $this->post('/banco-de-preguntas', $this->abierta(['tipo' => 'programacion']))->assertSessionHasErrors('lenguaje');
    }

    public function test_otro_lenguaje_exige_el_nombre(): void
    {
        $this->post('/banco-de-preguntas', $this->abierta(['tipo' => 'programacion', 'lenguaje' => 'otro']))->assertSessionHasErrors('lenguaje_otro');
    }

    #[DataProvider('lenguajesValidos')]
    public function test_el_lenguaje_viaja_a_la_api(array $entrada, string $esperado): void
    {
        $this->post('/banco-de-preguntas', $this->abierta(['tipo' => 'programacion'] + $entrada))->assertSessionHasNoErrors();

        $this->assertSame($esperado, $this->api->llamadasA('POST', '/teacher/questions')[0]['datos']['lenguaje']);
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: string}>
     */
    public static function lenguajesValidos(): array
    {
        return [
            'de la lista' => [['lenguaje' => 'Python'], 'Python'],
            'otro lenguaje' => [['lenguaje' => 'otro', 'lenguaje_otro' => ' Rust '], 'Rust'],
            'libre' => [['lenguaje' => 'libre'], 'Libre'],
        ];
    }

    public function test_los_tipos_que_no_son_programacion_no_envian_lenguaje(): void
    {
        $this->post('/banco-de-preguntas', $this->abierta(['lenguaje' => 'Python']))->assertSessionHasNoErrors();

        $this->assertArrayNotHasKey('lenguaje', $this->api->llamadasA('POST', '/teacher/questions')[0]['datos']);
    }

    public function test_la_pantalla_de_edicion_precarga_la_pregunta(): void
    {
        $this->get('/banco-de-preguntas/7/editar')->assertOk()
            ->assertSee('Editar pregunta')->assertSee('¿Capital de Francia?')
            ->assertSee('name="_method" value="PUT"', false);
    }

    public function test_la_edicion_precarga_un_lenguaje_que_no_esta_en_la_lista_como_otro(): void
    {
        $this->get('/banco-de-preguntas/8/editar')->assertOk()
            ->assertSee('value="Rust"', false);
    }

    public function test_una_pregunta_en_uso_no_se_puede_editar(): void
    {
        $this->get('/banco-de-preguntas/9/editar')->assertRedirect(route('panel.banco-de-preguntas'))
            ->assertSessionHas('aviso');
    }

    public function test_una_pregunta_inexistente_responde_404(): void
    {
        $this->get('/banco-de-preguntas/999/editar')->assertNotFound();
    }

    public function test_guardar_cambios_llama_a_la_api_con_put(): void
    {
        $this->api->responder('PUT', '/teacher/questions/7', ['data' => ['id' => 7]]);

        $this->put('/banco-de-preguntas/7', [
            'tipo' => 'opcion_multiple', 'enunciado' => '¿Capital de Italia?', 'editor_estructurado' => '1',
            'opciones_lista' => ['Roma', 'París'], 'correcta' => '0',
        ])->assertRedirect(route('panel.banco-de-preguntas'))->assertSessionHas('exito');

        $this->assertSame('Roma', $this->api->llamadasA('PUT', '/teacher/questions/7')[0]['datos']['respuesta_modelo']);
    }

    public function test_eliminar_llama_a_la_api_con_delete(): void
    {
        $this->api->responder('DELETE', '/teacher/questions/7', ['data' => ['id' => 7]]);

        $this->delete('/banco-de-preguntas/7')->assertRedirect(route('panel.banco-de-preguntas'))->assertSessionHas('exito');
        $this->assertCount(1, $this->api->llamadasA('DELETE', '/teacher/questions/7'));
    }

    public function test_eliminar_una_pregunta_en_uso_muestra_el_motivo(): void
    {
        $this->api->responder('DELETE', '/teacher/questions/9', ['message' => 'Esta pregunta ya forma parte de un examen y no se puede modificar ni eliminar.'], 409);

        $this->delete('/banco-de-preguntas/9')->assertSessionHas('error', 'Esta pregunta ya forma parte de un examen y no se puede modificar ni eliminar.');
    }

    public function test_la_lista_ofrece_editar_y_eliminar_solo_si_la_pregunta_no_esta_en_uso(): void
    {
        $pagina = $this->get('/banco-de-preguntas')->assertOk();

        $pagina->assertSee('banco-de-preguntas/7/editar', false)
            ->assertSee('confirmar-eliminar-pregunta-7', false)
            ->assertDontSee('banco-de-preguntas/9/editar', false)
            ->assertSee('En uso en un examen');
    }
}
