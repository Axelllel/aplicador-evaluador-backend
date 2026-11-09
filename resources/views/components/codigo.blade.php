{{--
    Archivo: codigo.blade.php
    Módulo: resources/views/components
    Descripción: Código en línea (folio, ruta) en fuente monoespaciada de 14 px sobre superficie
                 (Manual COD-WEB, sección 6.15, Tabla 13).
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
--}}
<code {{ $attributes->class(['rounded-lg border border-borde bg-superficie px-2 py-1 font-mono text-sm text-primario']) }}>{{ $slot }}</code>
