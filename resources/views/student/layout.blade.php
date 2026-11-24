{{--
    Archivo: layout.blade.php
    Módulo: resources/views/student
    Descripción: Vista del módulo student de BigBitByte.
    Autor: Axel Rebollo Zeferino
    Creado: 22/11/2026
    Modificado: 22/11/2026 · Equipo BigBitByte · Integración y revisión
--}}
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="theme-color" content="{{ collect(app(\App\Services\PaletaColoresService::class)->colores())->firstWhere('token', 'primario')['hex'] }}">
<title>@yield('title', 'Portal del estudiante') · BigBitByte</title>
@vite(['resources/css/student-ui.css','resources/js/student-ui.js'])
    @vite('resources/css/ui-standards.css')
</head><body>
<div class="student-home" id="bbb-student-app"
 data-screen="@yield('screen')" data-started="{{ $attempt['fecha_inicio'] ?? '' }}" data-attempt="{{ $attempt['intento_id'] ?? '' }}" data-group="{{ $student['grupo_id'] }}"
 data-home="{{ route('student.home') }}" data-exam="{{ route('student.exam') }}" data-grades="{{ route('student.grades') }}" data-join-group="{{ route('student.groups.join') }}"
 data-incident="{{ route('student.incident') }}" data-time="{{ route('student.time') }}" data-open-question="{{ route('student.open-question') }}" data-start="{{ route('student.start') }}" data-questions="{{ route('student.questions') }}" data-answer="{{ route('student.answer') }}" data-finish="{{ route('student.finish') }}" data-result="{{ route('student.result', ($attempt['finished'] ?? false) ? ['intento' => $attempt['intento_id']] : []) }}">
@yield('body')
</div></body></html>
