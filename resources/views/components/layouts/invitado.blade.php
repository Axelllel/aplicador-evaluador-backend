{{--
    Archivo: invitado.blade.php
    Módulo: resources/views/components/layouts
    Descripción: Diseño de las pantallas sin sesión: logotipo de 96 px centrado sobre un bloque
                 de color primario (Manual COD-WEB, secciones 6.14 y 6.16).
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 27/10/2026 · Leonardo Ballestero López · Logotipo sobre bloque primario con margen de 48 px
--}}
@props(['titulo'])
<x-layouts.base :titulo="$titulo">
    <a href="#contenido" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:inline-flex focus:min-h-11 focus:items-center focus:rounded-lg focus:border focus:border-primario focus:bg-white focus:px-4 focus:font-semibold focus:text-primario focus:underline">
        {{ __('panel.saltar_al_contenido') }}
    </a>

    <main id="contenido" tabindex="-1" class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center gap-6 p-6 focus:outline-hidden">
        <x-alertas-sesion />

        {{-- Margen libre del logotipo: la mitad de su alto, 48 px a 96 px (p-12). --}}
        <div class="mx-auto flex h-48 w-48 items-center justify-center rounded-xl bg-primario p-12">
            <x-logo :alto="96" />
        </div>

        <h1 class="text-center text-2xl font-semibold text-primario">{{ $titulo }}</h1>

        {{ $slot }}
    </main>
</x-layouts.base>
