<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="theme-color" content="#1F2937">
<title>@yield('title', 'Portal del estudiante') · BigBitByte</title>
@vite(['resources/css/student-ui.css','resources/js/student-ui.js'])
</head><body>
<div class="student-home" id="bbb-student-app"
 data-screen="@yield('screen')" data-started="{{ $attempt['fecha_inicio'] ?? '' }}"
 data-home="{{ route('student.home') }}" data-exam="{{ route('student.exam') }}" data-grades="{{ route('student.grades') }}"
 data-start="{{ route('student.start') }}" data-questions="{{ route('student.questions') }}" data-answer="{{ route('student.answer') }}" data-finish="{{ route('student.finish') }}" data-result="{{ route('student.result') }}">
@yield('body')
</div></body></html>
