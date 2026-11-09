{{--
    Archivo: logo.blade.php
    Módulo: resources/views/components
    Descripción: Único punto de inserción del logotipo de BigBitByte; conserva la proporción 537 × 600,
                 no se deforma ni se recolorea (Manual COD-WEB, sección 6.14).
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
--}}
@props(['alto' => 40])
@php
    $ancho = (int) round($alto * 537 / 600);
@endphp
<img
    src="{{ asset('images/logo.png') }}"
    alt="{{ __('panel.logo.alt') }}"
    width="{{ $ancho }}"
    height="{{ $alto }}"
    style="height: {{ $alto }}px"
    {{ $attributes->class(['w-auto max-w-none shrink-0 object-contain']) }}
>
