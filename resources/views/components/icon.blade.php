{{--
    Archivo: icon.blade.php
    Módulo: resources/views/components
    Descripción: Único componente de íconos: incluye el SVG Heroicons 20/solid de components/icon/
                 con tamaño 20 × 20 y un solo color (Manual COD-WEB, secciones 6.17 y 6.19).
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
--}}
@props(['name'])
@include('components.icon.'.$name)
