<?php

/**
 * Archivo: ResultadosPanelServiceTest.php
 * Módulo: tests/Unit
 * Descripción: Verifica con casos conocidos cómo se arman las filas de resultados: estado de la nota («Pendiente
 *              de calificar» mientras falten preguntas por calificar), nota en la escala del examen, fechas,
 *              origen de cada calificación y permisos de calificación (Manual COD-WEB, secciones 6.17 y 8.3).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ConsultasPanelService;
use App\Services\ResultadosPanelService;
use Tests\Support\PanelApiFalso;
use Tests\TestCase;

class ResultadosPanelServiceTest extends TestCase
{
    private ResultadosPanelService $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->servicio = new ResultadosPanelService(new ConsultasPanelService(new PanelApiFalso()));
    }

    public function test_el_estado_de_la_nota_depende_del_estado_del_intento_y_de_las_preguntas_calificadas(): void
    {
        $this->assertSame('calificada', $this->servicio->estadoDeNota(['estado' => 'finalizado', 'completo' => true, 'calificacion' => 8.5]));
        $this->assertSame('pendiente', $this->servicio->estadoDeNota(['estado' => 'finalizado', 'completo' => false, 'calificacion' => null]));
        $this->assertSame('sin_entregar', $this->servicio->estadoDeNota(['estado' => 'en_progreso', 'completo' => false, 'calificacion' => null]));
        $this->assertSame('anulado', $this->servicio->estadoDeNota(['estado' => 'anulado', 'completo' => false, 'calificacion' => null]));
    }

    public function test_las_filas_unen_resultados_con_fechas_y_dan_formato_a_la_nota(): void
    {
        $filas = $this->servicio->filas(
            collect([
                ['intento_id' => 1, 'nombre' => 'Ana', 'estado' => 'finalizado', 'calificadas' => 2, 'total_preguntas' => 2, 'calificacion' => 8.50, 'escala' => '0-10', 'completo' => true],
                ['intento_id' => 2, 'nombre' => 'Beto', 'estado' => 'finalizado', 'calificadas' => 1, 'total_preguntas' => 2, 'calificacion' => null, 'escala' => '0-10', 'completo' => false],
            ]),
            collect([
                ['id' => 1, 'fecha_entrega' => '2026-11-06 10:30:00+00'],
                ['id' => 2, 'fecha_entrega' => null],
            ]),
        );

        $this->assertSame('8.5', $filas[0]['nota']);
        $this->assertSame('06/11/2026 10:30', $filas[0]['entrega']);
        $this->assertSame('calificada', $filas[0]['estado_de_nota']);
        $this->assertNull($filas[1]['nota']);
        $this->assertNull($filas[1]['entrega']);
        $this->assertSame('pendiente', $filas[1]['estado_de_nota']);
        $this->assertSame(['intentos' => 2, 'calificados' => 1, 'pendientes' => 1], $this->servicio->resumen($filas));
    }

    public function test_el_detalle_calcula_el_aporte_en_la_escala_del_examen_y_el_origen_de_cada_calificacion(): void
    {
        $respuestas = collect([
            ['id' => 1, 'tipo' => 'opcion_multiple', 'enunciado' => 'P1', 'respuesta_texto' => 'A', 'calificacion_final' => '100.00', 'revisada_manual' => false, 'ponderacion' => '40.00', 'respuesta_modelo_snapshot' => 'A'],
            ['id' => 2, 'tipo' => 'abierta', 'enunciado' => 'P2', 'respuesta_texto' => '  ', 'calificacion_final' => '50.00', 'revisada_manual' => true, 'ponderacion' => '60.00', 'respuesta_modelo_snapshot' => null],
            ['id' => 3, 'tipo' => 'opcion_multiple', 'enunciado' => 'P3', 'respuesta_texto' => 'B', 'calificacion_final' => null, 'revisada_manual' => false, 'ponderacion' => '10.00', 'respuesta_modelo_snapshot' => 'C'],
        ]);

        $detalle = $this->servicio->detalle(
            ['escala' => '0-10'],
            ['nombre' => 'Ana', 'estado' => 'finalizado', 'completo' => false, 'calificacion' => null, 'calificadas' => 2, 'total_preguntas' => 4, 'escala' => '0-10'],
            ['numero_intento' => 1, 'fecha_inicio' => '2026-11-06 09:00:00+00', 'fecha_entrega' => '2026-11-06 10:00:00+00'],
            $respuestas,
        );

        $this->assertSame('pendiente', $detalle['estado_de_nota']);
        $this->assertSame(['automatica', 'manual', 'sin_calificar'], $detalle['respuestas']->pluck('origen')->all());
        // 100 % de una pregunta de 40 % sobre 10 puntos: 4; 50 % de una de 60 %: 3.
        $this->assertSame(['4', '3', null], $detalle['respuestas']->pluck('aporte')->all());
        $this->assertNull($detalle['respuestas'][1]['respuesta'], 'Una respuesta en blanco se trata como sin respuesta.');
        $this->assertNull($detalle['respuestas'][1]['modelo']);
        $this->assertSame('A', $detalle['respuestas'][0]['modelo']);
        $this->assertSame(1, $detalle['sin_responder']);
        $this->assertTrue($detalle['puede_calificar']);
        $this->assertTrue($detalle['puede_autocalificar']);
    }

    public function test_solo_se_califica_un_intento_entregado_y_no_hay_autocalificacion_sin_opcion_multiple_pendiente(): void
    {
        $enProgreso = $this->servicio->detalle(['escala' => '0-100'], ['nombre' => 'Ana', 'estado' => 'en_progreso', 'calificadas' => 0, 'total_preguntas' => 1], [], collect());

        $this->assertFalse($enProgreso['puede_calificar']);
        $this->assertFalse($enProgreso['puede_autocalificar']);

        $abiertaPendiente = $this->servicio->detalle(
            ['escala' => '0-100'],
            ['nombre' => 'Ana', 'estado' => 'finalizado', 'calificadas' => 0, 'total_preguntas' => 1],
            [],
            collect([['id' => 1, 'tipo' => 'abierta', 'enunciado' => 'P', 'respuesta_texto' => 'x', 'calificacion_final' => null, 'revisada_manual' => false, 'ponderacion' => '100', 'respuesta_modelo_snapshot' => 'm']]),
        );

        $this->assertTrue($abiertaPendiente['puede_calificar']);
        $this->assertFalse($abiertaPendiente['puede_autocalificar']);
    }

    public function test_da_formato_a_las_cifras_sin_ceros_sobrantes(): void
    {
        $this->assertSame('8.5', $this->servicio->numero(8.5));
        $this->assertSame('10', $this->servicio->numero(10.0));
        $this->assertSame('0', $this->servicio->numero(0.0));
        $this->assertSame('66.67', $this->servicio->numero(66.666));
    }
}
