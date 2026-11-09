{{--
    Archivo: seccion.blade.php
    Módulo: resources/views/panel
    Descripción: Página provisional de un destino del menú lateral que aún no tiene módulo propio
                 (Manual COD-WEB, sección 6.16).
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
--}}
<x-layouts.panel :titulo="$titulo" :rol="$rol">
    <x-tarjeta class="space-y-2">
        <h2 class="text-xl font-semibold text-primario">{{ __('panel.seccion_pendiente.encabezado') }}</h2>
        <p>{{ __('panel.seccion_pendiente.mensaje', ['seccion' => $titulo]) }}</p>
    </x-tarjeta>
</x-layouts.panel>
