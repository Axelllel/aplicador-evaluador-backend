{{--
    Archivo: volver-perfiles.blade.php
    Módulo: resources/views/components
    Descripción: Botón de regreso a la selección de perfil, independiente del historial. COD-WEB §§6.17–6.19.
    Autor: Axel Rebollo Zeferino
    Creado: 20/11/2026
    Modificado: 23/11/2026 · Leonardo Ballestero López · Cabecera de archivo
--}}
<x-boton variante="texto" sobre="superficie" :href="route('acceso')" icono="squares-2x2" class="entry-role-return">
    {{ __('sesion.perfiles.volver') }}
</x-boton>
