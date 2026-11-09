<?php

/**
 * Archivo: GuiaEstiloControllerTest.php
 * Módulo: tests/Feature
 * Descripción: Verifica que la guía de estilo muestre todo lo implementado: paleta, contraste, hallazgos,
 *              tipografía, componentes, mensajes, QR, acciones e íconos y logotipo
 *              (Manual COD-WEB, secciones 6.14 a 6.19).
 * Autor: Leonardo Ballestero López
 * Creado: 27/10/2026
 * Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AccionPanel;
use App\Services\PaletaColoresService;
use Tests\TestCase;

class GuiaEstiloControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guia_de_estilo_responde_200_con_el_titulo_y_todas_las_secciones(): void
    {
        $respuesta = $this->get('/guia-estilo')
            ->assertOk()
            ->assertSee('Guía de estilo');

        foreach (array_keys(__('guia.secciones')) as $idDeSeccion) {
            $respuesta->assertSee('id="'.$idDeSeccion.'"', false);
        }
    }

    public function test_cada_seccion_cita_el_manual_y_la_seccion(): void
    {
        $this->get('/guia-estilo')
            ->assertOk()
            ->assertSee('COD-WEB §6.14, Tabla 12')
            ->assertSee('COD-WEB §6.15, Tabla 13')
            ->assertSee('COD-WEB §6.16, Tabla 14')
            ->assertSee('COD-WEB §6.17, Tabla 15')
            ->assertSee('COD-WEB §6.18, Tabla 16')
            ->assertSee('COD-WEB §6.18 (Tabla 17) y §6.19');
    }

    public function test_la_paleta_muestra_el_token_el_hexadecimal_y_el_contraste_de_cada_color(): void
    {
        $respuesta = $this->get('/guia-estilo')->assertOk();

        foreach (app(PaletaColoresService::class)->colores() as $color) {
            $respuesta->assertSee($color['token'])->assertSee($color['hex']);
        }

        $respuesta->assertSee('#1F2937')->assertSee('#2E74B5')->assertSee('#B3261E');
    }

    public function test_la_guia_lista_los_hallazgos_de_contraste_con_su_seccion_del_manual(): void
    {
        $this->get('/guia-estilo')
            ->assertOk()
            ->assertSee('Hallazgos de contraste')
            ->assertSee('Borde sobre blanco')
            ->assertSee('Acento sobre superficie')
            ->assertSee('Acento claro como texto')
            ->assertSee('COD-WEB §6.14 (Tabla 12) y §6.17 (Tabla 15)')
            ->assertSee('No cumple');
    }

    public function test_la_guia_muestra_los_tres_tipos_de_boton_y_las_cajas_de_texto(): void
    {
        $this->get('/guia-estilo')
            ->assertOk()
            ->assertSee('border border-primario bg-primario text-white', false)
            ->assertSee('border border-primario bg-transparent text-primario', false)
            ->assertSee('text-acento underline', false)
            ->assertSee('border-error', false)
            ->assertSee('ring-2 ring-acento', false)
            ->assertSee('placeholder:text-texto-suave', false);
    }

    public function test_la_guia_muestra_los_cinco_tipos_de_mensaje_y_el_modal_de_confirmacion(): void
    {
        $this->get('/guia-estilo')
            ->assertOk()
            ->assertSee('Grupo creado correctamente.')
            ->assertSee('No se pudo guardar el examen. Revisa los datos e inténtalo de nuevo.')
            ->assertSee('Alcanzaste el límite de grupos de tu plan. Actualiza tu suscripción para crear más.')
            ->assertSee('Los resultados se actualizarán cuando termine el examen.')
            ->assertSee('¿Eliminar este grupo? Esta acción no se puede deshacer.')
            ->assertSee('<dialog', false)
            ->assertSee('data-modal-abrir="confirmar-eliminar-grupo"', false)
            ->assertSee('data-alerta-cerrar', false)
            ->assertSee('role="alert"', false)
            ->assertSee('role="status"', false)
            ->assertSee('data-autoocultar="5000"', false);
    }

    public function test_la_guia_muestra_el_qr_de_256_px_con_margen_blanco_y_el_folio_debajo(): void
    {
        $respuesta = $this->get('/guia-estilo')->assertOk();
        $html = $respuesta->getContent();

        $this->assertMatchesRegularExpression(
            '/<div data-qr-grupo class="bg-white p-4"><svg[^>]*width="256"[^>]*height="256"/s',
            $html,
        );

        $this->assertMatchesRegularExpression('/<figcaption>\s*<code[^>]*>ABC-1234<\/code>\s*<\/figcaption>/s', $html);
    }

    public function test_la_guia_muestra_la_tabla_completa_de_acciones_con_su_icono(): void
    {
        $respuesta = $this->get('/guia-estilo')->assertOk();

        $this->assertCount(15, AccionPanel::cases());

        foreach (AccionPanel::cases() as $accion) {
            $respuesta->assertSee($accion->texto())->assertSee($accion->icono());
        }

        $respuesta->assertSee('Descargar QR')
            ->assertSee('Copiar folio')
            ->assertSee('arrow-right-on-rectangle')
            ->assertSee('En color de error y con confirmación previa.');
    }

    public function test_la_guia_muestra_el_logotipo_en_40_y_en_96_px(): void
    {
        $this->get('/guia-estilo')
            ->assertOk()
            ->assertSee('style="height: 40px"', false)
            ->assertSee('style="height: 96px"', false);
    }

    public function test_la_guia_pagina_la_tabla_de_ejemplo_con_su_propio_parametro(): void
    {
        $this->get('/guia-estilo?pagina_tabla=2')
            ->assertOk()
            ->assertSee('Mostrando 16 a 30 de 38')
            ->assertSee('pagina_tabla=3', false);
    }
}
