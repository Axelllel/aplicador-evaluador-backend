@extends('student.layout')
@section('title', 'Inicio')
@section('screen', 'home')
@section('body')
@include('student.navigation', ['activePage' => 'home'])

<main class="sh-main sh-dashboard">
    <div class="sh-content">
        <div class="sh-breadcrumb">⌂ <span>Mi espacio</span> › <strong>Inicio</strong></div>
        <div class="sh-page-heading">
            <div>
                <p class="sh-welcome-label">TU ESPACIO ACADÉMICO</p>
                <h1>Hola, {{ $student['nombre'] }}<span>.</span></h1>
                <p>Grupo #{{ $student['grupo_id'] }} · {{ $student['materia'] }}</p>
            </div>
        </div>

        <section class="sh-feature">
            <div class="sh-feature-content">
                <div class="sh-feature-label"><span></span> TU PRÓXIMO RETO</div>
                <h2>Un paso más hacia<br>lo que puedes lograr.</h2>
                <p>Consulta los exámenes de tu grupo e inicia cuando estén disponibles.</p>
                <div class="sh-feature-bottom">
                    @if ($activeInGroup)
                        <a class="bbb-primary" href="{{ route('student.exam') }}">Continuar mi examen →</a>
                    @else
                        <a class="bbb-primary" href="#bbb-pending">Ver mis exámenes →</a>
                    @endif
                </div>
            </div>
            <div class="sh-feature-art" aria-hidden="true">
                <div class="sh-art-orbit"></div>
                <div class="sh-art-orbit sh-orbit-two"></div>
                <div class="sh-art-sheet">
                    <div class="sh-art-sheet-heading"><span>▤ &nbsp; Tu próximo reto</span></div>
                    <div class="sh-art-lines"><span></span><span></span><span></span></div>
                    @for ($line = 0; $line < 3; $line++)
                        <div class="sh-art-answer"><span></span><i></i></div>
                    @endfor
                    <div class="sh-art-sheet-footer"><span></span><span></span></div>
                </div>
                <div class="sh-art-check">✓</div>
                <div class="sh-art-mini"><span>☆ &nbsp; ¡Sigue creciendo!</span></div>
            </div>
        </section>

        <div class="sh-stat-grid">
            <div class="sh-stat-card">
                <div class="sh-stat-icon sh-stat-blue"><x-icon name="document-text" /></div>
                <div><p>Por realizar</p><strong>{{ $pendingExams === null ? '—' : count($pendingExams) }}</strong><span>Exámenes publicados del grupo actual</span></div>
                <div class="sh-stat-decoration" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
            </div>
            <div class="sh-stat-card">
                <div class="sh-stat-icon sh-stat-green"><x-icon name="check" /></div>
                <div><p>Entregados</p><strong>{{ $completedCount }}</strong><span>Exámenes entregados en esta sesión</span></div>
                <div class="sh-stat-decoration sh-green-bars" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
            </div>
            <div class="sh-stat-card">
                <div class="sh-stat-icon sh-stat-purple"><x-icon name="user-group" /></div>
                <div><p>Grupo actual</p><strong>#{{ $student['grupo_id'] }}</strong><span>{{ $student['materia'] }}</span></div>
            </div>
        </div>

        <section class="sh-exam-section" id="bbb-pending" tabindex="-1">
            <div class="sh-section-heading">
                <div><h2>Exámenes pendientes</h2><p>Selecciona un examen para consultar sus detalles e iniciarlo dentro de su horario.</p></div>
            </div>
            <div class="sh-exam-grid">
                @if ($activeInGroup)
                    <a class="sh-exam-card" href="{{ route('student.exam') }}">
                        <div class="sh-exam-top"><span class="sh-subject-icon"><x-icon name="document-text" /></span><span class="sh-badge">En curso</span></div>
                        <div class="sh-exam-body">
                            <p class="sh-subject-name">{{ $student['materia'] }}</p>
                            <h3>{{ $activeInGroup['nombre'] ?? 'Examen #'.$activeInGroup['examen_id'] }}</h3>
                            <div class="sh-exam-bottom"><span class="bbb-status">Continuar →</span></div>
                        </div>
                    </a>
                @endif
                @foreach ($pendingExams ?? [] as $exam)
                    <button class="sh-exam-card text-left" type="button" aria-haspopup="dialog"
                            data-exam-details="{{ $exam->examen_id }}" data-exam-name="{{ $exam->nombre }}"
                            data-exam-available="{{ (int) $exam->disponible }}"
                            @isset($exam->fecha_apertura)
                                data-exam-opens="{{ \Carbon\CarbonImmutable::parse($exam->fecha_apertura)->toIso8601String() }}"
                                data-exam-closes="{{ \Carbon\CarbonImmutable::parse($exam->fecha_cierre)->toIso8601String() }}"
                                data-exam-duration="{{ $exam->duracion_segundos }}" data-exam-questions="{{ $exam->total_preguntas }}"
                            @endisset>
                        <span class="sh-exam-top">
                            <span class="sh-subject-icon"><x-icon name="document-text" /></span>
                            <span class="sh-badge {{ $exam->disponible ? 'sh-badge-available' : '' }}">{{ $exam->disponible ? 'Disponible' : 'Próximamente' }}</span>
                        </span>
                        <span class="sh-exam-body block">
                            <span class="sh-subject-name block">{{ $student['materia'] }}</span>
                            <span class="block text-[13px] font-bold">{{ $exam->nombre }}</span>
                            <span class="sh-exam-bottom"><span class="bbb-status">Ver detalles →</span></span>
                        </span>
                    </button>
                @endforeach
                @if ($pendingExams === null)
                    <div class="bbb-empty sh-dashboard-empty">No se pudieron consultar los exámenes. Recarga la página para volver a intentarlo.</div>
                @elseif (!$pendingExams && !$activeInGroup)
                    <div class="bbb-empty sh-dashboard-empty">No hay exámenes disponibles ni próximos para este grupo.</div>
                @endif
            </div>
        </section>

        <section class="sh-exam-section sh-recent-section" aria-labelledby="bbb-recent-title">
            <div class="sh-section-heading">
                <div>
                    <h2 id="bbb-recent-title">Terminados recientemente <span>{{ count($recentResults) }}</span></h2>
                    <p>Cada logro es parte de tu progreso.</p>
                </div>
                <a class="sh-text-button" href="{{ route('student.grades', $recentResults ? ['grupo' => $student['grupo_id']] : []) }}">Ver calificaciones →</a>
            </div>
            <div class="sh-exam-grid">
                @forelse ($recentResults as $result)
                    @include('student.result-card', ['groupFilter' => $student['grupo_id']])
                @empty
                    <div class="bbb-empty sh-dashboard-empty">Los exámenes que entregues aparecerán aquí.</div>
                @endforelse
            </div>
        </section>

    </div>
</main>

@if ($pendingExams)
    @include('student.exam-details')
@endif

@endsection
