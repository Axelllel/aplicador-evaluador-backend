<?php

/**
 * Archivo: PanelControllerTest.php
 * Módulo: tests/Feature
 * Descripción: Verifica el panel del docente con datos reales simulados de la API: menú lateral, nombre de la
 *              sesión, tarjetas de consumo del plan, aviso de límite, caso sin plan y tabla de 15 filas por página
 *              (Manual COD-WEB, secciones 6.16, 6.17 y 6.18).
 * Autor: Leonardo Ballestero López
 * Creado: 27/10/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Resultados y Estadísticas ya no están en construcción
 */

declare(strict_types=1);

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Tests\Support\ConPanelFalso;
use Tests\TestCase;

class PanelControllerTest extends TestCase
{
    use ConPanelFalso;

    private const MENU_DEL_DOCENTE = [
        'Panel',
        'Grupos',
        'Banco de preguntas',
        'Exámenes',
        'Resultados',
        'Estadísticas',
        'Suscripción',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPanel();
        $this->conSesionDelPanel();
    }

    public function test_panel_responde_200_con_los_textos_clave_y_el_nombre_de_la_sesion(): void
    {
        $this->prepararLecturas();

        $this->get('/panel')
            ->assertOk()
            ->assertSee('<html lang="es">', false)
            ->assertSee('Resumen del plan')
            ->assertSee('Plan actual: Plan básico')
            ->assertSee('Cerrar sesión')
            ->assertSee('Laura Méndez Ríos')
            ->assertSee('Saltar al contenido')
            ->assertSee('<title>Panel · Sistema Aplicador / Evaluador de Exámenes</title>', false)
            ->assertDontSee('Carlos Rivera Soto');
    }

    public function test_el_nombre_de_la_barra_superior_es_el_del_docente_que_inicio_sesion(): void
    {
        $this->prepararLecturas();
        $this->conSesionDelPanel('Ana Torres Luna');

        $this->get('/panel')->assertOk()->assertSee('Ana Torres Luna')->assertDontSee('Laura Méndez Ríos');
    }

    public function test_panel_del_docente_tiene_los_siete_destinos_en_orden(): void
    {
        $this->prepararLecturas();

        $respuesta = $this->get('/panel')->assertOk();

        $this->assertSame(self::MENU_DEL_DOCENTE, $this->etiquetasDelMenu($respuesta->getContent()));
    }

    public function test_el_menu_marca_la_pagina_activa_con_texto_y_borde(): void
    {
        $this->prepararLecturas();

        $respuesta = $this->get('/panel')->assertOk()->assertSee('(página actual)');

        $this->assertSame(['Panel'], $this->consultar($respuesta->getContent(), '//aside//a[@aria-current="page"]/span[1]'));
        $this->assertStringContainsString('border-white', $respuesta->getContent());
        $this->assertStringContainsString('font-semibold', $respuesta->getContent());

        $grupos = $this->get('/grupos')->assertOk();

        $this->assertSame(['Grupos'], $this->consultar($grupos->getContent(), '//aside//a[@aria-current="page"]/span[1]'));
    }

    public function test_cada_destino_del_menu_abre_una_pagina_con_su_marcador_activo(): void
    {
        $this->prepararLecturas();

        $destinos = [
            '/grupos' => 'Grupos',
            '/banco-de-preguntas' => 'Banco de preguntas',
            '/examenes' => 'Exámenes',
            '/resultados' => 'Resultados',
            '/estadisticas' => 'Estadísticas',
            '/suscripcion' => 'Suscripción',
        ];

        foreach ($destinos as $ruta => $etiqueta) {
            $respuesta = $this->get($ruta)->assertOk();

            $this->assertSame(
                [$etiqueta],
                $this->consultar($respuesta->getContent(), '//aside//a[@aria-current="page"]/span[1]'),
            );
        }
    }

    public function test_resultados_y_estadisticas_ya_no_son_una_seccion_en_construccion(): void
    {
        $this->prepararLecturas();

        foreach (['/resultados', '/estadisticas'] as $ruta) {
            $this->get($ruta)->assertOk()->assertDontSee('Sección en construcción')->assertSee('Aún no hay resultados para mostrar.');
        }
    }

    public function test_el_panel_muestra_las_tarjetas_de_consumo_reales_contra_los_limites_del_plan(): void
    {
        $this->prepararLecturas();

        $this->get('/panel')
            ->assertOk()
            ->assertSee('5 de 5')
            ->assertSee('186 de 200')
            ->assertSee('85 de 150')
            ->assertSee('Límite alcanzado')
            ->assertSee('Cerca del límite')
            ->assertSee('Dentro del límite')
            ->assertSee('Alcanzaste el límite de grupos de tu plan. Actualiza tu suscripción para crear más.');
    }

    public function test_el_consumo_de_grupos_solo_cuenta_los_grupos_activos(): void
    {
        $grupos = $this->gruposDeEjemplo(3);
        $grupos[2]['activo'] = false;
        $this->prepararLecturas(['grupos' => $grupos]);

        $this->get('/panel')->assertOk()->assertSee('2 de 5')->assertDontSee('Alcanzaste el límite de grupos');

        $this->assertCount(0, $this->api->llamadasA('GET', '/teacher/groups/3/students'), 'No se cuentan alumnos de grupos inactivos.');
    }

