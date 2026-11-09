<?php

/**
 * Archivo: web.php
 * Módulo: routes
 * Descripción: Rutas web con sesión, cookies y CSRF: inicio de sesión, registro, panel del docente
 *              protegido por el middleware «panel.sesion» y guía de estilo (solo en local)
 *              (Manual COD-WEB, secciones 6.1 y 6.16).
 * Autor: Leonardo Ballestero López
 * Creado: 27/10/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Rutas de Resultados (con calificación) y Estadísticas
 */

declare(strict_types=1);

use App\Http\Controllers\Web\ApartadoController;
use App\Http\Controllers\Web\EstadisticaController;
use App\Http\Controllers\Web\ExamenController;
use App\Http\Controllers\Web\GrupoController;
use App\Http\Controllers\Web\GuiaEstiloController;
use App\Http\Controllers\Web\PanelController;
use App\Http\Controllers\Web\PreguntaController;
use App\Http\Controllers\Web\RegistroController;
use App\Http\Controllers\Web\ResultadoController;
use App\Http\Controllers\Web\SesionController;
use App\Http\Controllers\Web\SuscripcionController;
use App\Http\Controllers\Web\StudentPortalController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'auth.perfiles')->name('acceso');

// Ruta de Axel para comprobar la autenticación con Supabase (se conserva).
Route::get('/probar-auth', function () {
    return response()->json(['message' => 'Autenticado']);
})->middleware(\App\Http\Middleware\VerifySupabaseToken::class);

// Acceso público: inicio de sesión y registro. Ambos hablan con /api/v1/auth/* en proceso.
Route::get('/login', [SesionController::class, 'create'])->name('login');
Route::post('/login', [SesionController::class, 'store'])->name('sesion.store');
Route::post('/logout', [SesionController::class, 'destroy'])->name('sesion.destroy');
Route::get('/registro', [RegistroController::class, 'create'])->name('registro');
Route::post('/registro', [RegistroController::class, 'store'])->name('registro.store');

// Panel del docente: exige un token vigente en la sesión.
// TODO(Leonardo Ballestero López): asignar CheckPlanLimits a la creación de alumnos cuando exista ese módulo.
Route::middleware('panel.sesion')->group(function (): void {
    Route::get('/panel', [PanelController::class, 'index'])->name('panel.inicio');

    Route::get('/grupos', [GrupoController::class, 'index'])->name('panel.grupos');
    Route::post('/grupos', [GrupoController::class, 'store'])->name('panel.grupos.store');
    Route::get('/grupos/{grupo}/alumnos', [GrupoController::class, 'students'])
        ->whereNumber('grupo')->name('panel.grupos.alumnos');

    Route::get('/banco-de-preguntas', [PreguntaController::class, 'index'])->name('panel.banco-de-preguntas');
    Route::post('/banco-de-preguntas', [PreguntaController::class, 'store'])->name('panel.banco-de-preguntas.store');

    Route::get('/examenes', [ExamenController::class, 'index'])->name('panel.examenes');
    Route::post('/examenes', [ExamenController::class, 'store'])->name('panel.examenes.store');
    Route::delete('/examenes/{examen}', [ExamenController::class, 'eliminar'])
        ->whereNumber('examen')->name('panel.examenes.eliminar');
    Route::post('/examenes/{examen}/publicar', [ExamenController::class, 'publicar'])
        ->whereNumber('examen')->name('panel.examenes.publicar');
    Route::get('/examenes/{examen}/apartados', [ApartadoController::class, 'show'])
        ->whereNumber('examen')->name('panel.examenes.apartados');
    Route::post('/examenes/{examen}/apartados', [ApartadoController::class, 'storeApartado'])
        ->whereNumber('examen')->name('panel.examenes.apartados.store');
    Route::post('/examenes/{examen}/preguntas', [ApartadoController::class, 'asignarPregunta'])
        ->whereNumber('examen')->name('panel.examenes.preguntas.store');
    Route::patch('/examenes/{examen}/preguntas/{examenPregunta}/ponderacion', [ApartadoController::class, 'actualizarPonderacion'])
        ->whereNumber(['examen', 'examenPregunta'])->name('panel.examenes.preguntas.ponderacion');

    Route::get('/suscripcion', [SuscripcionController::class, 'index'])->name('panel.suscripcion');
    Route::get('/suscripcion/planes', [SuscripcionController::class, 'catalogo'])->name('panel.suscripcion.planes');
    Route::get('/suscripcion/planes/pago', [SuscripcionController::class, 'resumenDePago'])->name('panel.suscripcion.planes.pago');
    Route::post('/suscripcion/planes/pago/datos', [SuscripcionController::class, 'revisarDatosDePago'])->name('panel.suscripcion.planes.pago.revisar');

    // Resultados: tabla de intentos por examen, detalle de un intento y calificación (manual y automática).
    Route::get('/resultados', [ResultadoController::class, 'index'])->name('panel.resultados');
    Route::get('/resultados/{examen}/intentos/{intento}', [ResultadoController::class, 'intento'])
        ->whereNumber(['examen', 'intento'])->name('panel.resultados.intento');
    Route::post('/resultados/{examen}/intentos/{intento}/autocalificar', [ResultadoController::class, 'autocalificar'])
        ->whereNumber(['examen', 'intento'])->name('panel.resultados.autocalificar');
    Route::patch('/resultados/{examen}/intentos/{intento}/respuestas/{respuesta}', [ResultadoController::class, 'calificar'])
        ->whereNumber(['examen', 'intento', 'respuesta'])->name('panel.resultados.calificar');

    // Estadísticas calculadas en el panel con los resultados de la API.
    Route::get('/estadisticas', [EstadisticaController::class, 'index'])->name('panel.estadisticas');
});

// Guía de estilo: el controlador responde 404 fuera de APP_ENV=local.
Route::get('/guia-estilo', [GuiaEstiloController::class, 'index'])->name('guia-estilo');

// Portal del alumno (preservado desde staging).
Route::prefix('alumno')->name('student.')->group(function () {
    Route::get('/ingreso', [StudentPortalController::class, 'entry'])->name('entry');
    Route::post('/validar-folio', [StudentPortalController::class, 'validateFolio'])->name('validate-folio')->middleware('throttle:10,1');
    Route::post('/ingresar', [StudentPortalController::class, 'join'])->name('join')->middleware('throttle:5,1');
    Route::post('/grupos', [StudentPortalController::class, 'joinGroup'])->name('groups.join')->middleware('throttle:5,1');
    Route::get('/inicio', [StudentPortalController::class, 'home'])->name('home');
    Route::post('/examen/iniciar', [StudentPortalController::class, 'start'])->name('start')->middleware('throttle:5,1');
    Route::get('/examen', [StudentPortalController::class, 'exam'])->name('exam');
    Route::post('/api/preguntas', [StudentPortalController::class, 'questions'])->name('questions');
    Route::post('/api/respuesta', [StudentPortalController::class, 'answer'])->name('answer');
    Route::post('/api/entrega', [StudentPortalController::class, 'finish'])->name('finish')->middleware('throttle:5,1');
    Route::get('/calificaciones', [StudentPortalController::class, 'grades'])->name('grades');
    Route::post('/api/resultado', [StudentPortalController::class, 'result'])->name('result');
    Route::post('/salir', [StudentPortalController::class, 'leave'])->name('leave');
});
