{{--
    Archivo: tarjeta.blade.php
    Módulo: resources/views/components
    Descripción: Tarjeta con relleno de 24 px, fondo de superficie, esquinas de 12 px y sin sombra
                 (Manual COD-WEB, sección 6.17, Tabla 15).
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
--}}
<div {{ $attributes->class(['rounded-xl bg-superficie p-6']) }}>
    {{ $slot }}
</div>
