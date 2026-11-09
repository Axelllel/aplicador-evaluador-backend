{{--
    Archivo: seccion.blade.php
    Módulo: resources/views/components/guia
    Descripción: Sección de la guía de estilo con su título y la cita en pequeño del manual y la sección
                 que cumple (Manual COD-WEB, sección 6.18).
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
--}}
@props(['id', 'titulo', 'referencia'])
<section id="{{ $id }}" aria-labelledby="{{ $id }}-titulo" class="scroll-mt-20 space-y-4">
    <div class="space-y-1">
        <h2 id="{{ $id }}-titulo" class="text-xl font-semibold text-primario">{{ $titulo }}</h2>
        <p class="text-xs text-texto-suave">{{ __('guia.referencia', ['seccion' => $referencia]) }}</p>
    </div>

    {{ $slot }}
</section>
