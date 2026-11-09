{{--
    Archivo: 500.blade.php
    Módulo: resources/views/errors
    Descripción: Página de error general del servidor: texto en español, sin códigos ni detalles técnicos
                 (Manual COD-WEB, sección 6.18).
    Autor: Leonardo Ballestero López
    Creado: 06/11/2026
    Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
--}}
<x-layouts.invitado :titulo="__('errores.general.titulo')">
    <x-alerta tipo="error">{{ __('errores.general.mensaje') }}</x-alerta>

    <div class="flex justify-end">
        <x-boton :href="url('/login')" variante="secundario">{{ __('errores.no_encontrada.volver') }}</x-boton>
    </div>
</x-layouts.invitado>
