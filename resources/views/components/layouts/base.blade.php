{{--
    Archivo: base.blade.php
    Módulo: resources/views/components/layouts
    Descripción: Estructura HTML común de todas las páginas: fondo blanco, tipografía del sistema
                 e interlineado de 1.5 (Manual COD-WEB, secciones 6.14 y 6.15).
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
--}}
@props(['titulo'])
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }} · {{ __('panel.nombre_sistema') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white font-sans text-base leading-normal text-primario">
    {{ $slot }}
</body>
</html>
