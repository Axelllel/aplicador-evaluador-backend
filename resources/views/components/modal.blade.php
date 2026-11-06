{{--
    Archivo: modal.blade.php
    Módulo: resources/views/components
    Descripción: Ventana modal con ancho máximo de 512 px, esquinas de 12 px y como máximo dos botones,
                 con la acción principal a la derecha (Manual COD-WEB, sección 6.17, Tabla 15).
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
--}}
@props(['id', 'titulo'])
<dialog
    id="{{ $id }}"
    aria-labelledby="{{ $id }}-titulo"
    {{ $attributes->class(['m-auto max-h-[calc(100%-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-xl bg-transparent p-0 backdrop:bg-primario/60']) }}
>
    <div class="space-y-4 rounded-xl bg-white p-6 text-base text-primario">
        <h2 id="{{ $id }}-titulo" class="text-xl font-semibold text-primario">{{ $titulo }}</h2>

        <div>{{ $slot }}</div>

        @isset($acciones)
            <div class="flex flex-wrap justify-end gap-3">
                {{ $acciones }}
            </div>
        @endisset
    </div>
</dialog>
