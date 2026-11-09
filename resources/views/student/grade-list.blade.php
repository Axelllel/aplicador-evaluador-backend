@extends('student.layout')
@section('title', 'Calificaciones')
@section('screen', 'grades')
@section('body')
@include('student.navigation', ['activePage' => 'grades'])

<main class="sh-main sh-dashboard">
    <div class="sh-content student-grades">
        <div class="sh-breadcrumb">Mi espacio › <strong>Calificaciones</strong></div>
        <div class="sh-page-heading">
            <div>
                <p class="sh-welcome-label">CADA LOGRO ES PARTE DE TU CAMINO</p>
                <h1>Calificaciones<span>.</span></h1>
                <p>{{ $student['materia'] }} · Grupo #{{ $student['grupo_id'] }}</p>
            </div>
            <a class="sg-secondary" href="{{ route('student.home', ['grupo' => $student['grupo_id']]) }}">← Volver al inicio</a>
        </div>

        @php
            $reviewed = count(array_filter($results, fn ($item) => $item['estado'] === 'final'));
            $shownResults = $status ? array_filter($results, fn ($item) => $item['estado_filtro'] === $status) : $results;
            $statusLabels = [
                'en_revision' => 'En revisión',
                'revision_completada' => 'Revisión completada',
                'sin_publicar' => 'Sin publicar',
            ];
        @endphp
        <div class="sh-stat-grid">
            <div class="sh-stat-card">
                <div class="sh-stat-icon sh-stat-blue"><x-icon name="document-text" /></div>
                <div><p>Evaluaciones realizadas</p><strong>{{ count($results) }}</strong><span>Exámenes entregados</span></div>
            </div>
            <div class="sh-stat-card">
                <div class="sh-stat-icon sh-stat-green"><x-icon name="check" /></div>
                <div><p>Revisión completada</p><strong>{{ $reviewed }}</strong><span>Resultados finales publicados</span></div>
            </div>
            <div class="sh-stat-card">
                <div class="sh-stat-icon sh-stat-purple"><x-icon name="information-circle" /></div>
                <div><p>En revisión o por publicar</p><strong>{{ count($results) - $reviewed }}</strong><span>Evaluaciones sin resultado final publicado</span></div>
            </div>
        </div>

        <section class="sh-exam-section" aria-labelledby="bbb-evaluations-title">
            <div class="sh-section-heading">
                <div>
                    <h2 id="bbb-evaluations-title">Mis evaluaciones <span>{{ count($shownResults) }}</span></h2>
                    <p>Selecciona una tarjeta para consultar su detalle.</p>
                </div>
                <form method="GET" action="{{ route('student.grades') }}" class="sh-filter">
                    <input type="hidden" name="grupo" value="{{ $student['grupo_id'] }}">
                    <label for="bbb-grades-status" class="sr-only">Filtrar evaluaciones por estado</label>
                    <select name="estado" id="bbb-grades-status">
                        <option value="">Todas las evaluaciones</option>
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <noscript><button class="sh-small-button" type="submit">Filtrar</button></noscript>
                </form>
            </div>
            <div class="sh-exam-grid">
                @forelse ($shownResults as $result)
                    @include('student.result-card', ['groupFilter' => $student['grupo_id'], 'statusFilter' => $status, 'statusLabel' => $statusLabels[$result['estado_filtro']]])
                @empty
                    <div class="bbb-empty sh-dashboard-empty">{{ $status ? 'No hay evaluaciones de este grupo con ese estado.' : 'Aún no hay evaluaciones entregadas para este grupo.' }}</div>
                @endforelse
            </div>
        </section>
    </div>
</main>
@endsection
