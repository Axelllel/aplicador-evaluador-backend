{{--
    Archivo: pagos.blade.php
    Módulo: resources/views/admin
    Descripción: Movimientos filtrables por estado, docente y mes con cambios de estado auditados.
    Autor: Axel Rebollo Zeferino
    Creado: 22/11/2026
    Modificado: 24/11/2026 · Equipo BigBitByte · Panel administrativo
--}}
<x-layouts.administracion :titulo="__('admin.pagos')" :descripcion="__('admin.pagos_descripcion')">
    <div class="ad-metrics">
        <x-admin.metric :etiqueta="__('admin.monto_confirmado')" :valor="$resumen['monto_confirmado']" :ayuda="__('admin.monto_confirmado_ayuda')" icono="banknotes" />
        <x-admin.metric :etiqueta="__('admin.pendiente')" :valor="$resumen['pendientes']" :ayuda="__('admin.pagos_pendientes_ayuda')" icono="credit-card" />
        <x-admin.metric :etiqueta="__('admin.confirmado')" :valor="$resumen['confirmados']" :ayuda="__('admin.total_movimientos')" icono="check-circle" />
        <x-admin.metric :etiqueta="__('admin.rechazado')" :valor="$resumen['rechazados']" :ayuda="__('admin.total_movimientos')" icono="x-circle" />
    </div>
    <form method="GET" action="{{ route('panel.pagos') }}" class="ad-card ad-filter" role="search">
        <div class="ad-filter-field ad-filter-search"><label for="admin-pago-q">{{ __('admin.pago_busqueda') }}</label><input type="search" name="q" id="admin-pago-q" value="{{ $filtros['q'] ?? '' }}" maxlength="150" placeholder="{{ __('admin.pago_busqueda') }}"></div>
        <div class="ad-filter-field"><label for="admin-pago-estado">{{ __('admin.estado_pago') }}</label><select name="estado" id="admin-pago-estado"><option value="">{{ __('admin.todos_estados') }}</option>@foreach(['pendiente','confirmado','rechazado'] as $estado)<option value="{{ $estado }}" @selected(($filtros['estado'] ?? '') === $estado)>{{ __('admin.'.$estado) }}</option>@endforeach</select></div>
        <div class="ad-filter-field"><label for="admin-pago-mes">{{ __('admin.mes_registro') }}</label><input type="month" name="mes" id="admin-pago-mes" value="{{ $filtros['mes'] ?? '' }}"></div>
        <div class="ad-filter-actions"><x-boton type="submit" icono="magnifying-glass">{{ __('admin.filtrar') }}</x-boton>@if(!empty($filtros['q']) || !empty($filtros['estado']) || !empty($filtros['mes']))<a href="{{ route('panel.pagos') }}">{{ __('admin.limpiar') }}</a>@endif</div>
    </form>
    <section class="ad-card"><header class="ad-table-heading"><h2>{{ __('admin.gestion_pagos') }}</h2><span>{{ $pagos->total() }} {{ __('admin.movimientos_cantidad') }}</span></header>
        @if($pagos->isNotEmpty())<div class="ad-table-scroll" tabindex="0" role="region" aria-label="{{ __('admin.gestion_pagos') }}"><table class="ad-table"><thead><tr><th scope="col">{{ __('admin.col_pago') }}</th><th scope="col">{{ __('admin.col_vigencia') }}</th><th scope="col">{{ __('admin.col_importe') }}</th><th scope="col">{{ __('admin.col_estado') }}</th><th scope="col">{{ __('admin.col_acciones') }}</th></tr></thead><tbody>
            @foreach($pagos as $pago)<tr><td><div class="ad-person"><span class="ad-avatar" aria-hidden="true"><x-icon name="credit-card" /></span><div><strong>{{ $pago['nombre'] }}</strong><small>{{ $pago['folio'] }} · {{ $pago['fecha_texto'] }}</small></div></div></td><td><span class="ad-period"><span>{{ $pago['inicio_texto'] }}</span><small>{{ $pago['fin_texto'] }}</small></span></td><td class="ad-mono"><strong>{{ $pago['importe'] }}</strong> <small>MXN</small></td><td><span class="ad-badge {{ match($pago['estado']) {'confirmado' => 'ad-badge-success', 'pendiente' => 'ad-badge-warning', default => 'ad-badge-error'} }}">{{ __('admin.'.$pago['estado']) }}</span></td><td><x-boton variante="secundario" type="button" data-modal-abrir="pago-{{ $pago['id'] }}" aria-label="{{ __('admin.gestionar_pago') }} {{ $pago['folio'] }}">{{ __('admin.gestionar') }}</x-boton></td></tr>@endforeach
        </tbody></table></div><div class="ad-table-footer">@if($pagos->hasPages()){{ $pagos->links('pagination.panel') }}@else{{ __('admin.mostrando', ['desde' => $pagos->firstItem(), 'hasta' => $pagos->lastItem(), 'total' => $pagos->total()]) }}@endif</div>
        @else<div class="ad-empty"><x-icon name="credit-card" /><h3>{{ $resumen['pagos'] ? __('admin.sin_coincidencias') : __('admin.sin_pagos') }}</h3><p>{{ __('admin.sin_coincidencias_ayuda') }}</p>@if($resumen['pagos'])<x-boton variante="secundario" :href="route('panel.pagos')">{{ __('admin.limpiar') }}</x-boton>@endif</div>@endif
    </section>
    @foreach($pagos as $pago)
        @php($bolsa = $errors->getBag('pago_'.$pago['id']))
        <dialog class="ad-dialog" id="pago-{{ $pago['id'] }}" aria-labelledby="pago-titulo-{{ $pago['id'] }}" @if($bolsa->any()) data-admin-dialog-open @endif><div class="ad-dialog-body">
            <header class="ad-dialog-heading"><div><h2 id="pago-titulo-{{ $pago['id'] }}">{{ __('admin.gestionar_pago') }}</h2><p>{{ $pago['folio'] }} · {{ $pago['nombre'] }}</p></div><button type="button" class="ad-icon-button" data-modal-cerrar aria-label="{{ __('acciones.cerrar') }}"><x-icon name="x-mark" /></button></header>
            <p class="ad-dialog-notice">{{ __('admin.pago_aviso') }}</p><strong>{{ $pago['importe'] }} MXN</strong>
            <form method="POST" action="{{ route('panel.pagos.estado', ['payment' => $pago['id']] + request()->query()) }}">@csrf @method('PATCH')
                <div><label for="pago-estado-{{ $pago['id'] }}">{{ __('admin.estado_pago') }}</label><select id="pago-estado-{{ $pago['id'] }}" name="estado" required>@foreach(['pendiente','confirmado','rechazado'] as $estado)<option value="{{ $estado }}" @selected(($bolsa->any() ? old('estado') : $pago['estado']) === $estado)>{{ __('admin.'.$estado) }}</option>@endforeach</select></div>
                <div><label for="pago-motivo-{{ $pago['id'] }}">{{ __('admin.motivo_etiqueta') }}</label><textarea id="pago-motivo-{{ $pago['id'] }}" name="motivo" rows="3" required minlength="10" maxlength="1000" aria-describedby="pago-ayuda-{{ $pago['id'] }}" @if($bolsa->has('motivo')) aria-invalid="true" @endif>{{ $bolsa->any() ? old('motivo') : '' }}</textarea><small id="pago-ayuda-{{ $pago['id'] }}">{{ __('admin.motivo_ayuda') }}</small>@if($bolsa->has('motivo'))<p class="ad-field-error" role="alert">{{ $bolsa->first('motivo') }}</p>@endif</div>
                <div class="ad-dialog-actions"><x-boton variante="secundario" type="button" data-modal-cerrar>{{ __('admin.cancelar') }}</x-boton><x-boton type="submit">{{ __('acciones.guardar') }}</x-boton></div>
            </form>
        </div></dialog>
    @endforeach
</x-layouts.administracion>
