<?php

/**
 * Archivo: GraficaBarrasTest.php
 * Módulo: tests/Feature
 * Descripción: Verifica el componente x-grafica-barras: geometría proporcional, ejes con unidad, leyenda, fuente,
 *              etiquetas directas, colores solo desde tokens (sin hexadecimales), tabla alternativa para lectores
 *              de pantalla y estado vacío (Manual de Calidad, sección 3.10; Manual COD-WEB, sección 6.14).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\View\Components\GraficaBarras;
use Tests\TestCase;

class GraficaBarrasTest extends TestCase
{
    /**
     * @return list<array<string, mixed>>
     */
    private function series(): array
    {
        return [
            ['nombre' => 'Promedio (% de la escala)', 'valores' => [80.0, 50.0], 'tono' => 'principal'],
            ['nombre' => 'Aprobación (%)', 'valores' => [100.0, 25.0], 'tono' => 'secundaria'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>|null  $series
     * @param  list<string>  $categorias
     */
    private function grafica(?array $series = null, array $categorias = ['Examen A', 'Examen B']): GraficaBarras
    {
        return new GraficaBarras(
            titulo: 'Promedio y aprobación por examen',
            categorias: $categorias,
            series: $series ?? $this->series(),
            ejeX: 'Examen',
            ejeY: 'Porcentaje (%)',
            fuente: 'Resultados de prueba',
            unidad: '%',
            maximo: 100.0,
        );
    }

    public function test_la_geometria_es_proporcional_al_valor_y_al_tope_del_eje(): void
    {
        $dibujo = $this->grafica()->geometria();
        $altoUtil = $dibujo['base'] - $dibujo['tope'];

        $this->assertGreaterThanOrEqual(GraficaBarras::ANCHO_MINIMO, $dibujo['ancho']);

        $barras = array_merge($dibujo['grupos'][0]['barras'], $dibujo['grupos'][1]['barras']);

        // 80, 100, 50 y 25 sobre un tope de 100.
        $this->assertEqualsWithDelta($altoUtil * 0.8, $barras[0]['alto'], 0.01);
        $this->assertEqualsWithDelta($altoUtil, $barras[1]['alto'], 0.01);
        $this->assertEqualsWithDelta($altoUtil * 0.5, $barras[2]['alto'], 0.01);
        $this->assertEqualsWithDelta($altoUtil * 0.25, $barras[3]['alto'], 0.01);

        // Toda barra termina en el eje horizontal.
        foreach ($barras as $barra) {
            $this->assertEqualsWithDelta($dibujo['base'], $barra['y'] + $barra['alto'], 0.01);
        }

        $this->assertSame(['0', '20', '40', '60', '80', '100'], array_column($dibujo['marcas'], 'etiqueta'));
        $this->assertSame('80 %', $barras[0]['etiqueta']);
    }

    public function test_sin_tope_fijo_el_eje_se_redondea_a_pasos_enteros_para_cantidades(): void
    {
        $grafica = new GraficaBarras('Distribución', ['A', 'B'], [['nombre' => 'Alumnos', 'valores' => [3, 7]]], 'Rango', 'Alumnos (cantidad)', 'Prueba');

        $this->assertSame(['0', '2', '4', '6', '8'], array_column($grafica->geometria()['marcas'], 'etiqueta'));
    }

    public function test_el_dibujo_incluye_titulo_ejes_leyenda_fuente_y_etiquetas_directas(): void
    {
        $this->blade(
            '<x-grafica-barras titulo="Promedio y aprobación por examen" :categorias="$categorias" :series="$series" eje-x="Examen" eje-y="Porcentaje (%)" fuente="Resultados de prueba" unidad="%" :maximo="100" />',
            ['categorias' => ['Examen A', 'Examen B'], 'series' => $this->series()],
        )
            ->assertSee('Promedio y aprobación por examen')
            ->assertSee('<svg', false)
            ->assertSee('role="img"', false)
            ->assertSee('Porcentaje (%)')
            ->assertSee('>Examen<', false)
            ->assertSee('Promedio (% de la escala)')
            ->assertSee('Aprobación (%)')
            ->assertSee('Fuente: Resultados de prueba.')
            ->assertSee('>80 %<', false)
            ->assertSee('>100 %<', false)
            ->assertSee('>25 %<', false);
    }

    public function test_los_colores_salen_de_tokens_y_nunca_de_hexadecimales(): void
    {
        $html = (string) $this->blade(
            '<x-grafica-barras titulo="T" :categorias="$categorias" :series="$series" eje-x="X" eje-y="Y" fuente="F" />',
            ['categorias' => ['A'], 'series' => [
                ['nombre' => 'Uno', 'valores' => [1], 'tono' => 'principal'],
                ['nombre' => 'Dos', 'valores' => [2], 'tono' => 'secundaria'],
                ['nombre' => 'Tres', 'valores' => [3], 'tono' => 'apoyo'],
            ]],
        );

        $this->assertStringContainsString('fill-acento-claro', $html);
        $this->assertStringContainsString('fill-acento-medio', $html);
        $this->assertStringContainsString('fill-primario', $html);
        $this->assertStringContainsString('stroke-primario', $html);
        $this->assertStringContainsString('url(#grafica-', $html);
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{6}\b/', $html);
        $this->assertDoesNotMatchRegularExpression('/\b(?:rgb|hsl)a?\(/', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('drop-shadow', $html);
    }

    public function test_la_serie_secundaria_lleva_rayas_para_no_depender_solo_del_color(): void
    {
        $html = (string) $this->blade(
            '<x-grafica-barras titulo="T" :categorias="$categorias" :series="$series" eje-x="X" eje-y="Y" fuente="F" />',
            ['categorias' => ['A'], 'series' => [['nombre' => 'Dos', 'valores' => [2], 'tono' => 'secundaria']]],
        );

        $this->assertStringContainsString('<pattern', $html);
    }

    public function test_incluye_una_tabla_equivalente_para_lectores_de_pantalla(): void
    {
        $html = (string) $this->blade(
            '<x-grafica-barras titulo="Promedio" :categorias="$categorias" :series="$series" eje-x="Examen" eje-y="Porcentaje (%)" fuente="F" unidad="%" />',
            ['categorias' => ['Examen A', 'Examen B'], 'series' => $this->series()],
        );

        $this->assertStringContainsString('<table class="sr-only">', $html);
        $this->assertStringContainsString('Examen A', $html);
        $this->assertStringContainsString('>25 %<', $html);
    }

    public function test_sin_datos_muestra_el_estado_vacio_en_espanol_y_no_dibuja(): void
    {
        $html = (string) $this->blade(
            '<x-grafica-barras titulo="Promedio" :categorias="[]" :series="[]" eje-x="Examen" eje-y="Y" fuente="F" />',
        );

        $this->assertStringContainsString('Aún no hay resultados para mostrar.', $html);
        $this->assertStringNotContainsString('<svg', $html);
        $this->assertStringContainsString('Fuente: F.', $html);
    }

    public function test_las_etiquetas_largas_se_abrevian_pero_conservan_el_nombre_completo(): void
    {
        $dibujo = $this->grafica(categorias: ['Un nombre de examen demasiado largo', 'B'])->geometria();

        $this->assertStringEndsWith('…', $dibujo['grupos'][0]['etiqueta']);
        $this->assertSame('Un nombre de examen demasiado largo', $dibujo['grupos'][0]['completa']);
    }
}
