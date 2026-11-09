{{--
    Archivo: tarjeta.blade.php
    Módulo: resources/views/components
    Descripción: Tarjeta reutilizable con estilo adaptable al tema del docente
                 (Manual COD-WEB, sección 6.17, Tabla 15).
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 08/11/2026 · Adaptación de superficies al tema claro/oscuro del docente
--}}
<div {{ $attributes->class(['rounded-xl bg-superficie p-6 tp-card']) }}>
    {{ $slot }}
</div>
