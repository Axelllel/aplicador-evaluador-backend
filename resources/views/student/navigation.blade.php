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
        <a class="sh-nav-item" href="{{ $activePage === 'home' ? '' : route('student.home', ['grupo' => $student['grupo_id']]) }}#bbb-pending">▤ &nbsp; Exámenes por materia</a>
        <a class="sh-nav-item {{ $activePage === 'grades' ? 'sh-nav-active' : '' }}" href="{{ route('student.grades') }}" @if ($activePage === 'grades') aria-current="page" @endif>▥ &nbsp; Calificaciones</a>
        <button class="sh-nav-item" id="bbb-groups-open" type="button" aria-haspopup="dialog" aria-controls="bbb-groups-dialog"><x-icon name="user-group" /> Mis grupos</button>
        <button class="sh-nav-item sh-nav-join" type="button" data-open-join-group aria-haspopup="dialog" aria-controls="bbb-join-group-dialog"><x-icon name="plus" /> Unirse a un grupo</button>
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

    <div class="mt-6 grid gap-3">
        @foreach ($groups as $group)
            <a class="block rounded-xl border border-[var(--sh-border)] bg-[var(--sh-pale)] p-5 hover:border-blue-400"
               href="{{ route('student.home', ['grupo' => $group['grupo_id']]) }}#bbb-pending"
               @if ($group['grupo_id'] === $student['grupo_id']) aria-current="true" @endif>
                @if ($group['grupo_id'] === $student['grupo_id'])
                    <span class="ei-state ei-state-available"><span aria-hidden="true"></span> Grupo actual</span>
                @endif
                <h3 class="mt-2 text-xl font-bold">Grupo #{{ $group['grupo_id'] }}</h3>
                <p class="mt-2 text-xs text-[var(--sh-muted)]">{{ $group['materia'] }}</p>
                @if (!empty($group['folio_codigo']))
                    <p class="mt-4 border-t border-[var(--sh-border)] pt-3 font-mono text-xs text-[var(--sh-blue)]">{{ $group['folio_codigo'] }}</p>
                @endif
                <span class="mt-3 block text-xs text-[var(--sh-blue)]">Ver exámenes →</span>
            </a>
        @endforeach
    </div>

    <button class="sh-dialog-action" type="button" data-open-join-group aria-haspopup="dialog" aria-controls="bbb-join-group-dialog"><x-icon name="plus" /> Unirse a un grupo</button>
    @if ($activePage === 'home')
        <form method="dialog">
            <button class="sh-dialog-action sh-dialog-secondary" type="submit">Volver al inicio →</button>
        </form>
    @else
        <a class="sh-dialog-action sh-dialog-secondary" href="{{ route('student.home', ['grupo' => $student['grupo_id']]) }}">Volver al inicio →</a>
    @endif
</dialog>

<dialog class="sh-dialog overflow-y-auto" id="bbb-join-group-dialog" aria-labelledby="bbb-join-group-title">
    <form method="dialog">
        <button class="sh-icon-button sh-dialog-close" type="submit" aria-label="Cerrar"><x-icon name="x-mark" /></button>
    </form>
    <div class="sh-dialog-icon"><x-icon name="user-group" /></div>
    <h2 id="bbb-join-group-title">Unirse a un grupo</h2>
    <form id="bbb-join-group-form" class="mt-5">
        <label for="bbb-join-folio" class="mb-2 block text-xs font-semibold">Folio del grupo</label>
        <input id="bbb-join-folio" name="folio_codigo" class="w-full rounded-lg border border-[var(--sh-border)] bg-[var(--sh-panel)] px-3 py-2.5 text-sm"
               type="text" pattern="[A-Za-z0-9]{4,20}" minlength="4" maxlength="20" placeholder="Escribe el folio de tu docente"
               autocapitalize="characters" autocomplete="off" spellcheck="false" required autofocus>
        <button class="sh-dialog-action sh-dialog-secondary" id="bbb-join-scan" type="button"><x-icon name="qr-code" /> Escanear QR</button>
        <video class="mt-3 w-full rounded-lg" id="bbb-join-video" autoplay playsinline muted hidden></video>
        <p id="bbb-join-scan-status" class="mt-3 text-xs text-[var(--sh-muted)]" role="status"></p>
        <p class="bbb-warn" id="bbb-join-group-error" role="alert" hidden></p>
        <button class="sh-dialog-action disabled:opacity-60" id="bbb-join-confirm" type="submit">Agregar grupo →</button>
    </form>
</dialog>
