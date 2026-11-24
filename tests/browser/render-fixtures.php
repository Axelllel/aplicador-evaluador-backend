<?php

use App\Services\AdministracionVistaService;
use App\Services\PaginadorPanel;
use App\Services\ResultadosPanelService;
use Carbon\CarbonImmutable;
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

$reviewAnswers = collect([
    ['id' => 201, 'tipo' => 'relacion_columnas', 'enunciado' => 'Relaciona los conceptos.', 'respuesta_texto' => '["Valor fijo","Valor fijo"]',
        'respuesta_modelo_snapshot' => "Variable = Valor modificable\nConstante = Valor fijo", 'opciones' => [['izquierda' => 'Variable', 'derecha' => 'Valor modificable'], ['izquierda' => 'Constante', 'derecha' => 'Valor fijo']],
        'calificacion_final' => 50, 'revisada_manual' => false, 'ponderacion' => 50],
    ['id' => 202, 'tipo' => 'abierta', 'enunciado' => 'Define variable.', 'respuesta_texto' => 'Un valor que cambia.', 'respuesta_modelo_snapshot' => 'Un valor modificable.',
        'calificacion_final' => null, 'revisada_manual' => false, 'ponderacion' => 50, 'puntaje_ia' => 85,
        'retroalimentacion_ia' => ['proveedor' => 'groq', 'modelo' => 'modelo de prueba', 'justificacion' => 'Reconoce el concepto.', 'requiere_revision' => false, 'observaciones' => []]],
]);
$detail = app(ResultadosPanelService::class)->detalle(['escala' => '0-10'], [
    'nombre' => 'Alumno de prueba', 'estado' => 'finalizado', 'completo' => false, 'calificacion' => null, 'calificadas' => 1, 'total_preguntas' => 2,
], [], $reviewAnswers) + ['examen' => 'Conceptos básicos'];
file_put_contents('public/qa-revision.html', view('resultados.intento', [
    'detalle' => $detail, 'examenId' => 1, 'intentoId' => 1,
    'incidencias' => app(PaginadorPanel::class)->paginar(collect(), 1),
])->render());

$pending = $reviewAnswers->map(fn (array $answer): array => $answer['id'] === 202
    ? array_replace($answer, ['retroalimentacion_ia' => null, 'puntaje_ia' => null, 'estado_ia' => 'pendiente', 'estado_automatico' => 'pendiente']) : $answer);
$detail = app(ResultadosPanelService::class)->detalle(['escala' => '0-10'], [
    'nombre' => 'Alumno de prueba', 'estado' => 'finalizado', 'completo' => false, 'calificacion' => null, 'calificadas' => 1, 'total_preguntas' => 2,
], [], $pending) + ['examen' => 'Conceptos básicos'];
file_put_contents('public/qa-pending.html', view('resultados.intento', [
    'detalle' => $detail, 'examenId' => 1, 'intentoId' => 1,
    'incidencias' => app(PaginadorPanel::class)->paginar(collect(), 1),
])->render());

$groupDeletion = ['id' => 1, 'materia' => 'Historia', 'folio_codigo' => 'FOLIO123', 'activo' => true,
    'alumnos' => 3, 'examenes' => 2, 'intentos' => 4, 'evaluaciones' => 1, 'intentos_en_curso' => 0];
file_put_contents('public/qa-delete-group.html', view('grupos.eliminar', ['grupo' => $groupDeletion])->render());
file_put_contents('public/qa-delete-student.html', view('grupos.eliminar-alumno', [
    'grupo' => $groupDeletion, 'alumno' => ['id' => 14, 'nombre' => 'Alumno de prueba', 'intentos_en_grupo' => 2, 'intentos_en_curso' => 0],
])->render());

// Registros sintéticos únicamente para verificar la composición administrativa en navegador.
$adminView = app(AdministracionVistaService::class);
$adminTeachers = $adminView->docentes(array_map(fn (int $n): array => [
    'id' => sprintf('22222222-2222-4222-8222-%012d', $n),
    'nombre' => ['Mariana Torres García', 'Ricardo Mendoza', 'Valeria Hernández', 'Gabriel López', 'Camila Sánchez'][$n % 5],
    'correo' => 'docente'.$n.'@ejemplo.test', 'estado_pago' => $n % 3 ? 'activo' : 'inactivo',
    'suspendido_en' => $n % 4 ? null : '2026-11-23',
], range(1, 18)));
$adminPayments = $adminView->pagos(array_map(fn (int $n): array => [
    'id' => $n, 'nombre' => $adminTeachers[$n % 18]['nombre'], 'monto' => [115, 230, 345, 460][$n % 4],
    'estado' => $n % 5 ? 'confirmado' : 'pendiente',
    'fecha' => CarbonImmutable::now('America/Mexico_City')->subMonths($n % 6)->toIso8601String(),
    'fecha_inicio_vigencia' => '2026-11-24', 'fecha_fin_vigencia' => '2026-12-24',
], range(1, 24)));
file_put_contents('public/qa-admin-dashboard.html', view('admin.dashboard', [
    'resumen' => $adminView->resumen($adminTeachers, $adminPayments), 'serie' => $adminView->serie($adminPayments),
    'docentes' => $adminTeachers->take(5), 'pendientes' => $adminPayments->where('estado', 'pendiente')->take(4),
])->render());
file_put_contents('public/qa-admin-teachers.html', view('admin.docentes', [
    'resumen' => $adminView->resumen($adminTeachers, collect()), 'filtros' => [],
    'docentes' => app(PaginadorPanel::class)->paginar($adminTeachers, 1),
])->render());
file_put_contents('public/qa-admin-payments.html', view('admin.pagos', [
    'resumen' => $adminView->resumen(collect(), $adminPayments), 'filtros' => [],
    'pagos' => app(PaginadorPanel::class)->paginar($adminPayments, 1),
])->render());

$manualReview = $reviewAnswers->map(fn (array $row): array => $row['id'] === 201 ? array_replace($row, ['revisada_manual' => true]) : $row);
$detail = app(ResultadosPanelService::class)->detalle(['escala' => '0-10'], [
    'nombre' => 'Alumno de prueba', 'estado' => 'finalizado', 'completo' => false, 'calificacion' => null, 'calificadas' => 1, 'total_preguntas' => 2,
], [], $manualReview) + ['examen' => 'Conceptos básicos'];
file_put_contents('public/qa-review-manual.html', view('resultados.intento', [
    'detalle' => $detail, 'examenId' => 1, 'intentoId' => 1,
    'incidencias' => app(PaginadorPanel::class)->paginar(collect(), 1),
])->render());
