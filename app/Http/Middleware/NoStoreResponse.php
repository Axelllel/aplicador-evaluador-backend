<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NoStoreResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        if ($request->is('recuperar-contrasena*', 'restablecer-contrasena*')) {
            $response->headers->set('Referrer-Policy', 'no-referrer');
        }

        return $response;
    }
}
