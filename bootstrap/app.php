<?php

use App\Http\Middleware\CabecerasDeSeguridad;
use App\Http\Middleware\RequiereSesionPanel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Render: conservar HTTPS tras el proxy inverso. Por defecto confía en todos los proxies (como antes);
        // en producción conviene fijar TRUSTED_PROXIES con la lista de IP o rangos separados por comas,
        // porque con '*' un cliente puede falsear su IP con X-Forwarded-For y esquivar los límites de intentos.
        $proxies = (string) env('TRUSTED_PROXIES', '*');
        $middleware->trustProxies(at: in_array($proxies, ['*', '**'], true) ? $proxies : array_map('trim', explode(',', $proxies)));
        $middleware->append(CabecerasDeSeguridad::class);
        // Protege el panel web: sin token de acceso en la sesión redirige al inicio de sesión.
        $middleware->alias([
            'panel.sesion' => RequiereSesionPanel::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
    
