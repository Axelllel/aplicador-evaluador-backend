<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;

require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
URL::forceRootUrl('http://127.0.0.1:8899');
$student = ['alumno_id' => 14, 'grupo_id' => 1, 'nombre' => 'Alumno de prueba', 'materia' => 'Curso de historia'];
$attempt = ['intento_id' => 1, 'examen_id' => 1, 'fecha_inicio' => date('c')];
$errors = new ViewErrorBag;
View::share('errors', $errors);
foreach (['student.exam' => 'qa-exam', 'auth.login' => 'qa-login', 'auth.perfiles' => 'qa-perfiles', 'auth.admin-login' => 'qa-admin'] as $view => $file) {
    file_put_contents('public/'.$file.'.html', view($view, compact('student', 'attempt', 'errors'))->render());
}

file_put_contents('public/qa-payment.html', view('suscripcion.pago', [
    'grupos' => 3, 'alumnos' => 25, 'preguntas' => 150, 'precioCentavos' => 18100,
    'operacionId' => '11111111-1111-4111-8111-111111111111', 'errors' => $errors,
])->render());
