<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\StudentPortalController;

Route::get('/', fn () => view('welcome'));

Route::get('/probar-auth', function () {
    return response()->json(['message' => 'Autenticado']);
})->middleware(\App\Http\Middleware\VerifySupabaseToken::class);

Route::prefix('alumno')->name('student.')->group(function () {
    Route::get('/ingreso', [StudentPortalController::class, 'entry'])->name('entry');
    Route::post('/ingresar', [StudentPortalController::class, 'join'])->name('join')->middleware('throttle:5,1');
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
