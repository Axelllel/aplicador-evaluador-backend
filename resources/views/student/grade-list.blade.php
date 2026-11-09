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
                <p>Consulta tus evaluaciones entregadas y sus resultados.</p>
            </div>
            <a class="sg-secondary" href="{{ route('student.home', $filter ? ['grupo' => $filter['grupo_id']] : []) }}">← Volver al inicio</a>
        </div>

        @php
            $reviewed = count(array_filter($results, fn ($item) => $item['estado'] === 'final'));
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
                    <h2 id="bbb-evaluations-title">Mis evaluaciones <span>{{ count($results) }}</span></h2>
                    <p>Selecciona una tarjeta para consultar su detalle.</p>
                </div>
                <form method="GET" action="{{ route('student.grades') }}" class="sh-filter">
                    <label for="bbb-grades-group" class="sr-only">Filtrar por grupo</label>
                    <select name="grupo" id="bbb-grades-group">
                        <option value="">Todos los grupos</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group['grupo_id'] }}" @selected(($filter['grupo_id'] ?? null) === $group['grupo_id'])>
                                {{ $group['materia'] }} · Grupo #{{ $group['grupo_id'] }}
                            </option>
                        @endforeach
                    </select>
                    <button class="sh-small-button" type="submit">Filtrar</button>
                </form>
            </div>
            <div class="sh-exam-grid">
                @forelse ($results as $result)
                    @include('student.result-card', ['groupFilter' => $filter['grupo_id'] ?? null])
                @empty
                    <div class="bbb-empty sh-dashboard-empty">{{ $filter ? 'Aún no has entregado evaluaciones de este grupo.' : 'Tus evaluaciones entregadas en esta sesión aparecerán aquí.' }}</div>
                @endforelse
            </div>
        </section>
    </div>
</main>
@endsection
