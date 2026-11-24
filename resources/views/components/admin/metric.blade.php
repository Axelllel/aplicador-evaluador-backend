{{--
    Archivo: metric.blade.php
    Módulo: resources/views/components/admin
    Descripción: Indicador administrativo con etiqueta, valor real e icono accesible.
    Autor: Axel Rebollo Zeferino
    Creado: 24/11/2026
    Modificado: 24/11/2026 · Equipo BigBitByte · Panel administrativo
--}}
@props(['etiqueta', 'valor', 'ayuda', 'icono'])
<div class="ad-metric"><div class="ad-metric-label"><span>{{ $etiqueta }}</span><x-icon :name="$icono" /></div><strong class="ad-metric-value">{{ $valor }}</strong><small>{{ $ayuda }}</small></div>
