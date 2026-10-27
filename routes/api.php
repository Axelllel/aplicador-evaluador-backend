<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StudentJoinController;
use App\Http\Controllers\Api\TeacherGroupController;
use App\Http\Controllers\Api\TeacherExamController;
use App\Http\Controllers\Api\TeacherQuestionController;
use App\Http\Controllers\Api\TeacherExamSectionController;
use App\Http\Controllers\Api\TeacherExamPublishController;
use App\Http\Middleware\VerifySupabaseToken;

Route::post('/v1/auth/login', [AuthController::class, 'login']);

// Ingreso a grupo no equivale a autorizacion para responder examenes.
Route::post('/v1/grupos/unirse', [StudentJoinController::class, 'store'])
    ->middleware('throttle:5,1');

Route::middleware(VerifySupabaseToken::class)->group(function () {
    Route::get('/v1/teacher/groups', [TeacherGroupController::class, 'index']);
    Route::post('/v1/teacher/groups', [TeacherGroupController::class, 'store']);

    Route::get('/v1/teacher/exams', [TeacherExamController::class, 'index']);
    Route::post('/v1/teacher/exams', [TeacherExamController::class, 'store']);

    Route::get('/v1/teacher/questions', [TeacherQuestionController::class, 'index']);
    Route::post('/v1/teacher/questions', [TeacherQuestionController::class, 'store']);

    // Publicacion: valida configuracion, titularidad, vigencia y ponderaciones.
    Route::post('/v1/teacher/exams/{examenId}/publish', [TeacherExamPublishController::class, 'publish']);

    // Gestion de apartados y reactivos de un examen no publicado.
    Route::get('/v1/teacher/exams/{examenId}/sections', [TeacherExamSectionController::class, 'index']);
    Route::post('/v1/teacher/exams/{examenId}/sections', [TeacherExamSectionController::class, 'store']);
    Route::post('/v1/teacher/exams/{examenId}/sections/{apartadoId}/questions', [TeacherExamSectionController::class, 'attachQuestion']);
});
