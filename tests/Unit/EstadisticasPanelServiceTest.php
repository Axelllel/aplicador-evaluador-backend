<?php

/**
 * Archivo: EstadisticasPanelServiceTest.php
 * Módulo: tests/Unit
 * Descripción: Verifica con casos conocidos el cálculo de estadísticas del panel: promedio, mínimo, máximo, tasa de
 *              aprobación (60 % de la escala), distribución en cinco rangos, estadísticas por examen y por grupo con
 *              escalas distintas y preguntas con mayor error (Manual COD-WEB, sección 8.3).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\EstadisticasPanelService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class EstadisticasPanelServiceTest extends TestCase
{
    private EstadisticasPanelService $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->servicio = new EstadisticasPanelService();
    }

    public function test_convierte_la_nota_a_porcentaje_de_su_escala(): void
    {
        $this->assertSame(85.0, $this->servicio->aPorcentaje(8.5, '0-10'));
        $this->assertSame(85.0, $this->servicio->aPorcentaje(85.0, '0-100'));
        $this->assertSame(0.0, $this->servicio->aPorcentaje(0.0, '0-10'));
    }

    public function test_el_resumen_calcula_promedio_minimo_maximo_y_aprobacion(): void
    {
        $resumen = $this->servicio->resumen(collect([90.0, 50.0, 60.0]));

        $this->assertSame(3, $resumen['calificados']);
        $this->assertSame(66.67, $resumen['promedio']);
        $this->assertSame(50.0, $resumen['minimo']);
        $this->assertSame(90.0, $resumen['maximo']);
        $this->assertSame(2, $resumen['aprobados']);
        $this->assertSame(66.67, $resumen['tasa_aprobacion']);
        $this->assertSame([0, 0, 1, 1, 1], $resumen['distribucion']);
    }

    public function test_sin_datos_el_resumen_devuelve_ceros_y_nulos(): void
    {
        $resumen = $this->servicio->resumen(collect());

        $this->assertSame(0, $resumen['calificados']);
        $this->assertNull($resumen['promedio']);
        $this->assertNull($resumen['minimo']);
        $this->assertNull($resumen['maximo']);
        $this->assertNull($resumen['tasa_aprobacion']);
        $this->assertSame([0, 0, 0, 0, 0], $resumen['distribucion']);
    }

    public function test_sesenta_por_ciento_exacto_aprueba_y_cincuenta_y_nueve_con_noventa_y_nueve_no(): void
    {
        $this->assertSame(1, $this->servicio->resumen(collect([60.0]))['aprobados']);
        $this->assertSame(0, $this->servicio->resumen(collect([59.99]))['aprobados']);
    }

    public function test_los_limites_de_los_rangos_incluyen_el_inferior_y_el_ultimo_incluye_el_maximo(): void
    {
        $this->assertSame([1, 1, 1, 1, 2], $this->servicio->distribucion(collect([0.0, 20.0, 40.0, 60.0, 80.0, 100.0])));
        $this->assertSame([1, 0, 0, 0, 1], $this->servicio->distribucion(collect([19.99, 100.0])));
    }

    public function test_las_etiquetas_de_los_rangos_siguen_la_escala(): void
    {
        $this->assertSame(['0–2', '2–4', '4–6', '6–8', '8–10'], $this->servicio->etiquetasDeRangos('0-10'));
        $this->assertSame(['0–20', '20–40', '40–60', '60–80', '80–100'], $this->servicio->etiquetasDeRangos('0-100'));
    }

    public function test_estadisticas_por_examen_cuentan_pendientes_y_solo_promedian_los_calificados(): void
    {
        $examenes = $this->servicio->porExamen(collect([$this->datosDelExamenDeDiez()]));
        $examen = $examenes->first();

        $this->assertSame(2, $examen['id']);
        $this->assertSame(5, $examen['intentos']);
        $this->assertSame(4, $examen['entregados']);
        $this->assertSame(1, $examen['pendientes']);
        $this->assertSame(3, $examen['calificados']);
        // En la escala del propio examen (0 a 10).
        $this->assertSame(6.67, $examen['promedio']);
        $this->assertSame(5.0, $examen['minimo']);
        $this->assertSame(9.0, $examen['maximo']);
        $this->assertSame(66.67, $examen['promedio_porcentaje']);
        $this->assertSame(2, $examen['aprobados']);
        $this->assertSame(66.67, $examen['tasa_aprobacion']);
        $this->assertSame([0, 0, 1, 1, 1], $examen['distribucion']);
    }

    public function test_un_examen_sin_intentos_calificados_no_tiene_promedio(): void
    {
        $examen = $this->servicio->porExamen(collect([[
            'examen' => ['id' => 9, 'nombre' => 'Vacío', 'grupo_id' => 1, 'escala' => '0-10'],
            'resultados' => collect([['intento_id' => 1, 'estado' => 'en_progreso', 'completo' => false, 'calificacion' => null]]),
        ]]))->first();

        $this->assertSame(1, $examen['intentos']);
        $this->assertSame(0, $examen['calificados']);
        $this->assertNull($examen['promedio']);
        $this->assertNull($examen['tasa_aprobacion']);
    }

    public function test_el_grupo_junta_examenes_con_escalas_distintas_como_porcentaje(): void
    {
        $porExamen = $this->servicio->porExamen(collect([$this->datosDelExamenDeDiez(), $this->datosDelExamenDeCien()]));
        $grupos = $this->servicio->porGrupo($porExamen, collect([
            ['id' => 1, 'materia' => 'Álgebra'],
            ['id' => 2, 'materia' => 'Sin exámenes'],
        ]));

        $this->assertCount(1, $grupos, 'Un grupo sin exámenes no aparece.');

        $grupo = $grupos->first();

        $this->assertSame('Álgebra', $grupo['nombre']);
        $this->assertSame(2, $grupo['examenes']);
        $this->assertSame(7, $grupo['intentos']);
        $this->assertSame(1, $grupo['pendientes']);
        $this->assertSame(5, $grupo['calificados']);
        // Porcentajes juntos: 90, 50, 60, 80 y 40.
        $this->assertSame(64.0, $grupo['promedio_porcentaje']);
        $this->assertSame(40.0, $grupo['minimo_porcentaje']);
        $this->assertSame(90.0, $grupo['maximo_porcentaje']);
        $this->assertSame(3, $grupo['aprobados']);
        $this->assertSame(60.0, $grupo['tasa_aprobacion']);
        $this->assertSame([0, 0, 2, 1, 2], $grupo['distribucion']);
    }

    public function test_el_resumen_general_junta_todos_los_examenes(): void
    {
        $porExamen = $this->servicio->porExamen(collect([$this->datosDelExamenDeDiez(), $this->datosDelExamenDeCien()]));

        $general = $this->servicio->general($porExamen);

        $this->assertSame(2, $general['examenes']);
        $this->assertSame(7, $general['intentos']);
        $this->assertSame(1, $general['pendientes']);
        $this->assertSame(5, $general['calificados']);
        $this->assertSame(64.0, $general['promedio']);
        $this->assertSame(60.0, $general['tasa_aprobacion']);
    }

    public function test_las_preguntas_con_mayor_error_se_ordenan_por_error_y_omiten_las_sin_calificar(): void
    {
        $preguntas = $this->servicio->preguntasConMasError($this->respuestasDeEjemplo());

        $this->assertCount(2, $preguntas);

        $this->assertSame(12, $preguntas[0]['pregunta_id']);
        $this->assertSame('P1', $preguntas[0]['etiqueta']);
        $this->assertSame(3, $preguntas[0]['respuestas']);
        $this->assertSame(60.0, $preguntas[0]['promedio']);
        $this->assertSame(40.0, $preguntas[0]['error']);
        $this->assertSame(1, $preguntas[0]['reprobadas']);

        $this->assertSame(11, $preguntas[1]['pregunta_id']);
        $this->assertSame('P2', $preguntas[1]['etiqueta']);
        $this->assertSame(4, $preguntas[1]['respuestas']);
        $this->assertSame(25.0, $preguntas[1]['error']);
        $this->assertSame(1, $preguntas[1]['reprobadas']);
    }

    public function test_las_preguntas_con_mayor_error_respetan_el_limite(): void
    {
        $this->assertCount(1, $this->servicio->preguntasConMasError($this->respuestasDeEjemplo(), 1));
        $this->assertCount(0, $this->servicio->preguntasConMasError(collect()));
    }

    public function test_los_datos_de_las_graficas_usan_series_principal_y_secundaria(): void
    {
        $porExamen = $this->servicio->porExamen(collect([$this->datosDelExamenDeCien(), $this->datosDelExamenDeDiez()]));

        $grafica = $this->servicio->graficaPorExamen($porExamen);

        // De izquierda a derecha, del más antiguo (id 2) al más reciente (id 4).
        $this->assertSame(['Examen 2', 'Examen 4'], $grafica['categorias']);
        $this->assertSame('principal', $grafica['series'][0]['tono']);
        $this->assertSame('secundaria', $grafica['series'][1]['tono']);
        $this->assertSame([66.67, 60.0], $grafica['series'][0]['valores']);
        $this->assertSame([66.67, 50.0], $grafica['series'][1]['valores']);

        $distribucion = $this->servicio->graficaDeDistribucion($porExamen->firstWhere('id', 2));

        $this->assertSame(['0–2', '2–4', '4–6', '6–8', '8–10'], $distribucion['categorias']);
        $this->assertSame([0, 0, 1, 1, 1], $distribucion['series'][0]['valores']);
    }

    /**
     * Examen de escala 0-10 con tres notas (9, 5 y 6), un intento pendiente y uno en progreso.
     *
     * @return array{examen: array<string, mixed>, resultados: Collection<int, array<string, mixed>>}
     */
    private function datosDelExamenDeDiez(): array
    {
        return [
            'examen' => ['id' => 2, 'nombre' => 'Examen 2', 'grupo_id' => 1, 'escala' => '0-10'],
            'resultados' => collect([
                ['intento_id' => 21, 'estado' => 'finalizado', 'completo' => true, 'calificacion' => 9.0],
                ['intento_id' => 22, 'estado' => 'finalizado', 'completo' => true, 'calificacion' => 5.0],
                ['intento_id' => 23, 'estado' => 'finalizado', 'completo' => true, 'calificacion' => 6.0],
                ['intento_id' => 24, 'estado' => 'finalizado', 'completo' => false, 'calificacion' => null],
                ['intento_id' => 25, 'estado' => 'en_progreso', 'completo' => false, 'calificacion' => null],
            ]),
        ];
    }

    /**
     * Examen de escala 0-100 con dos notas (80 y 40).
     *
     * @return array{examen: array<string, mixed>, resultados: Collection<int, array<string, mixed>>}
     */
    private function datosDelExamenDeCien(): array
    {
        return [
            'examen' => ['id' => 4, 'nombre' => 'Examen 4', 'grupo_id' => 1, 'escala' => '0-100'],
            'resultados' => collect([
                ['intento_id' => 41, 'estado' => 'finalizado', 'completo' => true, 'calificacion' => 80.0],
                ['intento_id' => 42, 'estado' => 'finalizado', 'completo' => true, 'calificacion' => 40.0],
            ]),
        ];
    }

    /**
     * Respuestas de cuatro intentos a dos preguntas: la 11 (opción múltiple) y la 12 (abierta).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function respuestasDeEjemplo(): Collection
    {
        $fila = fn (int $pregunta, string $tipo, ?float $calificacion): array => [
            'examen_pregunta_id' => $pregunta,
            'tipo' => $tipo,
            'enunciado' => 'Pregunta '.$pregunta,
            'calificacion_final' => $calificacion,
        ];

        return collect([
            $fila(11, 'opcion_multiple', 100.0), $fila(12, 'abierta', 80.0),
            $fila(11, 'opcion_multiple', 0.0), $fila(12, 'abierta', 40.0),
            $fila(11, 'opcion_multiple', 100.0), $fila(12, 'abierta', 60.0),
            $fila(11, 'opcion_multiple', 100.0), $fila(12, 'abierta', null),
        ]);
    }
}
