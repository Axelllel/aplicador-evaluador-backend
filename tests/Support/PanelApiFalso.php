<?php

/**
 * Archivo: PanelApiFalso.php
 * Módulo: tests/Support
 * Descripción: Sustituto de PanelApiService para las pruebas: responde con datos preparados, sin sub-peticiones
 *              ni base de datos, y guarda cada llamada recibida para revisarla.
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace Tests\Support;

use App\Services\PanelApiService;
use App\Services\RespuestaApi;
use App\Services\SesionPanelService;
use Illuminate\Contracts\Http\Kernel;
use LogicException;

/**
 * Cliente de la API que responde con datos de prueba.
 */
class PanelApiFalso extends PanelApiService
{
    /** @var array<string, RespuestaApi> */
    private array $respuestas = [];

    /** @var list<array{metodo: string, ruta: string, datos: array<string, mixed>, token: string|null}> */
    public array $llamadas = [];

    public function __construct()
    {
        parent::__construct(app(), app(Kernel::class), app(SesionPanelService::class));
    }

    /**
     * Prepara la respuesta de una ruta. $cuerpo es el JSON completo; la clave «data» lleva los registros.
     *
     * @param  array<string, mixed>  $cuerpo
     */
    public function responder(string $metodo, string $ruta, array $cuerpo = [], int $estado = 200): static
    {
        $this->respuestas[$metodo.' '.$ruta] = new RespuestaApi($estado, $cuerpo);

        return $this;
    }

    /**
     * Devuelve las llamadas hechas a una ruta.
     *
     * @return list<array{metodo: string, ruta: string, datos: array<string, mixed>, token: string|null}>
     */
    public function llamadasA(string $metodo, string $ruta): array
    {
        return array_values(array_filter(
            $this->llamadas,
            fn (array $llamada): bool => $llamada['metodo'] === $metodo && $llamada['ruta'] === $ruta,
        ));
    }

    protected function ejecutar(string $metodo, string $uri, array $datos, ?string $token): RespuestaApi
    {
        $ruta = substr($uri, strlen(self::PREFIJO));

        $this->llamadas[] = ['metodo' => $metodo, 'ruta' => $ruta, 'datos' => $datos, 'token' => $token];

        return $this->respuestas[$metodo.' '.$ruta]
            ?? throw new LogicException("La prueba no preparó una respuesta para {$metodo} {$ruta}.");
    }
}
