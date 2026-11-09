{{--
    Archivo: error.blade.php
    Módulo: resources/views/panel
    Descripción: Página del panel cuando la API no pudo entregar los datos: mensaje en español sin códigos ni
                 detalles técnicos y botón Reintentar (Manual COD-WEB, secciones 6.16 y 6.18).
    Autor: Leonardo Ballestero López
    Creado: 06/11/2026
    Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
--}}
<x-layouts.panel :titulo="$titulo">
    <x-alerta tipo="error">{{ $mensaje }}</x-alerta>

    <div>
        <x-boton accion="reintentar" variante="secundario" :href="url()->current()" />
    </div>
</x-layouts.panel>
