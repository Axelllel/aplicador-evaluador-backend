{{--
    Archivo: 404.blade.php
    Módulo: resources/views/errors
    Descripción: Página de error para una dirección inexistente: texto en español, sin códigos ni
                 detalles técnicos (Manual COD-WEB, sección 6.18).
    Autor: Leonardo Ballestero López
    Creado: 28/10/2026
    Modificado: 28/10/2026 · Leonardo Ballestero López · Versión inicial
--}}
<x-layouts.invitado :titulo="__('errores.no_encontrada.titulo')">
    <x-alerta tipo="error">{{ __('errores.no_encontrada.mensaje') }}</x-alerta>

    <div class="flex justify-end">
        <x-boton :href="url('/login')" variante="secundario">{{ __('errores.no_encontrada.volver') }}</x-boton>
    </div>
</x-layouts.invitado>
