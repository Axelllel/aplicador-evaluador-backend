<header class="sh-header">
    <div class="sh-brand-area">
        <button class="sh-icon-button sh-menu-button" id="bbb-menu-toggle" type="button" aria-expanded="false" aria-controls="student-navigation" aria-label="Abrir menú">☰</button>
        <a class="sh-brand" href="{{ route('student.home') }}">
            <img src="{{ asset('images/bigbitbyte-logo.png') }}" alt="Logotipo BigBitByte">
            <span>BigBit<span>Byte</span><b>.</b></span>
        </a>
    </div>
    <div class="sh-header-right">
        <span class="sh-portal-tag">Portal del estudiante</span>
        <button class="sh-icon-button" type="button" data-theme-toggle aria-label="Cambiar tema">☾</button>
        <x-student-account :nombre="$student['nombre']" />
    </div>
</header>

<aside class="sh-sidebar" id="student-navigation" hidden>
    <div class="sh-sidebar-caption">MI ESPACIO
        <button class="sh-icon-button sh-mobile-close" type="button" id="bbb-menu-close" aria-label="Cerrar menú"><x-icon name="x-mark" /></button>
    </div>
    <nav aria-label="Menú principal">
        <a class="sh-nav-item {{ $activePage === 'home' ? 'sh-nav-active' : '' }}" href="{{ route('student.home') }}" @if ($activePage === 'home') aria-current="page" @endif>⌂ &nbsp; Inicio</a>
        <a class="sh-nav-item" href="{{ $activePage === 'home' ? '' : route('student.home') }}#bbb-pending">▤ &nbsp; Exámenes por materia</a>
        <a class="sh-nav-item {{ $activePage === 'grades' ? 'sh-nav-active' : '' }}" href="{{ route('student.grades') }}" @if ($activePage === 'grades') aria-current="page" @endif>▥ &nbsp; Calificaciones</a>
        <button class="sh-nav-item" id="bbb-groups-open" type="button" aria-haspopup="dialog" aria-controls="bbb-groups-dialog"><x-icon name="user-group" /> Mis grupos</button>
        <a class="sh-nav-item sh-nav-join" href="{{ route('student.entry') }}">＋ &nbsp; Unirse a un grupo</a>
    </nav>
    <div class="sh-sidebar-group">
        <div class="sh-sidebar-group-icon" aria-hidden="true">▦</div>
        <div>
            <span>MI GRUPO ACTUAL</span>
            <strong>Grupo #{{ $student['grupo_id'] }}</strong>
            <p>{{ $student['materia'] }}</p>
        </div>
    </div>
</aside>

<dialog class="sh-dialog overflow-y-auto" id="bbb-groups-dialog" aria-labelledby="bbb-groups-title">
    <form method="dialog">
        <button class="sh-icon-button sh-dialog-close" type="submit" aria-label="Cerrar grupos"><x-icon name="x-mark" /></button>
    </form>
    <div class="sh-dialog-icon"><x-icon name="user-group" /></div>
    <p class="sh-dialog-eyebrow">APRENDEMOS EN COMUNIDAD</p>
    <h2 id="bbb-groups-title">Mis grupos</h2>

    <article class="mt-6 rounded-xl border border-[var(--sh-border)] bg-[var(--sh-pale)] p-5">
        <span class="ei-state ei-state-available"><span aria-hidden="true"></span> Grupo actual</span>
        <h3 class="mt-3 text-xl font-bold">Grupo #{{ $student['grupo_id'] }}</h3>
        <p class="mt-2 text-xs text-[var(--sh-muted)]">{{ $student['materia'] }}</p>
        @if (!empty($student['folio_codigo']))
            <p class="mt-4 border-t border-[var(--sh-border)] pt-3 font-mono text-xs text-[var(--sh-blue)]">{{ $student['folio_codigo'] }}</p>
        @endif
    </article>

    <a class="sh-dialog-action" href="{{ route('student.entry') }}"><x-icon name="plus" /> Unirse a un grupo</a>
    @if ($activePage === 'home')
        <form method="dialog">
            <button class="sh-dialog-action sh-dialog-secondary" type="submit">Volver al inicio →</button>
        </form>
    @else
        <a class="sh-dialog-action sh-dialog-secondary" href="{{ route('student.home') }}">Volver al inicio →</a>
    @endif
</dialog>
