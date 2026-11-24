<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LogoutController;
use App\Http\Controllers\Api\MfaController;
use App\Http\Controllers\Api\PinRecoveryController;
use App\Http\Controllers\Api\StudentAttemptController;
use App\Http\Controllers\Api\StudentDashboardController;
use App\Http\Controllers\Api\StudentExamController;
use App\Http\Controllers\Api\StudentJoinController;
use App\Http\Controllers\Api\SupabaseAccountController;
use App\Http\Controllers\Api\TeacherAiController;
use App\Http\Controllers\Api\TeacherAnalyticsController;
use App\Http\Controllers\Api\TeacherBillingController;
use App\Http\Controllers\Api\TeacherEvaluationController;
use App\Http\Controllers\Api\TeacherExamController;
use App\Http\Controllers\Api\TeacherExamPublishController;
use App\Http\Controllers\Api\TeacherExamSectionController;
use App\Http\Controllers\Api\TeacherGroupController;
use App\Http\Controllers\Api\TeacherQuestionController;
use App\Http\Controllers\Api\TeacherResultsController;
use App\Http\Controllers\Api\TeacherStudentController;
use App\Http\Middleware\NoStoreResponse;
use App\Http\Middleware\VerifySupabaseToken;
use Illuminate\Support\Facades\Route;

Route::post('/v1/auth/logout', LogoutController::class)->middleware([VerifySupabaseToken::class.':logout', 'throttle:10,1']);

// Auth: limites publicos; no exponer existencia de cuentas.
Route::post('/v1/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/v1/auth/register', [SupabaseAccountController::class, 'register'])->middleware('throttle:3,1');
Route::post('/v1/auth/recover', [SupabaseAccountController::class, 'recover'])->middleware('throttle:3,1');
Route::middleware(NoStoreResponse::class)->group(function () {
    Route::post('/v1/grupos/unirse', [StudentJoinController::class, 'store'])->middleware('throttle:student-entry');
    Route::post('/v1/student/dashboard', [StudentDashboardController::class, 'show'])->middleware('throttle:student-entry');
    Route::post('/v1/student/exams/{examenId}/start', [StudentAttemptController::class, 'start'])
        ->whereNumber('examenId')->middleware('throttle:student-entry');

    // Compatibilidad con pases del docente; el inicio móvil por PIN usa la ruta anterior.
    Route::prefix('/v1/student/attempts')->middleware('throttle:student-attempt')->group(function () {
        Route::post('/start', [StudentExamController::class, 'start']);
        Route::post('/time', [StudentExamController::class, 'time']);
        Route::post('/open-question', [StudentExamController::class, 'openQuestion']);
        Route::post('/questions', [StudentExamController::class, 'questions']);
        Route::post('/answers', [StudentExamController::class, 'answer']);
        Route::post('/finish', [StudentExamController::class, 'finish']);
        Route::post('/result', [StudentExamController::class, 'result']);
        Route::post('/incidents', [StudentExamController::class, 'incident']);
    });

});

Route::middleware(VerifySupabaseToken::class)->group(function () {
    Route::post('/v1/support/pin-recovery', [PinRecoveryController::class, 'store'])->middleware('throttle:5,1');
    Route::post('/v1/support/pin-recovery/{caseId}/authorize', [PinRecoveryController::class, 'authorizeRecovery'])->whereNumber('caseId')->middleware('throttle:5,1');
    Route::get('/v1/teacher/groups/{groupId}/analytics', [TeacherAnalyticsController::class, 'show'])->whereNumber('groupId');
    Route::get('/v1/teacher/exams/{examId}/results/export/{format}', [TeacherResultsController::class, 'export'])->whereNumber('examId')->whereIn('format', ['pdf', 'xlsx']);
    Route::get('/v1/admin/teachers', [AdminController::class, 'teachers']);
    Route::get('/v1/admin/payments', [AdminController::class, 'payments']);
    Route::patch('/v1/admin/teachers/{teacher}', [AdminController::class, 'teacherStatus'])->whereUuid('teacher');
    Route::patch('/v1/admin/payments/{payment}', [AdminController::class, 'paymentStatus'])->whereNumber('payment');
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
    Route::post('/v1/teacher/attempts/{attemptId}/prepare-grading', [TeacherResultsController::class, 'prepareGrading'])->whereNumber('attemptId')->middleware('throttle:3,1');
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

// Solo estas rutas pueden usarse con AAL1 para completar el segundo factor.
Route::prefix('/v1/auth/mfa')->middleware([VerifySupabaseToken::class.':mfa', 'throttle:5,1'])->group(function () {
    Route::get('/', [MfaController::class, 'index']);
    Route::post('/enroll', [MfaController::class, 'enroll']);
    Route::post('/{factor}/challenge', [MfaController::class, 'challenge'])->whereUuid('factor');
    Route::post('/{factor}/verify', [MfaController::class, 'verify'])->whereUuid('factor');
    Route::delete('/{factor}', [MfaController::class, 'destroy'])->whereUuid('factor');
});

// Contrato adicional v2: navegación temporizada, sin cambiar el contrato de preguntas v1.
Route::prefix('/v2/student/attempts')->middleware([NoStoreResponse::class, 'throttle:student-attempt'])->group(function () {
    Route::post('/questions', [StudentExamController::class, 'questionsV2']);
    Route::post('/open-question', [StudentExamController::class, 'openQuestion']);
    Route::post('/time', [StudentExamController::class, 'time']);
    Route::post('/answers', [StudentExamController::class, 'answerV2']);
    Route::post('/finish', [StudentExamController::class, 'finish']);
    Route::post('/result', [StudentExamController::class, 'result']);
    Route::post('/incidents', [StudentExamController::class, 'incident']);
});
