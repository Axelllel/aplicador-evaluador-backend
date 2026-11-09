{{--
    Archivo: logo.blade.php
    Módulo: resources/views/components
    Descripción: Logo compartido con el portal del alumno, sin deformar la imagen original.
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 08/11/2026 · Unificación del logo entre alumno y docente
--}}
@props(['alto' => 40])
<img
    src="{{ asset('images/bigbitbyte-logo.png') }}"
    alt="{{ __('panel.logo.alt') }}"
    width="{{ $alto }}"
    height="{{ $alto }}"
    {{ $attributes->class(['w-auto max-w-none shrink-0 object-contain']) }}
>
