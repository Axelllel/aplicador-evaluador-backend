{{--
    Archivo: student-account.blade.php
    Módulo: resources/views/components
    Descripción: Vista del módulo components de BigBitByte.
    Autor: Axel Rebollo Zeferino
    Creado: 22/11/2026
    Modificado: 22/11/2026 · Equipo BigBitByte · Integración y revisión
--}}
@props(['nombre'])

<details class="sh-account">
    <summary class="sh-account-toggle">
        <span class="sh-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($nombre, 0, 1)) }}</span>
        <span class="sh-account-name">{{ $nombre }}</span>
        <x-icon name="chevron-down" />
    </summary>
    <div class="sh-account-menu">
        <span class="sh-account-role">Estudiante</span>
        <form method="POST" action="{{ route('student.leave') }}">
            @csrf
            <button class="bbb-secondary" type="submit">Cerrar sesión</button>
        </form>
    </div>
</details>
