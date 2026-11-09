{{--
    Archivo: fila.blade.php
    Módulo: resources/views/components/tabla
    Descripción: Fila de la tabla de datos con 48 px de alto como mínimo (Manual COD-WEB, sección 6.17).
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
--}}
<tr {{ $attributes->class(['h-12 bg-white']) }}>
    {{ $slot }}
</tr>
