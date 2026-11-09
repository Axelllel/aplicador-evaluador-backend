@extends('student.layout')
@section('title','Calificaciones')
@section('screen','grades')
@section('body')
<header class="sh-header"><div class="sh-brand-area"><a class="sh-brand" href="{{ route('student.home') }}"><img src="{{ asset('images/bigbitbyte-logo.png') }}" alt="Logo BigBitByte"><span>BigBit<span>Byte</span><b>.</b></span></a></div><div class="sh-header-right"><span class="sh-portal-tag">Portal del estudiante</span><button class="sh-icon-button" data-theme-toggle aria-label="Cambiar tema">☾</button></div></header>
<main class="sh-main" style="margin:0"><div class="sh-content student-grades"><div class="sh-breadcrumb">Mi espacio › <strong>Calificaciones</strong></div><div class="sh-page-heading"><div><p class="sh-welcome-label">SEGUIMIENTO ACADÉMICO</p><h1>Mis calificaciones<span>.</span></h1><p>Las calificaciones se consultan directamente en Supabase y solo aparecen cuando el docente lo permite.</p></div><a class="bbb-secondary" href="{{ route('student.home') }}">← Inicio</a></div>
@if($attempt && $attempt['finished'])<section class="sg-result-panel"><div class="sg-result-main"><div class="sg-result-icon">▤</div><div><p>Examen #{{ $attempt['examen_id'] }} · {{ $student['materia'] }}</p><h2 id="bbb-result-title">Consultando el resultado real…</h2><div class="sg-large-score" id="bbb-result-score" hidden><strong id="bbb-result-score-val"></strong><span id="bbb-score-scale"></span></div><span class="sg-score-explanation" id="bbb-result-description"></span></div></div><div class="sg-review-progress"><span id="bbb-graded-count"></span><div><span id="bbb-graded-progress" style="width:0"></span></div><p id="bbb-graded-pending"></p></div></section>
<div class="sg-publication-note">Si el resultado está oculto o en revisión, Laravel no revelará una nota antes de la publicación autorizada.</div>
@elseif($attempt && !$attempt['finished'])<div class="bbb-empty">Tu examen está en curso. <a href="{{ route('student.exam') }}">Continuar examen →</a></div>
@else<div class="bbb-empty">Aún no hay exámenes entregados en esta sesión. <a href="{{ route('student.home') }}">Ir al inicio →</a></div>@endif
</div></main>
@endsection
