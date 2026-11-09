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
    <div class="sh-sidebar-caption">MI ESPACIO</div>
    <nav aria-label="Menú principal">
        <a class="sh-nav-item {{ $activePage === 'home' ? 'sh-nav-active' : '' }}" href="{{ route('student.home') }}" @if ($activePage === 'home') aria-current="page" @endif>⌂ &nbsp; Inicio</a>
        <a class="sh-nav-item" href="{{ $activePage === 'home' ? '' : route('student.home') }}#bbb-pending">▤ &nbsp; Exámenes por materia</a>
        <a class="sh-nav-item {{ $activePage === 'grades' ? 'sh-nav-active' : '' }}" href="{{ route('student.grades') }}" @if ($activePage === 'grades') aria-current="page" @endif>▥ &nbsp; Calificaciones</a>
        <a class="sh-nav-item" href="{{ $activePage === 'home' ? '' : route('student.home') }}#bbb-groups">♧ &nbsp; Mis grupos</a>
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
