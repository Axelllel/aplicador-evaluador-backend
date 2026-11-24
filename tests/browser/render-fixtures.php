<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ViewErrorBag;

require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
URL::forceRootUrl('http://127.0.0.1:8899');
$student = ['alumno_id' => 14, 'grupo_id' => 1, 'nombre' => 'Alumno de prueba', 'materia' => 'Curso de historia'];
$attempt = ['intento_id' => 1, 'examen_id' => 1, 'fecha_inicio' => date('c')];
$errors = new ViewErrorBag;
foreach (['student.exam' => 'qa-exam', 'auth.login' => 'qa-login', 'auth.perfiles' => 'qa-perfiles'] as $view => $file) {
    file_put_contents('public/'.$file.'.html', view($view, compact('student', 'attempt', 'errors'))->render());
}
