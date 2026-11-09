{{--
    Archivo: celda.blade.php
    Módulo: resources/views/components/tabla
    Descripción: Celda de la tabla de datos con borde de 1 px y relleno de 16 px (Manual COD-WEB, sección 6.17).
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
--}}
<td {{ $attributes->class(['h-12 border border-borde px-4 py-2 align-middle']) }}>
    {{ $slot }}
</td>
