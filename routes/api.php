<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StudentAttemptController;
use App\Http\Controllers\Api\StudentDashboardController;
use App\Http\Controllers\Api\StudentExamController;
use App\Http\Controllers\Api\StudentJoinController;
use App\Http\Controllers\Api\SupabaseAccountController;
use App\Http\Controllers\Api\TeacherAiController;
use App\Http\Controllers\Api\TeacherBillingController;
use App\Http\Controllers\Api\TeacherEvaluationController;
use App\Http\Controllers\Api\TeacherExamController;
use App\Http\Controllers\Api\TeacherExamPublishController;
use App\Http\Controllers\Api\TeacherExamSectionController;
use App\Http\Controllers\Api\TeacherGroupController;
use App\Http\Controllers\Api\TeacherQuestionController;
use App\Http\Controllers\Api\TeacherResultsController;
use App\Http\Controllers\Api\TeacherStudentController;
use App\Http\Middleware\VerifySupabaseToken;
use Illuminate\Support\Facades\Route;

// Auth: limites publicos; no exponer existencia de cuentas.
Route::post('/v1/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1,api-login');
Route::post('/v1/auth/register', [SupabaseAccountController::class, 'register'])->middleware('throttle:3,1,api-registro');
Route::post('/v1/auth/recover', [SupabaseAccountController::class, 'recover'])->middleware('throttle:3,1,api-recuperar');
Route::post('/v1/grupos/unirse', [StudentJoinController::class, 'store'])->middleware('throttle:5,1,api-unirse');
Route::post('/v1/student/dashboard', [StudentDashboardController::class, 'show'])->middleware('throttle:10,1,api-panel-movil');
Route::post('/v1/student/exams/{examenId}/start', [StudentAttemptController::class, 'start'])
    ->whereNumber('examenId')->middleware('throttle:5,1,api-inicio-movil');

// Compatibilidad con pases del docente; el inicio móvil por PIN usa la ruta anterior.
Route::prefix('/v1/student/attempts')->middleware('throttle:30,1,api-intentos')->group(function () {
    Route::post('/start', [StudentExamController::class, 'start']);
    Route::post('/questions', [StudentExamController::class, 'questions']);
    Route::post('/answers', [StudentExamController::class, 'answer']);
    Route::post('/finish', [StudentExamController::class, 'finish']);
    Route::post('/result', [StudentExamController::class, 'result']);
    Route::post('/incidents', [StudentExamController::class, 'incident']);
});

Route::middleware(VerifySupabaseToken::class)->group(function () {
    Route::get('/v1/teacher/groups', [TeacherGroupController::class, 'index']);
    Route::post('/v1/teacher/groups', [TeacherGroupController::class, 'store']);
    Route::get('/v1/teacher/exams', [TeacherExamController::class, 'index']);
    Route::post('/v1/teacher/exams', [TeacherExamController::class, 'store']);
    Route::delete('/v1/teacher/exams/{examenId}', [TeacherExamController::class, 'destroy'])->whereNumber('examenId');
    Route::get('/v1/teacher/questions', [TeacherQuestionController::class, 'index']);
    Route::post('/v1/teacher/questions', [TeacherQuestionController::class, 'store']);
    Route::post('/v1/teacher/exams/{examenId}/publish', [TeacherExamPublishController::class, 'publish'])->whereNumber('examenId');
    Route::get('/v1/teacher/exams/{examenId}/sections', [TeacherExamSectionController::class, 'index'])->whereNumber('examenId');
    Route::post('/v1/teacher/exams/{examenId}/questions', [TeacherExamSectionController::class, 'attachQuestionAutomatically'])->whereNumber('examenId');
    Route::post('/v1/teacher/exams/{examenId}/sections', [TeacherExamSectionController::class, 'store'])->whereNumber('examenId');
    Route::post('/v1/teacher/exams/{examenId}/sections/{apartadoId}/questions', [TeacherExamSectionController::class, 'attachQuestion'])
        ->whereNumber('examenId')->whereNumber('apartadoId');
    Route::patch('/v1/teacher/exams/{examenId}/questions/{examenPreguntaId}/weight', [TeacherExamSectionController::class, 'updateWeight'])
        ->whereNumber(['examenId', 'examenPreguntaId']);

    Route::get('/v1/teacher/groups/{grupoId}/students', [TeacherStudentController::class, 'index'])->whereNumber('grupoId');
    Route::post('/v1/teacher/exams/{examenId}/students/{alumnoId}/grant', [TeacherStudentController::class, 'authorization'])
        ->whereNumber('examenId')->whereNumber('alumnoId');
    Route::get('/v1/teacher/exams/{examenId}/attempts', [TeacherResultsController::class, 'attempts'])->whereNumber('examenId');
    Route::get('/v1/teacher/exams/{examenId}/results', [TeacherResultsController::class, 'results'])->whereNumber('examenId');
    Route::get('/v1/teacher/attempts/{attemptId}/answers', [TeacherResultsController::class, 'answers'])->whereNumber('attemptId');
    Route::get('/v1/teacher/attempts/{attemptId}/incidents', [TeacherResultsController::class, 'incidents'])->whereNumber('attemptId');
    Route::post('/v1/teacher/attempts/{attemptId}/auto-grade', [TeacherResultsController::class, 'autoGrade'])->whereNumber('attemptId');
    Route::patch('/v1/teacher/answers/{answerId}/grade', [TeacherResultsController::class, 'grade'])->whereNumber('answerId');

    Route::get('/v1/teacher/groups/{grupoId}/evaluations', [TeacherEvaluationController::class, 'index'])->whereNumber('grupoId');
    Route::post('/v1/teacher/groups/{grupoId}/evaluations', [TeacherEvaluationController::class, 'store'])->whereNumber('grupoId');
    Route::post('/v1/teacher/rubrics/{rubroId}/grades', [TeacherEvaluationController::class, 'gradeRubric'])->whereNumber('rubroId');
    Route::get('/v1/teacher/plans', [TeacherBillingController::class, 'plans']);
    Route::post('/v1/teacher/payments/simulate', [TeacherBillingController::class, 'simulate'])->middleware('throttle:10,1');
    Route::get('/v1/teacher/payments', [TeacherBillingController::class, 'payments']);
});

Route::middleware([VerifySupabaseToken::class.':short', 'throttle:6,1'])->group(function () {
    Route::post('/v1/teacher/ai/questions', [TeacherAiController::class, 'questions']);
    Route::post('/v1/teacher/ai/answers/{respuestaId}', [TeacherAiController::class, 'grade'])->whereNumber('respuestaId');
});
