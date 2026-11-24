{{--
    Archivo: dashboard.blade.php
    Módulo: resources/views/admin
    Descripción: Resumen administrativo con indicadores, movimientos y estado real de las cuentas.
    Autor: Axel Rebollo Zeferino
    Creado: 24/11/2026
    Modificado: 24/11/2026 · Equipo BigBitByte · Panel administrativo
--}}
<x-layouts.administracion :titulo="__('admin.resumen_general')" :descripcion="__('admin.dashboard_descripcion')">
    <div class="ad-metrics">
        <x-admin.metric :etiqueta="__('admin.total_docentes')" :valor="$resumen['docentes']" :ayuda="__('admin.total_docentes_ayuda')" icono="users" />
        <x-admin.metric :etiqueta="__('admin.cuentas_habilitadas')" :valor="$resumen['habilitados']" :ayuda="__('admin.cuentas_habilitadas_ayuda')" icono="check-circle" />
        <x-admin.metric :etiqueta="__('admin.pagos_pendientes')" :valor="$resumen['pendientes']" :ayuda="__('admin.pagos_pendientes_ayuda')" icono="credit-card" />
        <x-admin.metric :etiqueta="__('admin.monto_confirmado')" :valor="$resumen['monto_confirmado']" :ayuda="__('admin.monto_confirmado_ayuda')" icono="banknotes" />
    </div>
    <div class="ad-dashboard-grid">
        <section class="ad-card ad-card-pad" aria-labelledby="admin-movimientos-title">
            <header class="ad-card-heading"><div><h2 id="admin-movimientos-title">{{ __('admin.movimientos') }}</h2><p>{{ __('admin.movimientos_ayuda') }}</p></div><a href="{{ route('panel.pagos', ['estado' => 'confirmado']) }}">{{ __('admin.ver_pagos') }}</a></header>
            <div class="ad-chart-total"><strong>{{ $serie['total'] }}</strong><small>{{ __('admin.total_periodo') }} · MXN</small></div>
            <svg class="ad-chart" viewBox="0 0 620 225" role="img" aria-labelledby="admin-chart-title"><title id="admin-chart-title">{{ __('admin.movimientos') }}: @foreach($serie['meses'] as $mes){{ $mes['mes'] }} {{ $mes['importe'] }}; @endforeach</title>
                @foreach([20,95,170] as $y)<line class="ad-chart-line" x1="75" y1="{{ $y }}" x2="610" y2="{{ $y }}" />@endforeach
                <text x="0" y="25">{{ $serie['maximo'] }}</text><text x="0" y="100">{{ $serie['mitad'] }}</text><text x="0" y="175">$0</text>
                @foreach($serie['meses'] as $i => $mes)
                    <rect class="ad-chart-bar" x="{{ 90+$i*86 }}" y="{{ 170-$mes['altura'] }}" width="42" height="{{ $mes['altura'] }}" rx="5"><title>{{ $mes['mes'] }}: {{ $mes['importe'] }}</title></rect><text x="{{ 111+$i*86 }}" y="201" text-anchor="middle">{{ $mes['mes'] }}</text>
                @endforeach
            </svg>
            <span class="ad-chart-caption">{{ __('admin.sin_ejemplo') }}</span>
        </section>
        <section class="ad-card ad-card-pad" aria-labelledby="admin-cuentas-title">
            <header class="ad-card-heading"><div><h2 id="admin-cuentas-title">{{ __('admin.distribucion') }}</h2><p>{{ __('admin.distribucion_ayuda') }}</p></div></header>
            <div class="ad-account-chart"><svg viewBox="0 0 120 120" aria-hidden="true"><circle class="ad-ring-bg" cx="60" cy="60" r="48"/><circle class="ad-ring-value" cx="60" cy="60" r="48" pathLength="100" stroke-dasharray="{{ $resumen['docentes'] ? round(100*$resumen['habilitados']/$resumen['docentes'],2) : 0 }} 100" /></svg><div><strong>{{ $resumen['docentes'] }}</strong><small>{{ __('admin.cuentas') }}</small></div></div>
            <div class="ad-legend"><div class="ad-legend-row"><span><i class="ad-dot" aria-hidden="true"></i>{{ __('admin.habilitado') }}</span><strong>{{ $resumen['habilitados'] }}</strong></div><div class="ad-legend-row"><span><i class="ad-dot ad-dot-muted" aria-hidden="true"></i>{{ __('admin.suspendido') }}</span><strong>{{ $resumen['suspendidos'] }}</strong></div></div>
        </section>
    </div>
    <section class="ad-card" aria-labelledby="admin-directorio-title"><header class="ad-table-heading"><h2 id="admin-directorio-title">{{ __('admin.directorio') }}</h2><a class="text-acento underline" href="{{ route('panel.docentes') }}">{{ __('admin.ver_todos') }}</a></header>
        @if($docentes->isNotEmpty())<x-admin.teacher-table :docentes="$docentes" />@else<div class="ad-empty"><x-icon name="users" /><h3>{{ __('admin.sin_docentes') }}</h3></div>@endif
    </section>
    <section class="ad-card ad-card-pad" aria-labelledby="admin-pendientes-title"><header class="ad-card-heading"><div><h2 id="admin-pendientes-title">{{ __('admin.pendientes_titulo') }}</h2><p>{{ __('admin.pagos_pendientes_ayuda') }}</p></div><a href="{{ route('panel.pagos', ['estado' => 'pendiente']) }}">{{ __('admin.ver_pagos') }}</a></header>
        @if($pendientes->isNotEmpty())<div class="ad-attention-list">@foreach($pendientes as $pago)<a class="ad-attention-item" href="{{ route('panel.pagos', ['q' => $pago['folio']]) }}"><div><strong>{{ $pago['nombre'] }}</strong><small>{{ $pago['folio'] }} · {{ $pago['fecha_texto'] }}</small></div><span class="ad-attention-amount">{{ $pago['importe'] }} <small>MXN</small></span></a>@endforeach</div>@else<div class="ad-empty"><x-icon name="check-circle" /><h3>{{ __('admin.todo_revisado') }}</h3><p>{{ __('admin.todo_revisado_ayuda') }}</p></div>@endif
    </section>
</x-layouts.administracion>
