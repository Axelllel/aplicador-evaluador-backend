{{--
    Archivo: qr-grupo.blade.php
    Módulo: resources/views/components
    Descripción: Código QR del grupo de 256 × 256 px, centrado, con margen blanco de 16 px
                 y el folio del grupo debajo (Manual COD-WEB, sección 6.17, Tabla 15).
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
--}}
@props(['folio', 'svg'])
<figure class="mx-auto flex w-fit flex-col items-center gap-3">
    {{-- El SVG lo genera CodigoQrService a partir del folio; no contiene datos del usuario. --}}
    <div data-qr-grupo class="bg-white p-4">{!! $svg !!}</div>

    <figcaption>
        <x-codigo>{{ $folio }}</x-codigo>
    </figcaption>
</figure>
