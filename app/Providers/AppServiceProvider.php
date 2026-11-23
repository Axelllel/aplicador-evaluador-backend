<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('student-entry', function (Request $request): array {
            $student = $request->hasSession() ? $request->session()->get('bbb.student.alumno_id') : null;
            $pin = $request->input('codigo_acceso', '');
            $name = $request->input('nombre', '');
            $folio = $request->input('folio_codigo', '');
            $identity = $student ? 'student:'.$student : (is_string($folio) ? strtoupper($folio) : '').'|'.(is_string($pin) ? strtoupper($pin) : '').'|'.(is_string($name) ? mb_strtolower($name) : '');

            return [Limit::perMinute(5)->by('entry:'.hash('sha256', $identity.'|'.$request->ip())),
                Limit::perMinute(120)->by('entry-ip:'.$request->ip())];
        });
        RateLimiter::for('student-attempt', function (Request $request): array {
            $token = $request->hasSession() ? $request->session()->get('bbb.attempt.token_sesion', $request->input('token_sesion')) : $request->input('token_sesion');
            $token = is_string($token) ? $token : '';

            return [
                Limit::perMinute(120)->by('attempt:'.hash('sha256', $token.'|'.$request->ip())),
                Limit::perMinute(6000)->by('attempt-ip:'.$request->ip()),
            ];
        });
    }
}
