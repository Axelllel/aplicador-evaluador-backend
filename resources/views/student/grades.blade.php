@extends('student.layout')
@section('title', 'Calificaciones')
@section('screen', 'grades')
@section('body')
@include('student.navigation', ['activePage' => 'grades'])

<main class="sh-main pb-8">
    <div class="sh-content student-grades">
        <a class="sg-back-link" href="{{ route('student.home') }}#bbb-pending">← Mis exámenes</a>
        <div class="sh-page-heading sg-detail-heading">
            <div>
                <p class="sh-welcome-label">{{ $student['materia'] }}</p>
                <h1 id="bbb-result-exam-name">{{ $attempt ? ($attempt['nombre'] ?? 'Examen #'.$attempt['examen_id']) : 'Mis calificaciones' }}</h1>
                <p>Grupo #{{ $student['grupo_id'] }} <span id="bbb-result-date" hidden></span></p>
            </div>
            @if ($attempt && $attempt['finished'])
                <span class="sg-review-chip" id="bbb-result-status">Consultando</span>
            @endif
        </div>
        @if ($attempt && $attempt['finished'])
            <section class="sg-result-panel" aria-live="polite">
                <div class="sg-result-main">
                    <div class="sg-result-icon"><x-icon name="clipboard-document-check" /></div>
                    <div>
                        <p>Resultado del examen</p>
                        <h2 id="bbb-result-title">Consultando el resultado…</h2>
                        <div class="sg-large-score" id="bbb-result-score" hidden>
                            <strong id="bbb-result-score-val"></strong><span id="bbb-score-scale"></span>
                        </div>
                        <span class="sg-score-explanation" id="bbb-result-description"></span>
                    </div>
                </div>
                <div class="sg-review-progress" id="bbb-review-progress" hidden>
                    <span id="bbb-graded-count"></span>
                    <div><span id="bbb-graded-progress" style="width:0"></span></div>
                    <p id="bbb-graded-pending"></p>
                </div>
            </section>
            <div class="sg-question-columns" id="bbb-result-questions" hidden>
                <section>
                    <div class="sg-category-heading">
                        <span class="sg-category-icon"><x-icon name="chart-bar" /></span>
                        <div><h2>Opción múltiple <span id="bbb-objective-count">0</span></h2><p>Preguntas que el docente puede calificar automáticamente.</p></div>
                    </div>
                    <div id="bbb-result-objective"></div>
                </section>
                <section>
                    <div class="sg-category-heading">
                        <span class="sg-category-icon"><x-icon name="document-text" /></span>
                        <div><h2>Revisión del docente <span id="bbb-manual-count">0</span></h2><p>Relación de columnas, respuestas abiertas y programación.</p></div>
                    </div>
                    <div id="bbb-result-manual"></div>
                </section>
            </div>
        @elseif ($attempt)
            <div class="bbb-empty">Tu examen está en curso. <a href="{{ route('student.exam') }}">Continuar examen →</a></div>
        @else
            <div class="bbb-empty">Aún no hay exámenes entregados en esta sesión. <a href="{{ route('student.home') }}">Ir al inicio →</a></div>
        @endif
    </div>
</main>
@endsection
