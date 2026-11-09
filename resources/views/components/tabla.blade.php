{{--
    Archivo: tabla.blade.php
    Módulo: resources/views/components
    Descripción: Tabla de datos: encabezado de color primario con texto blanco en seminegrita,
                 bordes de 1 px y filas de 48 px como mínimo (Manual COD-WEB, sección 6.17, Tabla 15).
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 27/10/2026 · Leonardo Ballestero López · Encabezado en seminegrita
--}}
@props(['titulo', 'columnas' => []])
<div class="relative overflow-x-auto rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acento" role="region" tabindex="0" aria-label="{{ $titulo }}">
    <table class="w-full border-collapse border border-borde text-left text-base">
        <caption class="sr-only">{{ $titulo }}</caption>
        <thead class="bg-primario text-white">
            <tr>
                @foreach ($columnas as $columna)
                    <th scope="col" class="h-12 border border-borde px-4 py-2 font-semibold">{{ $columna }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
