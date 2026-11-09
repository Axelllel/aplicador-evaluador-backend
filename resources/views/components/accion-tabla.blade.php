{{--
    Archivo: accion-tabla.blade.php
    Módulo: resources/views/components
    Descripción: Acción de fila de tabla solo con ícono: área de 44 × 44 px, descripción emergente (title)
                 y texto alternativo para lectores de pantalla (Manual COD-WEB, secciones 6.17 y 6.18).
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
--}}
@props(['accion', 'href' => null, 'contexto' => null])
@php
    $accionPanel = \App\Enums\AccionPanel::from($accion);
    $colorDelIcono = $accionPanel->esDestructiva() ? 'text-error' : 'text-primario';
    $etiqueta = $href !== null ? 'a' : 'button';
    $atributosPropios = $href !== null ? ['href' => $href] : ['type' => 'button'];
@endphp
<{{ $etiqueta }} {{ $attributes->merge($atributosPropios)->merge(['title' => $accionPanel->texto()])->class(['inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg hover:bg-superficie focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acento', $colorDelIcono]) }}>
    <x-icon :name="$accionPanel->icono()" />
    <span class="sr-only">{{ $accionPanel->texto() }}@if ($contexto !== null): {{ $contexto }}@endif</span>
</{{ $etiqueta }}>
