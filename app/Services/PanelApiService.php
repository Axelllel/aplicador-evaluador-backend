<?php

/**
 * Archivo: PanelApiService.php
 * Módulo: app/Services
 * Descripción: Único punto de acceso del panel web a la API /api/v1/* de la propia aplicación. Ejecuta
 *              cada llamada EN PROCESO como sub-petición (sin HTTP a localhost), con el token de la sesión,
 *              y restaura la petición web, el usuario y la ruta actuales al terminar
 *              (Manual COD-WEB, secciones 8.1, 8.2 y 8.4).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\SesionVencidaException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Facade;
use Throwable;

/**
 * Cliente interno de la API del backend para el panel web.
 *
 * Las pruebas lo sustituyen sobrescribiendo {@see self::ejecutar()}, que es donde ocurre toda la entrada
 * y salida (ver tests/Support/PanelApiFalso.php).
 */
class PanelApiService
{
    /** Prefijo de todas las rutas de la API. */
    public const PREFIJO = '/api/v1';

    public function __construct(
        private readonly Application $app,
        private readonly Kernel $nucleo,
        private readonly SesionPanelService $sesion,
    ) {
    }

    /**
     * Consulta autenticada con el token de la sesión.
     *
     * @param  array<string, mixed>  $consulta
     *
     * @throws SesionVencidaException
     */
    public function get(string $ruta, array $consulta = []): RespuestaApi
    {
        return $this->solicitar('GET', $ruta, $consulta);
    }

    /**
     * Envío autenticado con el token de la sesión.
     *
     * @param  array<string, mixed>  $datos
     *
     * @throws SesionVencidaException
     */
    public function post(string $ruta, array $datos = []): RespuestaApi
    {
        return $this->solicitar('POST', $ruta, $datos);
    }

    /**
     * Actualización parcial autenticada con el token de la sesión.
     *
     * @param  array<string, mixed>  $datos
     *
     * @throws SesionVencidaException
     */
    public function patch(string $ruta, array $datos = []): RespuestaApi
    {
        return $this->solicitar('PATCH', $ruta, $datos);
    }

    /**
     * Envío a una ruta pública (inicio de sesión y registro): no lleva token y un 401 no cierra sesión.
     *
     * @param  array<string, mixed>  $datos
     */
    public function publico(string $ruta, array $datos = []): RespuestaApi
    {
        return $this->solicitar('POST', $ruta, $datos, false);
    }

    /**
     * Punto único de entrada: valida el token, ejecuta la llamada y traduce el 401.
     *
     * @param  array<string, mixed>  $datos
     *
     * @throws SesionVencidaException
     */
    public function solicitar(string $metodo, string $ruta, array $datos = [], bool $autenticada = true): RespuestaApi
    {
        $token = null;

        if ($autenticada) {
            $token = $this->sesion->token();

            if ($token === null) {
                throw new SesionVencidaException('Sin token de acceso vigente.');
            }
        }

        $respuesta = $this->ejecutar($metodo, self::PREFIJO.'/'.ltrim($ruta, '/'), $datos, $token);

        if ($autenticada && $respuesta->estado === 401) {
            throw new SesionVencidaException('La API rechazó el token.');
        }

        return $respuesta;
    }

    /**
     * Ejecuta la sub-petición dentro de este mismo proceso.
     *
     * Antes y después se conservan la instancia «request» del contenedor, el usuario de la guardia de
     * autenticación y la ruta actual del router, porque el middleware de la API los modifica
     * (Auth::setUser, request(), Route::current()) y contaminarían la petición web que sigue en curso.
     *
     * @param  array<string, mixed>  $datos
     */
    protected function ejecutar(string $metodo, string $uri, array $datos, ?string $token): RespuestaApi
    {
        $original = $this->app->make('request');
        $guardia = Auth::guard();
        $usuarioPrevio = method_exists($guardia, 'hasUser') && $guardia->hasUser() ? $guardia->user() : null;
        $router = $this->app->make(Router::class);
        $rutaPrevia = $router->current();

        try {
            $respuesta = $this->nucleo->handle($this->construirPeticion($metodo, $uri, $datos, $token, $original));
            $json = json_decode((string) $respuesta->getContent(), true);

            return new RespuestaApi($respuesta->getStatusCode(), is_array($json) ? $json : []);
        } catch (Throwable $excepcion) {
            report($excepcion);

            return new RespuestaApi(0);
        } finally {
            $this->restaurar($original, $guardia, $usuarioPrevio, $router, $rutaPrevia);
        }
    }

    /**
     * Arma la petición JSON de la API conservando la IP del visitante real, para que los límites de
     * intentos (throttle) se apliquen por visitante y no a todo el panel a la vez.
     *
     * @param  array<string, mixed>  $datos
     */
    private function construirPeticion(string $metodo, string $uri, array $datos, ?string $token, Request $original): Request
    {
        $servidor = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'REMOTE_ADDR' => $original->ip() ?? '127.0.0.1',
        ];

        if ($token !== null) {
            $servidor['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        if ($metodo === 'GET') {
            return Request::create($uri, 'GET', $datos, [], [], $servidor);
        }

        return Request::create($uri, $metodo, [], [], [], $servidor, json_encode($datos, JSON_THROW_ON_ERROR));
    }

    /**
     * Devuelve el contenedor al estado que tenía antes de la sub-petición.
     */
    private function restaurar(Request $original, mixed $guardia, mixed $usuarioPrevio, Router $router, ?Route $rutaPrevia): void
    {
        $this->app->instance('request', $original);
        Facade::clearResolvedInstance('request');

        if ($usuarioPrevio !== null) {
            $guardia->setUser($usuarioPrevio);
        } elseif (method_exists($guardia, 'forgetUser')) {
            $guardia->forgetUser();
        }

        if ($rutaPrevia !== null) {
            $this->app->instance(Route::class, $rutaPrevia);
        }

        // El router no tiene método público para fijar su ruta y petición actuales.
        (function () use ($rutaPrevia, $original): void {
            $this->current = $rutaPrevia;
            $this->currentRequest = $original;
        })->call($router);
    }
}
