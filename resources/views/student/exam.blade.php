@extends('student.layout')
@section('title', 'Resolver examen')
@section('screen', 'exam')
@section('body')
<div class="exam-session">
    <header class="es-header">
        <div class="es-header-left">
            <div class="sh-brand es-brand">
                <img src="{{ asset('images/bigbitbyte-logo.png') }}" alt="Logo BigBitByte">
                <span>BigBit<span>Byte</span><b>.</b></span>
            </div>
            <span class="es-header-divider" aria-hidden="true"></span>
            <div class="es-exam-name">
                <strong id="bbb-active-exam-title">Examen #{{ $attempt['examen_id'] }}</strong>
                <span>{{ $student['materia'] }} · Grupo #{{ $student['grupo_id'] }}</span>
            </div>
        </div>
        <div class="es-header-right">
            <div class="es-timer">
                <span aria-hidden="true">◷</span>
                <div><span>TIEMPO ESTIMADO RESTANTE</span><strong id="bbb-time">—</strong></div>
            </div>
            <button class="sh-icon-button" type="button" data-theme-toggle aria-label="Cambiar tema">☾</button>
        </div>
    </header>

    <main class="es-main pb-8">
        <section class="es-progress-panel">
            <div class="es-progress-heading">
                <div><p>TU PROGRESO</p><h1>Pregunta <b id="bbb-q-current">—</b> <span id="bbb-total-label"></span></h1></div>
                <div class="es-progress-count"><strong id="bbb-answered-count">—</strong><span>preguntas respondidas</span></div>
            </div>
            <div class="es-progress-track" aria-hidden="true"><span id="bbb-progress" style="width:0"></span></div>
            <div class="es-progress-foot"><span id="bbb-save-state" role="status">Cargando preguntas…</span><strong id="bbb-percent">0% completado</strong></div>
        </section>

        <nav class="es-question-nav" id="bbb-q-nav" aria-label="Navegación de preguntas"></nav>
        <div class="es-nav-legend">
            <span><i class="es-legend-current"></i> Actual</span><span><i class="es-legend-answered"></i> Respondida</span><span><i></i> Pendiente</span>
        </div>

        <section class="es-question-card">
            <header class="es-card-header">
                <span><x-icon name="document-text" /><span id="bbb-question-type">Pregunta</span></span>
                <span class="es-answer-status" id="bbb-q-status"><i></i><span id="bbb-q-status-label">Pendiente</span></span>
            </header>
            <div class="es-question-content">
                <span class="es-question-label" id="bbb-q-label"></span>
                <h2 id="bbb-question-heading" tabindex="-1">Cargando…</h2>
                <p class="es-question-hint" id="bbb-q-hint"></p>
                <div id="bbb-q-input"></div>
            </div>
        </section>

        <div class="es-navigation-actions">
            <button class="es-previous" id="bbb-prev" type="button" disabled>← Anterior</button>
            <span id="bbb-q-position"></span>
            <button class="es-next" id="bbb-next" type="button" disabled>Siguiente →</button>
        </div>
        <section class="es-review-panel">
            <div><div class="es-review-icon"><x-icon name="clipboard-document-check" /></div>
                <div><h3>Antes de terminar, revisa tus respuestas.</h3><p id="bbb-review-count">Consulta las preguntas pendientes.</p></div>
            </div>
            <button class="es-review-button" id="bbb-review-open" type="button" disabled>Revisar respuestas →</button>
        </section>
    </main>

    <dialog class="er-dialog" id="bbb-review" aria-labelledby="bbb-review-title">
        <button class="sh-icon-button er-close" data-review-close type="button" aria-label="Cerrar"><x-icon name="x-mark" /></button>
        <div class="er-heading-icon"><x-icon name="clipboard-document-check" /></div>
        <h2 id="bbb-review-title">Revisa tus respuestas</h2>
        <p class="er-exam-name" id="bbb-review-exam-name"></p>
        <dl class="er-summary">
            <div><dd id="bbb-review-total">0</dd><dt>Total de preguntas</dt></div>
            <div><dd id="bbb-review-done">0</dd><dt>Respondidas</dt></div>
            <div><dd id="bbb-review-pending">0</dd><dt>Pendientes</dt></div>
        </dl>
        <div class="er-list-heading"><h3>Estado de tus respuestas</h3><p>Selecciona una pregunta para volver a ella.</p></div>
        <ul class="er-question-list" id="bbb-review-list"></ul>
        <div class="er-warning" id="bbb-review-warning-box" hidden>
            <x-icon name="exclamation-triangle" />
            <div><strong id="bbb-review-warning"></strong><p>Aún puedes completarlas antes de entregar.</p></div>
        </div>
        <div class="er-actions">
            <button class="er-secondary" data-review-close type="button">Volver al examen</button>
            <button class="er-primary" id="bbb-submit" type="button">Entregar examen →</button>
        </div>
    </dialog>

    <dialog class="er-dialog" id="bbb-confirm" aria-labelledby="bbb-confirm-title">
        <h2 id="bbb-confirm-title">Confirmar entrega</h2>
        <div class="er-confirm-message"><x-icon name="information-circle" /><p id="bbb-confirm-summary"></p></div>
        <p role="alert" class="bbb-warn" id="bbb-finish-error" hidden></p>
        <div class="er-actions">
            <button class="er-secondary" id="bbb-confirm-back" type="button">Volver a la revisión</button>
            <button class="er-primary" id="bbb-confirm-submit" type="button">Sí, entregar</button>
        </div>
    </dialog>

    <dialog class="er-dialog" id="bbb-done-dialog" aria-labelledby="bbb-done-title">
        <div class="er-heading-icon"><x-icon name="check-circle" /></div>
        <h2 id="bbb-done-title">Examen entregado</h2>
        <p class="er-exam-name">Tus respuestas quedaron registradas.</p>
        <div class="bbb-modal-actions">
            <a class="bbb-primary" href="{{ route('student.grades') }}">Ver resultado →</a>
            <a class="bbb-secondary" href="{{ route('student.home') }}">Volver al inicio</a>
        </div>
    </dialog>
</div>
@endsection
