{{--
    Archivo: insignia.blade.php
    Módulo: resources/views/components
    Descripción: Etiqueta de estado con texto visible; el color acompaña al texto y nunca lo sustituye
                 (Manual COD-WEB, sección 6.14).
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
--}}
@props(['tono' => 'neutro'])
@php
    $clasesDelTono = match ($tono) {
        'exito' => 'border-exito text-exito',
        'aviso' => 'border-aviso text-aviso',
        'error' => 'border-error text-error',
        'primario' => 'border-primario text-primario',
        default => 'border-texto-suave text-texto-suave',
    };
@endphp
<span {{ $attributes->class(['inline-flex items-center rounded-lg border bg-white px-2 py-1 text-sm font-semibold', $clasesDelTono]) }}>{{ $slot }}</span>
