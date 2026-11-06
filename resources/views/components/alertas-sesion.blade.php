{{--
    Archivo: alertas-sesion.blade.php
    Módulo: resources/views/components
    Descripción: Muestra los mensajes guardados en la sesión: éxito e información como avisos superiores
                 que se ocultan a los 5 s; error y advertencia como banners (Manual COD-WEB, sección 6.18).
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
--}}
@php
    $avisosSuperiores = collect(['exito', 'info'])->filter(fn (string $tipo): bool => session()->has($tipo));
    $banners = collect(['error', 'aviso'])->filter(fn (string $tipo): bool => session()->has($tipo));
@endphp
@if ($avisosSuperiores->isNotEmpty())
    <div class="pointer-events-none fixed inset-x-0 top-20 z-50 flex flex-col items-center gap-3 px-4">
        @foreach ($avisosSuperiores as $tipo)
            <x-alerta :tipo="$tipo" class="pointer-events-auto w-full max-w-xl">{{ session($tipo) }}</x-alerta>
        @endforeach
    </div>
@endif
@foreach ($banners as $tipo)
    <x-alerta :tipo="$tipo">{{ session($tipo) }}</x-alerta>
@endforeach
