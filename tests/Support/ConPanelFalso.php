<?php

/**
 * Archivo: ConPanelFalso.php
 * Módulo: tests/Support
 * Descripción: Ayudas para probar el panel web con la API sustituida: instala PanelApiFalso, abre una sesión
 *              de docente y prepara las respuestas de lectura más comunes.
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Resultados e intentos vacíos por examen
 */

declare(strict_types=1);

namespace Tests\Support;

use App\Services\PanelApiService;
use App\Services\SesionPanelService;
use Carbon\CarbonImmutable;

/**
 * Prepara pruebas de las páginas del panel sin base de datos ni Supabase.
 */
trait ConPanelFalso
{
    protected PanelApiFalso $api;

    protected function prepararPanel(): void
    {
        $this->withoutVite();

        $this->api = new PanelApiFalso();
        $this->app->instance(PanelApiService::class, $this->api);
    }

    /**
     * Abre una sesión de docente con un token vigente (o vencido si $segundosDeVigencia es negativo).
     */
    protected function conSesionDelPanel(string $nombre = 'Laura Méndez Ríos', int $segundosDeVigencia = 3600): static
    {
        $datos = SesionPanelService::datosParaSesion([
            'access_token' => 'token-de-prueba',
            'refresh_token' => 'refresco-de-prueba',
            'expires_in' => 3600,
            'docente' => [
                'id' => '94902bd0-fc37-4728-a6e3-6d34b804f100',
                'nombre' => $nombre,
                'correo' => 'laura@example.com',
            ],
        ]);
        $datos['expira_en'] = time() + $segundosDeVigencia;

        return $this->withSession([SesionPanelService::CLAVE => $datos]);
    }

    /**
     * Prepara las lecturas del panel de un docente con plan básico y consumo cercano al límite.
     *
     * @param  array<string, mixed>  $cambios  Datos que sustituyen a los de ejemplo: grupos, preguntas, examenes, planes, pagos.
     */
    protected function prepararLecturas(array $cambios = []): void
    {
        $grupos = $cambios['grupos'] ?? $this->gruposDeEjemplo(5);
        $examenes = $cambios['examenes'] ?? $this->examenesDeEjemplo(3);

        $this->api
            ->responder('GET', '/teacher/groups', ['data' => $grupos])
            ->responder('GET', '/teacher/questions', ['data' => $cambios['preguntas'] ?? $this->preguntasDeEjemplo(186)])
            ->responder('GET', '/teacher/exams', ['data' => $examenes])
            ->responder('GET', '/teacher/plans', ['data' => $cambios['planes'] ?? $this->planesDeEjemplo()])
            ->responder('GET', '/teacher/payments', ['data' => $cambios['pagos'] ?? [$this->pagoDeEjemplo()]]);

        foreach ($grupos as $grupo) {
            $this->api->responder('GET', '/teacher/groups/'.$grupo['id'].'/students', [
                'data' => array_map(fn (int $n): array => ['id' => $n, 'nombre' => 'Alumno '.$n, 'fecha_union' => '2026-11-01 10:00:00+00'], range(1, 17)),
            ]);
        }

        // Resultados e intentos sin alumnos: Resultados y Estadísticas muestran su estado vacío.
        foreach ($examenes as $examen) {
            $this->prepararResultados($examen, []);
        }
    }

    /**
     * Prepara los resultados y los intentos de un examen (la API los entrega por separado).
     *
     * @param  array<string, mixed>  $examen  Fila de la lista de exámenes (id, nombre y escala).
     * @param  list<array<string, mixed>>  $resultados  Filas de GET /teacher/exams/{id}/results.
     * @param  list<array<string, mixed>>  $intentos  Filas de GET /teacher/exams/{id}/attempts.
     */
    protected function prepararResultados(array $examen, array $resultados, array $intentos = []): void
    {
        $this->api
            ->responder('GET', '/teacher/exams/'.$examen['id'].'/results', [
                'data' => [
                    'examen' => [
                        'id' => $examen['id'],
                        'nombre' => $examen['nombre'],
                        'escala' => $examen['escala'] ?? '0-10',
                        'resultado_visible' => 'oculto',
                    ],
                    'resultados' => $resultados,
                ],
            ])
            ->responder('GET', '/teacher/exams/'.$examen['id'].'/attempts', ['data' => $intentos]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function gruposDeEjemplo(int $cantidad): array
    {
        return array_map(fn (int $n): array => [
            'id' => $n,
            'materia' => 'Materia '.$n,
            'folio_codigo' => 'FOLIO'.str_pad((string) $n, 11, 'X'),
            'activo' => true,
        ], range(1, $cantidad));
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function preguntasDeEjemplo(int $cantidad): array
    {
        return array_map(fn (int $n): array => [
            'id' => $n,
            'tipo' => 'opcion_multiple',
            'enunciado' => 'Pregunta número '.$n,
            'respuesta_modelo' => 'A',
            'opciones' => ['A', 'B'],
        ], range($cantidad, 1));
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function examenesDeEjemplo(int $cantidad): array
    {
        return array_map(fn (int $n): array => [
            'id' => $n,
            'grupo_id' => 1,
            'nombre' => 'Examen '.$n,
            'fecha_apertura' => '2026-10-01 08:00:00+00',
            'fecha_cierre' => '2026-10-01 10:00:00+00',
            'orden' => 'fijo',
            'escala' => '0-10',
            'plataforma_permitida' => 'ambas',
            'publicado' => $n % 2 === 0,
            'resultado_visible' => 'oculto',
        ], range($cantidad, 1));
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function planesDeEjemplo(): array
    {
        return [
            ['id' => 2, 'nombre' => 'Plan básico', 'max_grupos' => 5, 'max_preguntas' => 200, 'max_alumnos' => 150, 'precio' => '199.00'],
            ['id' => 3, 'nombre' => 'Plan profesional', 'max_grupos' => 15, 'max_preguntas' => 600, 'max_alumnos' => 450, 'precio' => '499.00'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function pagoDeEjemplo(string $estado = 'confirmado'): array
    {
        $ahora = CarbonImmutable::now();

        return [
            'id' => 1,
            'paquete_id' => 2,
            'fecha' => $ahora->subDay()->toDateTimeString(),
            'monto' => '199.00',
            'estado' => $estado,
            'fecha_inicio_vigencia' => $ahora->subDay()->toDateTimeString(),
            'fecha_fin_vigencia' => $ahora->addDays(29)->toDateTimeString(),
            'renovacion_automatica' => false,
        ];
    }
}
