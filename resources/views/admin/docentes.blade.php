{{--
    Archivo: docentes.blade.php
    Módulo: resources/views/admin
    Descripción: Directorio filtrable y paginado de docentes con gestión contextual de acceso y motivo auditable.
    Autor: Axel Rebollo Zeferino
    Creado: 22/11/2026
    Modificado: 24/11/2026 · Equipo BigBitByte · Panel administrativo
--}}
<x-layouts.administracion :titulo="__('admin.docentes')" :descripcion="__('admin.docentes_descripcion')">
    <div class="ad-summary-strip"><span>{{ __('admin.total_docentes') }}<strong>{{ $resumen['docentes'] }}</strong></span><span>{{ __('admin.habilitado') }}<strong>{{ $resumen['habilitados'] }}</strong></span><span>{{ __('admin.suspendido') }}<strong>{{ $resumen['suspendidos'] }}</strong></span></div>
    <form method="GET" action="{{ route('panel.docentes') }}" class="ad-card ad-filter" role="search">
        <div class="ad-filter-field ad-filter-search"><label for="admin-docente-q">{{ __('admin.nombre_correo') }}</label><input id="admin-docente-q" name="q" type="search" maxlength="150" value="{{ $filtros['q'] ?? '' }}" placeholder="{{ __('admin.buscar_docente') }}"></div>
        <div class="ad-filter-field"><label for="admin-docente-estado">{{ __('admin.estado_cuenta') }}</label><select id="admin-docente-estado" name="estado"><option value="">{{ __('admin.todos_estados') }}</option>@foreach(['habilitado','suspendido'] as $estado)<option value="{{ $estado }}" @selected(($filtros['estado'] ?? '') === $estado)>{{ __('admin.'.$estado) }}</option>@endforeach</select></div>
        <div class="ad-filter-actions"><x-boton type="submit" icono="magnifying-glass">{{ __('admin.filtrar') }}</x-boton>@if(!empty($filtros['q']) || !empty($filtros['estado']))<a href="{{ route('panel.docentes') }}">{{ __('admin.limpiar') }}</a>@endif</div>
    </form>
    <section class="ad-card"><header class="ad-table-heading"><h2>{{ __('admin.cuentas_docentes') }}</h2><span>{{ $docentes->total() }} {{ __('admin.cuentas') }}</span></header>
        @if($docentes->isNotEmpty())<x-admin.teacher-table :docentes="$docentes" :gestion="true" />
            <div class="ad-table-footer">@if($docentes->hasPages()){{ $docentes->links('pagination.panel') }}@else{{ __('admin.mostrando', ['desde' => $docentes->firstItem(), 'hasta' => $docentes->lastItem(), 'total' => $docentes->total()]) }}@endif</div>
        @else<div class="ad-empty"><x-icon name="users" /><h3>{{ $resumen['docentes'] ? __('admin.sin_coincidencias') : __('admin.sin_docentes') }}</h3><p>{{ __('admin.sin_coincidencias_ayuda') }}</p>@if($resumen['docentes'])<x-boton variante="secundario" :href="route('panel.docentes')">{{ __('admin.limpiar') }}</x-boton>@endif</div>@endif
    </section>
    @foreach($docentes as $docente)
        @php($bolsa = $errors->getBag('docente_'.$docente['id']))
        <dialog class="ad-dialog" id="cuenta-{{ $docente['id'] }}" aria-labelledby="cuenta-titulo-{{ $docente['id'] }}" @if($bolsa->any()) data-admin-dialog-open @endif>
            <div class="ad-dialog-body"><header class="ad-dialog-heading"><div><h2 id="cuenta-titulo-{{ $docente['id'] }}">{{ $docente['nombre'] }}</h2><p>{{ $docente['correo'] }}</p></div><button type="button" class="ad-icon-button" data-modal-cerrar aria-label="{{ __('acciones.cerrar') }}"><x-icon name="x-mark" /></button></header>
                <p class="ad-dialog-notice">{{ $docente['suspendido_en'] ? __('admin.habilitacion_aviso') : __('admin.suspension_aviso') }}</p>
                @if($docente['id'] === (app(\App\Services\SesionPanelService::class)->docente()['id'] ?? null))<small>{{ __('admin.cuenta_protegida') }}</small>@endif
                <form method="POST" action="{{ route('panel.docentes.estado', ['teacher' => $docente['id']] + request()->query()) }}">
                    @csrf @method('PATCH')<input type="hidden" name="suspender" value="{{ $docente['suspendido_en'] ? 0 : 1 }}">
                    <div><label for="motivo-docente-{{ $docente['id'] }}">{{ __('admin.motivo_etiqueta') }}</label><textarea id="motivo-docente-{{ $docente['id'] }}" name="motivo" rows="3" required minlength="10" maxlength="1000" aria-describedby="ayuda-docente-{{ $docente['id'] }}" @if($bolsa->has('motivo')) aria-invalid="true" @endif>{{ $bolsa->any() ? old('motivo') : '' }}</textarea><small id="ayuda-docente-{{ $docente['id'] }}">{{ __('admin.motivo_ayuda') }}</small>@if($bolsa->has('motivo'))<p class="ad-field-error" role="alert">{{ $bolsa->first('motivo') }}</p>@endif</div>
                    <div class="ad-dialog-actions"><x-boton variante="secundario" type="button" data-modal-cerrar>{{ __('admin.cancelar') }}</x-boton><x-boton type="submit" :variante="$docente['suspendido_en'] ? 'principal' : 'peligro-solido'" :disabled="!$docente['suspendido_en'] && $docente['id'] === (app(\App\Services\SesionPanelService::class)->docente()['id'] ?? null)">{{ $docente['suspendido_en'] ? __('admin.rehabilitar') : __('admin.suspender') }}</x-boton></div>
                </form>
            </div>
        </dialog>
    @endforeach
</x-layouts.administracion>
