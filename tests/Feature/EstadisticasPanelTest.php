<?php

/**
 * Archivo: EstadisticasPanelTest.php
 * Módulo: tests/Feature
 * Descripción: Verifica la página de Estadísticas con la API sustituida: cifras calculadas con casos conocidos
 *              (por examen, por grupo y general), gráficas con el componente x-grafica-barras, preguntas con mayor
 *              error, estado vacío, paginación de 15 filas y los casos 401, 403 y 404
 *              (Manual COD-WEB, secciones 6.16, 6.17 y 8.3).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Tests\Support\ConPanelFalso;
use Tests\TestCase;

class EstadisticasPanelTest extends TestCase
{
    use ConPanelFalso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPanel();
        $this->conSesionDelPanel();
    }

    /**
     * Prepara dos exámenes publicados del grupo 1: el 2 (escala 0-10, notas 9, 5 y 6 más un intento pendiente y uno
     * en progreso) y el 4 (escala 0-100, notas 80 y 40), con las respuestas de los intentos entregados.
     */
    private function prepararDatos(): void
    {
        $examenes = $this->examenesDeEjemplo(4);
        $examenes[0]['escala'] = '0-100';

        $this->prepararLecturas(['examenes' => $examenes]);

        $this->prepararResultados($examenes[2], [
            $this->resultado(21, 'Ana Pérez', 'finalizado', 9.0, '0-10'),
            $this->resultado(22, 'Beto Ríos', 'finalizado', 5.0, '0-10'),
            $this->resultado(23, 'Carla Soto', 'finalizado', 6.0, '0-10'),
            $this->resultado(24, 'Dani Luna', 'finalizado', null, '0-10'),
            $this->resultado(25, 'Eva Mora', 'en_progreso', null, '0-10'),
        ]);
        $this->prepararResultados($examenes[0], [
            $this->resultado(41, 'Fer Gil', 'finalizado', 80.0, '0-100'),
            $this->resultado(42, 'Gina Paz', 'finalizado', 40.0, '0-100'),
        ]);

        // Preguntas 11 (opción múltiple) y 12 (abierta) del examen 2.
        $this->respuestas(21, [100.0, 80.0]);
        $this->respuestas(22, [0.0, 40.0]);
        $this->respuestas(23, [100.0, 60.0]);
        $this->respuestas(24, [100.0, null]);
        $this->respuestas(41, [100.0, 60.0]);
        $this->respuestas(42, [50.0, 30.0]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resultado(int $intento, string $alumno, string $estado, ?float $nota, string $escala): array
    {
        return [
            'intento_id' => $intento, 'alumno_id' => $intento, 'nombre' => $alumno, 'estado' => $estado,
            'calificadas' => $nota !== null ? 2 : 1, 'total_preguntas' => 2, 'calificacion' => $nota,
            'escala' => $escala, 'completo' => $nota !== null,
        ];
    }

    /**
     * @param  list<float|null>  $calificaciones  Una por pregunta, en el orden 11 y 12.
     */
    private function respuestas(int $intento, array $calificaciones): void
    {
        $this->api->responder('GET', '/teacher/attempts/'.$intento.'/answers', ['data' => [
            'intento' => ['id' => $intento, 'examen_id' => 2, 'alumno_id' => $intento, 'estado' => 'finalizado', 'escala' => '0-10'],
            'respuestas' => [
                [
                    'id' => $intento * 10 + 1, 'examen_pregunta_id' => 11, 'tipo' => 'opcion_multiple', 'enunciado' => 'Pregunta 11',
                    'respuesta_texto' => 'A', 'calificacion_final' => $calificaciones[0], 'revisada_manual' => false,
                    'ponderacion' => '50.00', 'respuesta_modelo_snapshot' => 'A',
                ],
                [
                    'id' => $intento * 10 + 2, 'examen_pregunta_id' => 12, 'tipo' => 'abierta', 'enunciado' => 'Pregunta 12',
                    'respuesta_texto' => 'Texto', 'calificacion_final' => $calificaciones[1], 'revisada_manual' => true,
                    'ponderacion' => '50.00', 'respuesta_modelo_snapshot' => 'Modelo',
                ],
            ],
        ]]);
    }

    public function test_las_tablas_muestran_las_cifras_calculadas_por_examen_por_grupo_y_en_general(): void
    {
        $this->prepararDatos();

        $respuesta = $this->get('/estadisticas?examen=2')->assertOk();
        $html = $respuesta->getContent();

        $this->assertSame(
            ['Examen 2', 'Materia 1', 'De 0 a 10', '5', '3', '6.67', '5', '9', '66.67 %'],
            $this->consultar($html, '//table[caption[contains(., "Estadísticas por examen")]]//tr[td[normalize-space()="Examen 2"]]/td'),
        );
        $this->assertSame(
            ['Examen 4', 'Materia 1', 'De 0 a 100', '2', '2', '60', '40', '80', '50 %'],
            $this->consultar($html, '//table[caption[contains(., "Estadísticas por examen")]]//tr[td[normalize-space()="Examen 4"]]/td'),
        );
        $this->assertSame(
            ['Materia 1', '2', '7', '5', '64 %', '40 %', '90 %', '60 %'],
            $this->consultar($html, '//table[caption[contains(., "Estadísticas por grupo")]]//tr[td[normalize-space()="Materia 1"]]/td'),
        );

        $respuesta
            ->assertSee('Resumen general')
            ->assertSee('Intentos registrados')
            ->assertSee('1 pendiente de calificar')
            ->assertSee('Se aprueba con 60 % de la escala o más.');

        // Tarjetas del resumen general: 7 intentos, 5 calificados, promedio 64 % y aprobación 60 %.
        $this->assertSame(['7', '5', '64 %', '60 %'], $this->consultar($html, '//section[@aria-labelledby="titulo-resumen-general"]//p[contains(@class, "text-2xl")]'));
        $this->assertSame(['Estadísticas'], $this->consultar($html, '//aside//a[@aria-current="page"]/span[1]'));
    }

    public function test_las_graficas_usan_el_componente_con_titulo_ejes_leyenda_y_fuente(): void
    {
        $this->prepararDatos();

        $respuesta = $this->get('/estadisticas?examen=2')
            ->assertOk()
            ->assertSee('Promedio y aprobación por examen')
            ->assertSee('Promedio y aprobación por grupo')
            ->assertSee('Distribución de notas: Examen 2')
            ->assertSee('Preguntas con mayor error: Examen 2')
            ->assertSee('Porcentaje (%)')
            ->assertSee('Alumnos (cantidad)')
            ->assertSee('Rango de nota (De 0 a 10)')
            ->assertSee('Promedio (% de la escala)')
            ->assertSee('Aprobación (%)')
            ->assertSee('Error promedio (%)')
            ->assertSee('Fuente: Resultados de los exámenes publicados')
            ->assertSee('fill-acento-claro', false)
            ->assertSee('fill-acento-medio', false)
            // Etiquetas directas: promedio 66.67 % y aprobación 66.67 % del examen 2.
            ->assertSee('>66.7 %<', false);

        $html = $respuesta->getContent();

        $this->assertSame(4, count($this->consultar($html, '//figure')));
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{6}\b/', $html);
        $this->assertStringNotContainsString('cdn.', $html);
    }

    public function test_la_distribucion_de_notas_cuenta_alumnos_por_rango(): void
    {
        $this->prepararDatos();

        $html = $this->get('/estadisticas?examen=2')->assertOk()->getContent();

        $filas = $this->consultar($html, '//figure[.//figcaption[contains(., "Distribución de notas")]]//table[@class="sr-only"]/tbody/tr/*');

        // Notas 9, 5 y 6 sobre 10: un alumno en 4–6, otro en 6–8 y otro en 8–10.
        $this->assertSame(['0–2', '0', '2–4', '0', '4–6', '1', '6–8', '1', '8–10', '1'], $filas);
    }

    public function test_las_preguntas_con_mayor_error_se_ordenan_y_muestran_su_error_promedio(): void
    {
        $this->prepararDatos();

        $html = $this->get('/estadisticas?examen=2')->assertOk()->getContent();

        $filas = $this->consultar($html, '//table[caption[normalize-space()="Preguntas con mayor error"]]/tbody/tr');

        $this->assertCount(2, $filas);
        $this->assertSame(
            ['P1', 'Pregunta 12', 'Abierta', '3', '1', '40 %'],
            $this->consultar($html, '//table[caption[normalize-space()="Preguntas con mayor error"]]/tbody/tr[1]/td'),
        );
        $this->assertSame(
            ['P2', 'Pregunta 11', 'Opción múltiple', '4', '1', '25 %'],
            $this->consultar($html, '//table[caption[normalize-space()="Preguntas con mayor error"]]/tbody/tr[2]/td'),
        );
    }

    public function test_sin_examen_elegido_el_detalle_usa_el_mas_reciente_con_notas(): void
    {
        $this->prepararDatos();

        $this->get('/estadisticas')->assertOk()->assertSee('Distribución de notas: Examen 4')->assertSee('80–100');
        $this->assertCount(1, $this->api->llamadasA('GET', '/teacher/attempts/41/answers'));
        $this->assertSame([], $this->api->llamadasA('GET', '/teacher/attempts/21/answers'), 'Solo se leen las respuestas del examen elegido.');
    }

    public function test_el_selector_del_detalle_lista_solo_examenes_publicados(): void
    {
        $this->prepararDatos();

        $html = $this->get('/estadisticas?examen=2')->assertOk()->getContent();

        $this->assertSame(['Examen 4', 'Examen 2'], $this->consultar($html, '//select[@name="examen"]/option'));
        $this->assertSame(['Examen 2'], $this->consultar($html, '//select[@name="examen"]/option[@selected]'));
    }

    public function test_sin_resultados_muestra_el_estado_vacio_en_espanol_sin_graficas(): void
    {
        $this->prepararLecturas();

        $respuesta = $this->get('/estadisticas')
            ->assertOk()
            ->assertSee('Aún no hay resultados para mostrar.')
            ->assertDontSee('Resumen general');

        $this->assertSame(0, count($this->consultar($respuesta->getContent(), '//figure')));
    }

    public function test_si_ningun_intento_esta_calificado_no_hay_estadisticas(): void
    {
        $examenes = $this->examenesDeEjemplo(2);
        $this->prepararLecturas(['examenes' => $examenes]);
        $this->prepararResultados($examenes[0], [$this->resultado(21, 'Ana Pérez', 'finalizado', null, '0-10')]);

        $this->get('/estadisticas')->assertOk()->assertSee('Aún no hay resultados para mostrar.')->assertDontSee('Resumen general');
    }

    public function test_un_examen_borrador_o_inexistente_devuelve_404(): void
    {
        $this->prepararDatos();

        $this->get('/estadisticas?examen=1')->assertNotFound();
        $this->get('/estadisticas?examen=999')->assertNotFound();
    }

    public function test_las_tablas_de_estadisticas_pagan_de_a_15_filas(): void
    {
        $examenes = $this->examenesDeEjemplo(32);
        $this->prepararLecturas(['examenes' => $examenes]);

        foreach ($examenes as $examen) {
            if ($examen['publicado']) {
                $this->prepararResultados($examen, [$this->resultado(1000 + $examen['id'], 'Alumno '.$examen['id'], 'finalizado', 8.0, '0-10')]);
                $this->api->responder('GET', '/teacher/attempts/'.(1000 + $examen['id']).'/answers', ['data' => [
                    'intento' => ['id' => 1000 + $examen['id'], 'examen_id' => $examen['id'], 'alumno_id' => 1, 'estado' => 'finalizado', 'escala' => '0-10'],
                    'respuestas' => [],
                ]]);
            }
        }

        $primera = $this->get('/estadisticas')->assertOk()->assertSee('Mostrando 1 a 15 de 16');
        $this->assertSame(15, count($this->consultar($primera->getContent(), '//table[caption[contains(., "Estadísticas por examen")]]/tbody/tr')));

        $segunda = $this->get('/estadisticas?pagina_examenes=2')->assertOk()->assertSee('Mostrando 16 a 16 de 16');
        $this->assertSame(1, count($this->consultar($segunda->getContent(), '//table[caption[contains(., "Estadísticas por examen")]]/tbody/tr')));
        // La gráfica por examen no se paginó: solo muestra los 10 más recientes.
        $this->assertCount(10, $this->consultar($segunda->getContent(), '//figure[.//figcaption[contains(., "Promedio y aprobación por examen")]]//table[@class="sr-only"]/tbody/tr'));
    }

    public function test_un_403_de_la_api_muestra_la_pagina_amable(): void
    {
        $this->prepararDatos();
        $this->api->responder('GET', '/teacher/exams/2/results', ['message' => 'Docente no registrado.'], 403);

        $this->get('/estadisticas')->assertStatus(503)->assertSee('No pudimos cargar la página')->assertDontSee('Docente no registrado');
    }

    public function test_un_401_de_la_api_vuelve_al_login(): void
    {
        $this->api->responder('GET', '/teacher/groups', ['message' => 'Token inválido o expirado.'], 401);

        $this->get('/estadisticas')
            ->assertRedirect(route('login'))
            ->assertSessionHas('aviso', 'Tu sesión venció. Inicia sesión de nuevo para continuar.');
    }

    public function test_sin_sesion_redirige_al_login_sin_llamar_a_la_api(): void
    {
        $this->flushSession();

        $this->get('/estadisticas')->assertRedirect(route('login'));
        $this->assertSame([], $this->api->llamadas);
    }

    /**
     * Ejecuta una consulta XPath y devuelve el texto de cada nodo encontrado.
     *
     * @return list<string>
     */
    private function consultar(string $html, string $consulta): array
    {
        $documento = new DOMDocument();
        libxml_use_internal_errors(true);
        $documento->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();

        $textos = [];

        foreach ((new DOMXPath($documento))->query($consulta) as $nodo) {
            $textos[] = trim($nodo->textContent);
        }

        return $textos;
    }
}
