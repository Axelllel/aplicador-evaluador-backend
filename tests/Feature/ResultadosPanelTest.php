<?php

/**
 * Archivo: ResultadosPanelTest.php
 * Módulo: tests/Feature
 * Descripción: Verifica las páginas de Resultados con la API sustituida: selector de examen, tabla de intentos con
 *              nota o «Pendiente de calificar», detalle de un intento con la respuesta modelo, incidencias,
 *              calificación manual con y sin motivo, autocalificación y los casos 401, 402, 403, 404 y 5xx
 *              (Manual COD-WEB, secciones 6.16, 6.17, 6.18 y 8.3).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\SesionPanelService;
use DOMDocument;
use DOMXPath;
use Tests\Support\ConPanelFalso;
use Tests\TestCase;

class ResultadosPanelTest extends TestCase
{
    use ConPanelFalso;

    private const AVISO_DE_SESION = 'Tu sesión venció. Inicia sesión de nuevo para continuar.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPanel();
        $this->conSesionDelPanel();
    }

    /**
     * Prepara cuatro exámenes (publicados: 4 con escala 0-100 y 2 con escala 0-10) y los intentos del examen 2.
     */
    private function prepararExamenes(): void
    {
        $examenes = $this->examenesDeEjemplo(4);
        $examenes[0]['escala'] = '0-100';

        $this->prepararLecturas(['examenes' => $examenes]);
        $this->prepararResultados($examenes[2], [
            $this->resultado(21, 'Ana Pérez', 'finalizado', 2, 9.0),
            $this->resultado(22, 'Beto Ríos', 'finalizado', 1, null),
            $this->resultado(23, 'Carla Soto', 'en_progreso', 0, null),
        ], [
            $this->intento(21, 'Ana Pérez', 'finalizado', '2026-11-06 10:30:00+00'),
            $this->intento(22, 'Beto Ríos', 'finalizado', '2026-11-06 10:45:00+00'),
            $this->intento(23, 'Carla Soto', 'en_progreso', null),
        ]);
    }

    /**
     * Prepara la página del intento 22 (Beto Ríos): una respuesta sin calificar y otra ya calificada a mano.
     */
    private function prepararIntento(): void
    {
        $this->prepararExamenes();
        $this->api
            ->responder('GET', '/teacher/attempts/22/answers', ['data' => [
                'intento' => ['id' => 22, 'examen_id' => 2, 'alumno_id' => 2, 'estado' => 'finalizado', 'escala' => '0-10'],
                'respuestas' => [
                    [
                        'id' => 201, 'examen_pregunta_id' => 11, 'tipo' => 'opcion_multiple', 'enunciado' => 'Capital de Francia?',
                        'respuesta_texto' => 'Roma', 'calificacion_final' => null, 'revisada_manual' => false,
                        'ponderacion' => '50.00', 'respuesta_modelo_snapshot' => 'París',
                    ],
                    [
                        'id' => 202, 'examen_pregunta_id' => 12, 'tipo' => 'abierta', 'enunciado' => 'Explica la fotosíntesis.',
                        'respuesta_texto' => 'Las plantas fabrican su comida', 'calificacion_final' => '70.00', 'revisada_manual' => true,
                        'ponderacion' => '50.00', 'respuesta_modelo_snapshot' => 'Proceso por el cual las plantas convierten luz en energía.',
                    ],
                ],
            ]])
            ->responder('GET', '/teacher/attempts/22/incidents', ['data' => [
                ['id' => 1, 'tipo_alerta' => 'cambio_pestana', 'nivel_severidad' => 'media', 'evidencia_url' => null, 'fecha_hora' => '2026-11-06 09:40:00+00'],
                ['id' => 2, 'tipo_alerta' => 'salida_pantalla', 'nivel_severidad' => 'alta', 'evidencia_url' => null, 'fecha_hora' => '2026-11-06 09:41:00+00'],
            ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resultado(int $intento, string $alumno, string $estado, int $calificadas, ?float $nota): array
    {
        return [
            'intento_id' => $intento, 'alumno_id' => $intento - 20, 'nombre' => $alumno, 'estado' => $estado,
            'calificadas' => $calificadas, 'total_preguntas' => 2, 'calificacion' => $nota,
            'escala' => '0-10', 'completo' => $nota !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function intento(int $intento, string $alumno, string $estado, ?string $entrega): array
    {
        return [
            'id' => $intento, 'alumno_id' => $intento - 20, 'nombre' => $alumno, 'numero_intento' => 1,
            'estado' => $estado, 'fecha_inicio' => '2026-11-06 09:00:00+00', 'fecha_entrega' => $entrega,
        ];
    }

    private function urlDelIntento(int $examen = 2, int $intento = 22): string
    {
        return route('panel.resultados.intento', ['examen' => $examen, 'intento' => $intento]);
    }

    private function urlDeLaRespuesta(int $respuesta, int $examen = 2, int $intento = 22): string
    {
        return route('panel.resultados.calificar', ['examen' => $examen, 'intento' => $intento, 'respuesta' => $respuesta]);
    }

    // Tabla de intentos ---------------------------------------------------------------------------------------

    public function test_la_tabla_muestra_alumno_estado_entrega_y_nota_o_pendiente_de_calificar(): void
    {
        $this->prepararExamenes();

        $respuesta = $this->get('/resultados?examen=2')
            ->assertOk()
            ->assertSee('Intentos de Examen 2')
            ->assertSee('Ana Pérez')
            ->assertSee('Beto Ríos')
            ->assertSee('Carla Soto')
            ->assertSee('Finalizado')
            ->assertSee('En progreso')
            ->assertSee('06/11/2026 10:30')
            ->assertSee('9 de 10')
            ->assertSee('Pendiente de calificar')
            ->assertSee('Sin entregar')
            ->assertSee('Intentos: 3')
            ->assertSee('Calificados: 1')
            ->assertSee('Pendientes de calificar: 1')
            ->assertSee($this->urlDelIntento(2, 21), false)
            ->assertSee($this->urlDelIntento(2, 22), false)
            ->assertSee($this->urlDelIntento(2, 23), false);

        $this->assertSame(3, count($this->consultar($respuesta->getContent(), '//tbody/tr')));
        $this->assertSame(['Resultados'], $this->consultar($respuesta->getContent(), '//aside//a[@aria-current="page"]/span[1]'));
    }

    public function test_sin_examen_elegido_usa_el_publicado_mas_reciente_y_el_selector_solo_lista_publicados(): void
    {
        $this->prepararExamenes();

        $respuesta = $this->get('/resultados')->assertOk()->assertSee('Intentos de Examen 4')->assertSee('Todavía ningún alumno ha presentado este examen.');

        $opciones = $this->consultar($respuesta->getContent(), '//select[@name="examen"]/option');

        $this->assertSame(['Examen 4 · Materia 1', 'Examen 2 · Materia 1'], $opciones);
        $this->assertSame(['Examen 4 · Materia 1'], $this->consultar($respuesta->getContent(), '//select[@name="examen"]/option[@selected]'));
    }

    public function test_la_nota_de_un_examen_de_escala_0_100_se_muestra_sobre_100(): void
    {
        $this->prepararExamenes();
        $examen = $this->examenesDeEjemplo(4)[0];
        $examen['escala'] = '0-100';
        $fila = $this->resultado(41, 'Dani Luna', 'finalizado', 2, 87.5);
        $fila['escala'] = '0-100';
        $this->prepararResultados($examen, [$fila], [$this->intento(41, 'Dani Luna', 'finalizado', '2026-11-06 11:00:00+00')]);

        $this->get('/resultados?examen=4')->assertOk()->assertSee('87.5 de 100');
    }

    public function test_la_tabla_de_intentos_pagina_de_a_15_filas(): void
    {
        $this->prepararExamenes();
        $resultados = [];
        $intentos = [];

        foreach (range(1, 16) as $n) {
            $resultados[] = $this->resultado(100 + $n, 'Alumno '.$n, 'finalizado', 2, 8.0);
            $intentos[] = $this->intento(100 + $n, 'Alumno '.$n, 'finalizado', '2026-11-06 10:00:00+00');
        }

        $this->prepararResultados($this->examenesDeEjemplo(4)[2], $resultados, $intentos);

        $primera = $this->get('/resultados?examen=2')->assertOk()->assertSee('Mostrando 1 a 15 de 16');
        $this->assertSame(15, count($this->consultar($primera->getContent(), '//tbody/tr')));

        $segunda = $this->get('/resultados?examen=2&page=2')->assertOk()->assertSee('Mostrando 16 a 16 de 16');
        $this->assertSame(1, count($this->consultar($segunda->getContent(), '//tbody/tr')));
    }

    public function test_sin_examenes_publicados_muestra_el_estado_vacio(): void
    {
        $this->prepararLecturas(['examenes' => [$this->examenesDeEjemplo(1)[0]]]);

        $this->get('/resultados')
            ->assertOk()
            ->assertSee('Aún no hay resultados para mostrar.')
            ->assertSee('Ir a Exámenes')
            ->assertDontSee('<select', false);

        $this->assertSame([], $this->api->llamadasA('GET', '/teacher/exams/1/results'));
    }

    public function test_un_examen_borrador_o_ajeno_devuelve_404(): void
    {
        $this->prepararExamenes();

        $this->get('/resultados?examen=1')->assertNotFound();
        $this->get('/resultados?examen=999')->assertNotFound();
    }

    public function test_una_falla_de_la_api_muestra_la_pagina_amable_sin_detalles_tecnicos(): void
    {
        $this->prepararExamenes();
        $this->api->responder('GET', '/teacher/exams/2/results', ['message' => 'SQLSTATE[HY000]: error interno'], 500);

        $this->get('/resultados?examen=2')
            ->assertStatus(503)
            ->assertSee('No pudimos cargar la página')
            ->assertDontSee('SQLSTATE');
    }

    public function test_un_403_al_leer_los_resultados_muestra_la_pagina_amable(): void
    {
        $this->prepararExamenes();
        $this->api->responder('GET', '/teacher/exams/2/results', ['message' => 'Docente no registrado.'], 403);

        $this->get('/resultados?examen=2')->assertStatus(503)->assertDontSee('Docente no registrado');
    }

    public function test_un_401_al_leer_los_resultados_vuelve_al_login(): void
    {
        $this->api->responder('GET', '/teacher/groups', ['message' => 'Token inválido o expirado.'], 401);

        $this->get('/resultados')->assertRedirect(route('login'))->assertSessionHas('aviso', self::AVISO_DE_SESION);
    }

    // Detalle del intento -------------------------------------------------------------------------------------

    public function test_el_detalle_muestra_respuestas_la_respuesta_modelo_la_calificacion_y_las_incidencias(): void
    {
        $this->prepararIntento();

        $respuesta = $this->get($this->urlDelIntento())
            ->assertOk()
            ->assertSee('Intento de Beto Ríos')
            ->assertSee('Examen Examen 2 · Intento 1')
            ->assertSee('Pendiente de calificar')
            ->assertSee('De 0 a 10')
            ->assertSee('1 de 2 preguntas calificadas.')
            ->assertSee('Capital de Francia?')
            ->assertSee('Roma')
            ->assertSee('Respuesta modelo (solo visible para ti)')
            ->assertSee('París')
            ->assertSee('Sin calificar')
            ->assertSee('Calificada a mano')
            ->assertSee('Calificación actual: 70 de 100')
            ->assertSee('Ponderación: 50 %')
            ->assertSee('Aporta 3.5 de 10 a la nota final')
            ->assertSee('Autocalificar opción múltiple')
            ->assertSee('Cambio de pestaña')
            ->assertSee('Salida de pantalla')
            ->assertSee('Media')
            ->assertSee('Alta')
            ->assertSee('06/11/2026 09:40')
            ->assertSee($this->urlDeLaRespuesta(201), false)
            ->assertSee($this->urlDeLaRespuesta(202), false)
            ->assertSee('name="_method" value="PATCH"', false);

        $html = $respuesta->getContent();

        $this->assertSame(['Resultados'], $this->consultar($html, '//aside//a[@aria-current="page"]/span[1]'));
        // Una respuesta sin nota (motivo opcional) y otra con nota (motivo obligatorio).
        $this->assertSame(['Motivo (opcional)', 'Motivo del cambio'], $this->consultar($html, '//label[starts-with(@for, "motivo-")]'));
        $this->assertSame(['70'], $this->consultar($html, '//input[@id="calificacion-202"]/@value'));
        // Hay pendientes de opción múltiple: el botón de autocalificar está activo.
        $this->assertCount(1, $this->consultar($html, '//form[contains(@action, "autocalificar")]//button[not(@disabled)]'));
    }

    public function test_el_boton_de_autocalificar_se_desactiva_si_no_hay_opcion_multiple_pendiente(): void
    {
        $this->prepararIntento();
        $this->api->responder('GET', '/teacher/attempts/22/answers', ['data' => [
            'intento' => ['id' => 22, 'examen_id' => 2, 'alumno_id' => 2, 'estado' => 'finalizado', 'escala' => '0-10'],
            'respuestas' => [[
                'id' => 201, 'examen_pregunta_id' => 11, 'tipo' => 'opcion_multiple', 'enunciado' => 'Capital de Francia?',
                'respuesta_texto' => 'París', 'calificacion_final' => '100.00', 'revisada_manual' => false,
                'ponderacion' => '50.00', 'respuesta_modelo_snapshot' => 'París',
            ]],
        ]]);

        $respuesta = $this->get($this->urlDelIntento())->assertOk()->assertSee('No hay respuestas de opción múltiple sin calificar.')->assertSee('Calificada automáticamente');

        $this->assertCount(1, $this->consultar($respuesta->getContent(), '//form[contains(@action, "autocalificar")]//button[@disabled]'));
    }

    public function test_un_intento_en_progreso_no_se_puede_calificar(): void
    {
        $this->prepararExamenes();
        $this->api
            ->responder('GET', '/teacher/attempts/23/answers', ['data' => [
                'intento' => ['id' => 23, 'examen_id' => 2, 'alumno_id' => 3, 'estado' => 'en_progreso', 'escala' => '0-10'],
                'respuestas' => [],
            ]])
            ->responder('GET', '/teacher/attempts/23/incidents', ['data' => []]);

        $this->get($this->urlDelIntento(2, 23))
            ->assertOk()
            ->assertSee('Podrás calificar este intento cuando el alumno lo entregue.')
            ->assertSee('El alumno no registró respuestas en este intento.')
            ->assertSee('El alumno no respondió 2 preguntas.')
            ->assertSee('Este intento no tiene incidencias.')
            ->assertDontSee('Autocalificar opción múltiple')
            ->assertDontSee('name="_method"', false);
    }

    public function test_un_intento_calificado_muestra_la_nota_en_la_escala_del_examen(): void
    {
        $this->prepararExamenes();
        $this->api
            ->responder('GET', '/teacher/attempts/21/answers', ['data' => [
                'intento' => ['id' => 21, 'examen_id' => 2, 'alumno_id' => 1, 'estado' => 'finalizado', 'escala' => '0-10'],
                'respuestas' => [],
            ]])
            ->responder('GET', '/teacher/attempts/21/incidents', ['data' => []]);

        $this->get($this->urlDelIntento(2, 21))->assertOk()->assertSee('9 de 10')->assertDontSee('Pendiente de calificar');
    }

    public function test_las_incidencias_se_paginan_de_a_15_filas(): void
    {
        $this->prepararIntento();
        $this->api->responder('GET', '/teacher/attempts/22/incidents', ['data' => array_map(
            fn (int $n): array => ['id' => $n, 'tipo_alerta' => 'conexion', 'nivel_severidad' => 'baja', 'evidencia_url' => null, 'fecha_hora' => '2026-11-06 09:40:00+00'],
            range(1, 17),
        )]);

        $primera = $this->get($this->urlDelIntento())->assertOk()->assertSee('Mostrando 1 a 15 de 17')->assertSee('Problema de conexión');
        $this->assertSame(15, count($this->consultar($primera->getContent(), '//table[caption[contains(., "Incidencias")]]/tbody/tr')));

        $this->get($this->urlDelIntento().'?pagina_incidencias=2')->assertOk()->assertSee('Mostrando 16 a 17 de 17');
    }

    public function test_un_intento_que_no_existe_o_es_de_otro_examen_devuelve_404(): void
    {
        $this->prepararIntento();
        $this->api->responder('GET', '/teacher/attempts/99/answers', ['message' => 'Intento no disponible.'], 404);

        $this->get($this->urlDelIntento(2, 99))->assertNotFound();
        // El intento 22 es del examen 2; abrirlo desde el examen 4 no está permitido.
        $this->get($this->urlDelIntento(4, 22))->assertNotFound();
    }

    // Calificación manual -------------------------------------------------------------------------------------

    public function test_califica_una_respuesta_sin_nota_previa_sin_pedir_motivo(): void
    {
        $this->prepararIntento();
        $this->api->responder('PATCH', '/teacher/answers/201/grade', ['data' => ['respuesta_id' => 201, 'calificacion' => '85.5']]);

        $this->from($this->urlDelIntento())
            ->patch($this->urlDeLaRespuesta(201), ['calificacion' => '85.5', 'motivo' => ''])
            ->assertRedirect($this->urlDelIntento().'#respuesta-201')
            ->assertSessionHas('exito', 'Calificación guardada correctamente.')
            ->assertSessionHasNoErrors();

        $llamadas = $this->api->llamadasA('PATCH', '/teacher/answers/201/grade');

        $this->assertCount(1, $llamadas);
        $this->assertSame(['calificacion' => 85.5], $llamadas[0]['datos']);
        $this->assertSame('token-de-prueba', $llamadas[0]['token']);
    }

    public function test_el_banner_de_exito_se_ve_en_la_pagina_del_intento_despues_de_calificar(): void
    {
        $this->prepararIntento();
        $this->api->responder('PATCH', '/teacher/answers/201/grade', ['data' => ['respuesta_id' => 201, 'calificacion' => '85.5']]);

        $this->followingRedirects()
            ->from($this->urlDelIntento())
            ->patch($this->urlDeLaRespuesta(201), ['calificacion' => '85.5'])
            ->assertOk()
            ->assertSee('Calificación guardada correctamente.')
            ->assertSee('Éxito');
    }

    public function test_si_la_respuesta_ya_tenia_nota_exige_el_motivo_y_no_llama_a_la_api(): void
    {
        $this->prepararIntento();

        $this->from($this->urlDelIntento())
            ->patch($this->urlDeLaRespuesta(202), ['calificacion' => '90', 'motivo' => '   '])
            ->assertRedirect($this->urlDelIntento())
            ->assertSessionHasErrors(['motivo'], null, 'respuesta_202');

        $this->assertSame([], $this->api->llamadasA('PATCH', '/teacher/answers/202/grade'));
    }

    public function test_el_error_del_motivo_aparece_junto_al_formulario_de_su_respuesta_y_conserva_lo_escrito(): void
    {
        $this->prepararIntento();

        $respuesta = $this->followingRedirects()
            ->from($this->urlDelIntento())
            ->patch($this->urlDeLaRespuesta(202), ['calificacion' => '90'])
            ->assertOk()
            ->assertSee('Escribe el motivo del cambio, porque la pregunta ya tiene calificación.');

        $html = $respuesta->getContent();

        $this->assertSame(['90'], $this->consultar($html, '//input[@id="calificacion-202"]/@value'));
        // La otra respuesta no hereda ni el valor ni el error.
        $this->assertSame([''], $this->consultar($html, '//input[@id="calificacion-201"]/@value'));
        $this->assertSame(1, substr_count($html, 'Escribe el motivo del cambio, porque'));
    }

    public function test_si_la_respuesta_ya_tenia_nota_y_hay_motivo_lo_envia_a_la_api(): void
    {
        $this->prepararIntento();
        $this->api->responder('PATCH', '/teacher/answers/202/grade', ['data' => ['respuesta_id' => 202, 'calificacion' => '90']]);

        $this->from($this->urlDelIntento())
            ->patch($this->urlDeLaRespuesta(202), ['calificacion' => '90', 'motivo' => '  Revisión con el alumno  '])
            ->assertRedirect($this->urlDelIntento().'#respuesta-202')
            ->assertSessionHas('exito');

        $this->assertSame(
            ['calificacion' => 90.0, 'motivo' => 'Revisión con el alumno'],
            $this->api->llamadasA('PATCH', '/teacher/answers/202/grade')[0]['datos'],
        );
    }

    public function test_valida_que_la_calificacion_este_entre_0_y_100(): void
    {
        $this->prepararIntento();

        foreach (['150', '-1', 'abc', '', '85.555'] as $valor) {
            $this->from($this->urlDelIntento())
                ->patch($this->urlDeLaRespuesta(201), ['calificacion' => $valor])
                ->assertSessionHasErrors(['calificacion'], null, 'respuesta_201');
        }

        $this->assertSame([], $this->api->llamadasA('PATCH', '/teacher/answers/201/grade'));
    }

    public function test_acepta_los_limites_0_y_100(): void
    {
        $this->prepararIntento();
        $this->api
            ->responder('PATCH', '/teacher/answers/201/grade', ['data' => []]);

        foreach (['0', '100'] as $valor) {
            $this->from($this->urlDelIntento())
                ->patch($this->urlDeLaRespuesta(201), ['calificacion' => $valor])
                ->assertSessionHasNoErrors();
        }

        $this->assertCount(2, $this->api->llamadasA('PATCH', '/teacher/answers/201/grade'));
    }

    public function test_una_respuesta_que_no_es_del_intento_devuelve_404_sin_llamar_a_la_api_de_calificacion(): void
    {
        $this->prepararIntento();

        $this->from($this->urlDelIntento())
            ->patch($this->urlDeLaRespuesta(999), ['calificacion' => '50'])
            ->assertNotFound();

        $this->assertSame([], $this->api->llamadasA('PATCH', '/teacher/answers/999/grade'));
    }

    public function test_el_rechazo_422_de_la_api_muestra_un_error_claro_sin_detalles_tecnicos(): void
    {
        $this->prepararIntento();
        $this->api->responder('PATCH', '/teacher/answers/201/grade', ['message' => 'Respuesta no disponible o correccion invalida.'], 422);

        $this->from($this->urlDelIntento())
            ->patch($this->urlDeLaRespuesta(201), ['calificacion' => '50'])
            ->assertRedirect($this->urlDelIntento())
            ->assertSessionHas('error', fn (string $texto): bool => str_starts_with($texto, 'No se pudo guardar la calificación.'));
    }

    public function test_el_402_y_el_403_al_calificar_muestran_una_advertencia(): void
    {
        $this->prepararIntento();

        $this->api->responder('PATCH', '/teacher/answers/201/grade', ['message' => 'Límite alcanzado.'], 402);
        $this->from($this->urlDelIntento())
            ->patch($this->urlDeLaRespuesta(201), ['calificacion' => '50'])
            ->assertSessionHas('aviso', 'Alcanzaste el límite de tu plan. Actualiza tu suscripción para continuar.');

        $this->api->responder('PATCH', '/teacher/answers/201/grade', ['message' => 'Docente no registrado.'], 403);
        $this->from($this->urlDelIntento())
            ->patch($this->urlDeLaRespuesta(201), ['calificacion' => '50'])
            ->assertSessionHas('aviso', 'Tu cuenta no tiene un plan activo o tu pago ya venció. Revisa tu suscripción para continuar.');
    }

    public function test_un_fallo_del_servicio_al_calificar_no_muestra_detalles_tecnicos(): void
    {
        $this->prepararIntento();
        $this->api->responder('PATCH', '/teacher/answers/201/grade', ['message' => 'SQLSTATE[HY000]: error'], 500);

        $this->from($this->urlDelIntento())
            ->patch($this->urlDeLaRespuesta(201), ['calificacion' => '50'])
            ->assertSessionHas('error', 'No pudimos completar la acción. Inténtalo de nuevo en unos minutos.');
    }

    public function test_un_401_al_calificar_cierra_la_sesion_y_vuelve_al_login(): void
    {
        $this->prepararIntento();
        $this->api->responder('PATCH', '/teacher/answers/201/grade', ['message' => 'Token inválido o expirado.'], 401);

        $this->from($this->urlDelIntento())
            ->patch($this->urlDeLaRespuesta(201), ['calificacion' => '50'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('aviso', self::AVISO_DE_SESION);

        $this->assertFalse(app(SesionPanelService::class)->activa());
    }

    // Autocalificación ----------------------------------------------------------------------------------------

    public function test_autocalifica_la_opcion_multiple_y_avisa_cuantas_respuestas_calificó(): void
    {
        $this->prepararIntento();

        foreach ([3 => 'Se calificaron 3 respuestas de opción múltiple correctamente.', 1 => 'Se calificó 1 respuesta de opción múltiple correctamente.', 0 => 'No había respuestas de opción múltiple por calificar.'] as $cantidad => $mensaje) {
            $this->api->responder('POST', '/teacher/attempts/22/auto-grade', ['data' => ['preguntas_calificadas' => $cantidad, 'metodo' => 'coincidencia_exacta_opcion_multiple']]);

            $this->post(route('panel.resultados.autocalificar', ['examen' => 2, 'intento' => 22]))
                ->assertRedirect($this->urlDelIntento())
                ->assertSessionHas('exito', $mensaje);
        }

        $this->assertCount(3, $this->api->llamadasA('POST', '/teacher/attempts/22/auto-grade'));
    }

    public function test_los_errores_de_la_autocalificacion_se_explican_sin_detalles_tecnicos(): void
    {
        $this->prepararIntento();
        $url = route('panel.resultados.autocalificar', ['examen' => 2, 'intento' => 22]);

        $this->api->responder('POST', '/teacher/attempts/22/auto-grade', ['message' => 'El intento no se puede calificar.'], 422);
        $this->from($this->urlDelIntento())->post($url)
            ->assertSessionHas('error', 'Este intento todavía no se puede calificar. Debe estar entregado.');

        $this->api->responder('POST', '/teacher/attempts/22/auto-grade', ['message' => 'x'], 402);
        $this->from($this->urlDelIntento())->post($url)->assertSessionHas('aviso', 'Alcanzaste el límite de tu plan. Actualiza tu suscripción para continuar.');

        $this->api->responder('POST', '/teacher/attempts/22/auto-grade', ['message' => 'x'], 403);
        $this->from($this->urlDelIntento())->post($url)->assertSessionHas('aviso', 'Tu cuenta no tiene un plan activo o tu pago ya venció. Revisa tu suscripción para continuar.');

        $this->api->responder('POST', '/teacher/attempts/22/auto-grade', ['message' => 'x'], 500);
        $this->from($this->urlDelIntento())->post($url)->assertSessionHas('error', 'No pudimos completar la acción. Inténtalo de nuevo en unos minutos.');

        $this->api->responder('POST', '/teacher/attempts/22/auto-grade', ['message' => 'Token inválido o expirado.'], 401);
        $this->from($this->urlDelIntento())->post($url)->assertRedirect(route('login'))->assertSessionHas('aviso', self::AVISO_DE_SESION);
    }

    // Protección ----------------------------------------------------------------------------------------------

    public function test_sin_sesion_el_detalle_y_los_formularios_redirigen_al_login(): void
    {
        $this->flushSession();

        $this->get($this->urlDelIntento())->assertRedirect(route('login'));
        $this->patch($this->urlDeLaRespuesta(201), ['calificacion' => '50'])->assertRedirect(route('login'));
        $this->post(route('panel.resultados.autocalificar', ['examen' => 2, 'intento' => 22]))->assertRedirect(route('login'));

        $this->assertSame([], $this->api->llamadas);
    }

    public function test_las_rutas_exigen_identificadores_numericos(): void
    {
        $this->get('/resultados/abc/intentos/22')->assertNotFound();
        $this->get('/resultados/2/intentos/xyz')->assertNotFound();
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