    public function test_sin_plan_activo_el_panel_muestra_la_advertencia_y_no_las_tarjetas(): void
    {
        $this->prepararLecturas(['pagos' => []]);

        $this->get('/panel')
            ->assertOk()
            ->assertSee('Tu cuenta no tiene un plan activo o tu pago ya venció. Revisa tu suscripción para continuar.')
            ->assertSee('Sin plan activo')
            ->assertSee('Ver suscripción')
            ->assertDontSee('Resumen del plan');

        $this->assertSame([], $this->api->llamadasA('GET', '/teacher/groups/1/students'));
    }

    public function test_un_pago_vencido_cuenta_como_sin_plan(): void
    {
        $pago = $this->pagoDeEjemplo();
        $pago['fecha_inicio_vigencia'] = '2020-01-01 00:00:00';
        $pago['fecha_fin_vigencia'] = '2020-02-01 00:00:00';
        $this->prepararLecturas(['pagos' => [$pago]]);

        $this->get('/panel')->assertOk()->assertSee('Sin plan activo');
    }

    public function test_un_pago_pendiente_no_da_plan(): void
    {
        $this->prepararLecturas(['pagos' => [$this->pagoDeEjemplo('pendiente')]]);

        $this->get('/panel')->assertOk()->assertSee('Sin plan activo');
    }

    public function test_la_tabla_de_examenes_reales_pagina_de_a_15_filas(): void
    {
        $this->prepararLecturas(['examenes' => $this->examenesDeEjemplo(38)]);

        $primera = $this->get('/panel')->assertOk()->assertSee('Mostrando 1 a 15 de 38');
        $this->assertSame(15, count($this->consultar($primera->getContent(), '//tbody/tr')));

        $segunda = $this->get('/panel?page=2')->assertOk()->assertSee('Mostrando 16 a 30 de 38');
        $this->assertSame(15, count($this->consultar($segunda->getContent(), '//tbody/tr')));

        $tercera = $this->get('/panel?page=3')->assertOk()->assertSee('Mostrando 31 a 38 de 38');
        $this->assertSame(8, count($this->consultar($tercera->getContent(), '//tbody/tr')));
    }

    public function test_la_tabla_muestra_nombre_grupo_estado_en_texto_y_acciones_reales(): void
    {
        $grupos = $this->gruposDeEjemplo(1);
        $grupos[0]['materia'] = 'Cálculo diferencial';
        $this->prepararLecturas(['grupos' => $grupos, 'examenes' => $this->examenesDeEjemplo(2)]);

        $this->get('/panel')
            ->assertOk()
            ->assertSee('Examen 1')
            ->assertSee('Cálculo diferencial')
            ->assertSee('Borrador')
            ->assertSee('Cerrado')
            ->assertSee('01/10/2026 08:00')
            ->assertSee('title="Publicar"', false)
            ->assertSee('title="Abrir"', false)
            ->assertSee('title="Resultados"', false)
            ->assertSee('data-modal-abrir="confirmar-publicar-examen-1"', false)
            ->assertSee('¿Publicar este examen?')
            ->assertSee(route('panel.examenes.apartados', ['examen' => 1]), false)
            ->assertSee(route('panel.examenes.publicar', ['examen' => 1]), false)
            ->assertDontSee('title="Eliminar"', false);
    }

    public function test_sin_examenes_el_panel_invita_a_crear_el_primero(): void
    {
        $this->prepararLecturas(['examenes' => []]);

        $this->get('/panel')->assertOk()->assertSee('Aún no tienes exámenes.');
    }

    public function test_la_barra_superior_tiene_el_logotipo_de_40_px_y_el_menu_de_cuenta(): void
    {
        $this->prepararLecturas();

        $this->get('/panel')
            ->assertOk()
            ->assertSee('style="height: 40px"', false)
            ->assertSee('h-16', false)
            ->assertSee('w-64', false)
            ->assertSee('data-menu-alternar', false)
            ->assertSee('aria-controls="menu-lateral"', false)
            ->assertSee('id="menu-cuenta"', false);
    }

    public function test_una_falla_de_la_api_muestra_una_pagina_amable_sin_detalles_tecnicos(): void
    {
        $this->prepararLecturas();
        $this->api->responder('GET', '/teacher/exams', ['message' => 'SQLSTATE[HY000]: error interno'], 500);

        $this->get('/panel')
            ->assertStatus(503)
            ->assertSee('No pudimos cargar la página')
            ->assertSee('Ocurrió un problema al consultar tus datos.')
            ->assertSee('Reintentar')
            ->assertSee('Laura Méndez Ríos')
            ->assertDontSee('SQLSTATE')
            ->assertDontSee('Error 500');
    }

    public function test_las_paginas_no_muestran_claves_de_idioma_sin_traducir(): void
    {
        $this->prepararLecturas();

        foreach (['/panel', '/grupos', '/banco-de-preguntas', '/examenes', '/suscripcion', '/resultados', '/guia-estilo'] as $ruta) {
            $texto = strip_tags($this->get($ruta)->assertOk()->getContent());

            $this->assertDoesNotMatchRegularExpression(
                '/\b(?:panel|guia|mensajes|acciones|sesion|ejemplo|grupos|preguntas|examenes|suscripcion|registro|api)\.[a-z_]+/',
                $texto,
                "La página {$ruta} muestra una clave de idioma sin traducir.",
            );
        }
    }

    /**
     * Devuelve las etiquetas del menú lateral en el orden en que aparecen.
     *
     * @return list<string>
     */
    private function etiquetasDelMenu(string $html): array
    {
        return $this->consultar($html, '//aside[@id="menu-lateral"]//li/a/span[1]');
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
