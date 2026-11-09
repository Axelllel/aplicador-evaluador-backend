{{--
    Archivo: resultado-contraste.blade.php
    Módulo: resources/views/components
    Descripción: Resultado de una comprobación de contraste con ícono y texto (cumple o no cumple);
                 el color nunca es el único indicador (Manual COD-WEB, sección 6.14).
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
--}}
@props(['cumple'])
<span {{ $attributes->class(['inline-flex items-center gap-2 font-semibold', $cumple ? 'text-exito' : 'text-error']) }}>
    <x-icon :name="$cumple ? 'check-circle' : 'x-circle'" />
    <span>{{ $cumple ? __('guia.contraste.cumple') : __('guia.contraste.no_cumple') }}</span>
</span>
