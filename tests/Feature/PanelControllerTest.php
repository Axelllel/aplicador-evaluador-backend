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
        $this->assertStringContainsString('tp-nav-active', $respuesta->getContent());
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
            ->assertSee('Alumnos en el grupo más numeroso')
            ->assertSee('17 de 150')
            ->assertSee('Límite alcanzado')
            ->assertSee('Cerca del límite')
            ->assertSee('Dentro del límite')
            ->assertSee('Alcanzaste el límite de grupos de tu plan. Actualiza tu suscripción para crear más.')
            ->assertDontSee('Ver suscripción');
    }

    public function test_el_consumo_de_grupos_solo_cuenta_los_grupos_activos(): void
    {
        $grupos = $this->gruposDeEjemplo(3);
        $grupos[2]['activo'] = false;
        $this->prepararLecturas(['grupos' => $grupos]);

        $this->get('/panel')->assertOk()->assertSee('2 de 5')->assertDontSee('Alcanzaste el límite de grupos');

        $this->assertCount(0, $this->api->llamadasA('GET', '/teacher/groups/3/students'), 'No se cuentan alumnos de grupos inactivos.');
    }

    public function test_el_limite_de_alumnos_se_evalua_por_grupo_y_no_por_la_suma(): void
    {
        $planes = $this->planesDeEjemplo();
        $planes[0]['max_alumnos'] = 30;
        $this->prepararLecturas(['planes' => $planes]);
        $this->api->responder('GET', '/teacher/groups/2/students', ['data' => array_fill(0, 22, ['id' => 1])]);

        $this->get('/panel')
            ->assertOk()
            ->assertSee('22 de 30')
            ->assertDontSee('Alcanzaste el límite de alumnos en al menos un grupo.');

        $this->api->responder('GET', '/teacher/groups/2/students', ['data' => array_fill(0, 30, ['id' => 1])]);

        $this->get('/panel')
            ->assertOk()
            ->assertSee('30 de 30')
            ->assertSee('Alcanzaste el límite de alumnos en al menos un grupo.');
    }

    public function test_sin_plan_activo_el_panel_muestra_la_advertencia_y_no_las_tarjetas(): void
    {
        $this->prepararLecturas(['pagos' => []]);

        $respuesta = $this->get('/panel')
            ->assertOk()
            ->assertSee('Tu cuenta no tiene un plan activo o tu pago ya venció. Revisa tu suscripción para continuar.')
            ->assertSee('Sin plan activo')
            ->assertDontSee('Resumen del plan');

        $this->assertSame([], $this->consultar($respuesta->getContent(), '//*[@data-alerta]//a'));
        $this->assertSame(
            [route('panel.suscripcion')],
            $this->consultar($respuesta->getContent(), '//section[@aria-labelledby="titulo-sin-plan"]//a/@href'),
        );
        $this->assertSame([], $this->api->llamadasA('GET', '/teacher/groups/1/students'));
    }

    public function test_suscripcion_enlaza_al_catalogo_de_planes_disponibles(): void
    {
        $this->prepararLecturas();

        $respuesta = $this->get('/suscripcion')->assertOk()->assertSee('Tus pagos');

        $this->assertSame(
            [url('/suscripcion/planes')],
            $this->consultar($respuesta->getContent(), '//main//a[normalize-space(.)="Crea tu plan"]/@href'),
        );

        $catalogo = $this->get('/suscripcion/planes')
            ->assertOk()
            ->assertSee('Crea tu plan')
            ->assertSee('Plan básico')
            ->assertSee('Plan profesional')
            ->assertSee('Tu plan');

        $this->assertSame(
            [route('panel.suscripcion')],
            $this->consultar($catalogo->getContent(), '//main//a[normalize-space(.)="Volver a Suscripción"]/@href'),
        );
    }

    public function test_el_catalogo_muestra_el_estado_vacio_si_no_hay_planes(): void
    {
        $this->prepararLecturas(['planes' => [], 'pagos' => []]);

        $this->get('/suscripcion/planes')->assertOk()->assertSee('Aún no hay planes disponibles.');
    }

    public function test_el_docente_puede_cotizar_su_plan_aunque_no_tenga_un_plan_activo(): void
    {
        $this->prepararLecturas(['pagos' => []]);

        $inicial = $this->get('/suscripcion/planes')->assertOk()->assertSee('Configura tu plan')->assertSee('$115.00');

        $this->assertSame(array_map('strval', range(1, 10)), $this->consultar($inicial->getContent(), '//select[@name="grupos"]/option/@value'));
        $this->assertSame(['15', '20', '25', '30'], $this->consultar($inicial->getContent(), '//select[@name="alumnos"]/option/@value'));
        $this->assertSame(['100', '150', '200'], $this->consultar($inicial->getContent(), '//select[@name="preguntas"]/option/@value'));
        $this->assertSame(
            [url('/suscripcion/planes/pago')],
            $this->consultar($inicial->getContent(), '//form[@data-configurador-plan]//button[normalize-space(.)="Continuar al pago"]/@formaction'),
        );

        $this->get('/suscripcion/planes?grupos=3&alumnos=25&preguntas=150')->assertOk()->assertSee('$181.00');
        $this->get('/suscripcion/planes?grupos=10&alumnos=30&preguntas=200')->assertOk()->assertSee('$405.00');
    }

    public function test_la_cotizacion_rechaza_capacidades_fuera_de_las_opciones(): void
    {
        $this->getJson('/suscripcion/planes?grupos=11&alumnos=16&preguntas=250')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['grupos', 'alumnos', 'preguntas']);
    }

    public function test_el_resumen_de_pago_recalcula_el_precio_y_permite_editar_la_seleccion(): void
    {
        $seleccion = ['grupos' => 3, 'alumnos' => 25, 'preguntas' => 150];
        $pago = $this->get('/suscripcion/planes/pago?'.http_build_query($seleccion + ['precio' => 1]))
            ->assertOk()
            ->assertSee('Resumen de pago')
            ->assertSee('Pago en línea pendiente')
            ->assertSeeInOrder(['Grupos activos', '3', 'Alumnos por grupo', '25', 'Preguntas en el banco', '150'])
            ->assertSee('$181.00')
            ->assertSee('Tarjeta de crédito o débito')
            ->assertSee('Transferencia SPEI');

        $this->assertSame(
            [route('panel.suscripcion.planes', $seleccion)],
            $this->consultar($pago->getContent(), '//main//a[normalize-space(.)="Editar mi plan"]/@href'),
        );
        $this->assertSame(
            [route('panel.suscripcion.planes.pago.revisar')],
            $this->consultar($pago->getContent(), '//main//form/@action'),
        );
        $this->assertSame(
            ['titular', 'correo_facturacion'],
            $this->consultar($pago->getContent(), '//main//form//input[@required]/@name'),
        );
        $this->assertSame([], $this->api->llamadas);
    }

    public function test_el_resumen_de_pago_requiere_una_seleccion_completa_y_valida(): void
    {
        $this->getJson('/suscripcion/planes/pago?grupos=11&preguntas=150')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['grupos', 'alumnos']);
    }

    public function test_el_resumen_no_acepta_solicitudes_de_cobro(): void
    {
        $this->postJson('/suscripcion/planes/pago', ['metodo' => 'tarjeta', 'grupos' => 3, 'alumnos' => 25, 'preguntas' => 150])
            ->assertStatus(405);

        $this->assertSame([], $this->api->llamadas);
    }

    public function test_la_revision_de_pago_requiere_titular_y_correo_validos(): void
    {
        $seleccion = ['grupos' => 3, 'alumnos' => 25, 'preguntas' => 150];

        $this->postJson('/suscripcion/planes/pago/datos', $seleccion)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['titular', 'correo_facturacion']);

        $this->postJson('/suscripcion/planes/pago/datos', $seleccion + ['titular' => 'Laura Méndez', 'correo_facturacion' => 'correo-invalido'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('correo_facturacion');
    }

    public function test_revisar_los_datos_no_registra_un_pago(): void
    {
        $this->post('/suscripcion/planes/pago/datos', [
            'grupos' => 3, 'alumnos' => 25, 'preguntas' => 150,
            'titular' => 'Laura Méndez', 'correo_facturacion' => 'laura@example.com',
        ])
            ->assertOk()
            ->assertSee('Datos de facturación completos.')
            ->assertSee('Pago en línea pendiente')
            ->assertSee('$181.00');

        $this->assertSame([], $this->api->llamadas);
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
            ->assertSee('data-modal-abrir="confirmar-eliminar-examen-1"', false)
            ->assertSee('¿Eliminar «Examen 1»?')
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertDontSee('data-modal-abrir="confirmar-eliminar-examen-2"', false);
    }

    public function test_eliminar_el_borrador_envia_su_id_a_la_api(): void
    {
        $this->api->responder('DELETE', '/teacher/exams/1');

        $this->delete('/examenes/1')
            ->assertRedirect(route('panel.examenes'))
            ->assertSessionHas('exito', 'Examen eliminado correctamente.');

        $this->assertCount(1, $this->api->llamadasA('DELETE', '/teacher/exams/1'));
    }

    public function test_el_formulario_de_ponderacion_solo_aparece_en_borradores(): void
    {
        $this->prepararLecturas();
        $apartado = [
            'id' => 12, 'tipo' => 'opcion_multiple', 'cantidad_preguntas' => 1,
            'preguntas' => [[
                'examen_pregunta_id' => 32, 'pregunta_id' => 1, 'enunciado' => 'Pregunta asignada',
                'tiempo_segundos' => 30, 'ponderacion' => '70.00',
            ]],
        ];
        $this->api->responder('GET', '/teacher/exams/1/sections', ['data' => [$apartado]])
            ->responder('GET', '/teacher/exams/2/sections', ['data' => [$apartado]]);

        $this->get('/examenes/1/apartados')
            ->assertOk()
            ->assertSee('Guardar ponderación')
            ->assertSee(route('panel.examenes.preguntas.ponderacion', ['examen' => 1, 'examenPregunta' => 32]), false);

        $this->get('/examenes/2/apartados')
            ->assertOk()
            ->assertSee('70 %')
            ->assertDontSee('Guardar ponderación');
    }

    public function test_guardar_ponderacion_valida_el_porcentaje_y_actualiza_la_pregunta_asignada(): void
    {
        $ruta = '/examenes/1/preguntas/32/ponderacion';
        $rutaApi = '/teacher/exams/1/questions/32/weight';

        $this->from('/examenes/1/apartados')->patch($ruta, ['nueva_ponderacion' => '70.001'])
            ->assertRedirect('/examenes/1/apartados')
            ->assertSessionHasErrors('nueva_ponderacion');
        $this->assertSame([], $this->api->llamadasA('PATCH', $rutaApi));

        $this->api->responder('PATCH', $rutaApi);

        $this->patch($ruta, ['nueva_ponderacion' => '70.25'])
            ->assertRedirect('/examenes/1/apartados')
            ->assertSessionHas('exito', 'Ponderación actualizada correctamente.');

        $this->assertSame(['ponderacion' => '70.25'], $this->api->llamadasA('PATCH', $rutaApi)[0]['datos']);
    }

    public function test_sin_examenes_el_panel_invita_a_crear_el_primero(): void
    {
        $this->prepararLecturas(['examenes' => []]);

        $this->get('/panel')->assertOk()->assertSee('Aún no tienes exámenes.');
    }

    public function test_la_barra_superior_tiene_el_logotipo_compartido_y_el_menu_de_cuenta(): void
    {
        $this->prepararLecturas();

        $this->get('/panel')
            ->assertOk()
            ->assertSee('images/bigbitbyte-logo.png', false)
            ->assertSee('width="35"', false)
            ->assertSee('height="35"', false)
            ->assertSee('tp-header', false)
            ->assertSee('tp-sidebar', false)
            ->assertSee('data-menu-alternar', false)
            ->assertSee('aria-controls="menu-lateral"', false)
            ->assertSee('id="menu-cuenta"', false)
            ->assertSee('id="tp-theme-toggle"', false);
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
