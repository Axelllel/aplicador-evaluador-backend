<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('examenes:cerrar-vencidos')->everyTenSeconds()->withoutOverlapping(2);

Schedule::command('examenes:procesar-calificaciones')->everyMinute()->withoutOverlapping(5)->runInBackground();

if (config('backup.enabled')) {
    Schedule::command('bd:respaldar')->dailyAt('23:00')->timezone('America/Mexico_City')->withoutOverlapping(60)->runInBackground();
}
