{{--
    Archivo: volver-perfiles.blade.php
    Módulo: resources/views/components
    Descripción: Permite regresar al selector de perfiles desde el inicio de sesión.
    Autor: Axel Rebollo Zeferino
    Creado: 22/11/2026
    Modificado: 22/11/2026 · Equipo BigBitByte · Revisión de navegación
--}}
<x-boton variante="texto" sobre="superficie" :href="route('acceso')" icono="squares-2x2" class="entry-role-return">
    {{ __('sesion.perfiles.volver') }}
</x-boton>
