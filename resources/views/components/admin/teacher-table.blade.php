{{--
    Archivo: teacher-table.blade.php
    Módulo: resources/views/components/admin
    Descripción: Directorio administrativo compacto con acceso al detalle o a la gestión de cada cuenta.
    Autor: Axel Rebollo Zeferino
    Creado: 24/11/2026
    Modificado: 24/11/2026 · Equipo BigBitByte · Panel administrativo
--}}
@props(['docentes', 'gestion' => false])
<div class="ad-table-scroll" tabindex="0" role="region" aria-label="{{ __('admin.directorio') }}">
    <table class="ad-table"><thead><tr><th scope="col">{{ __('admin.col_docente') }}</th><th scope="col">{{ __('admin.col_acceso') }}</th><th scope="col">{{ __('admin.col_plan') }}</th><th scope="col">{{ __('admin.col_acciones') }}</th></tr></thead>
        <tbody>@foreach($docentes as $docente)
            <tr id="docente-{{ $docente['id'] }}"><td><div class="ad-person"><span class="ad-avatar" aria-hidden="true">{{ $docente['iniciales'] }}</span><div><strong>{{ $docente['nombre'] }}</strong><small title="{{ $docente['correo'] }}">{{ $docente['correo'] }}</small></div></div></td>
                <td><span class="ad-badge {{ $docente['suspendido_en'] ? 'ad-badge-error' : 'ad-badge-success' }}">{{ $docente['suspendido_en'] ? __('admin.suspendido') : __('admin.habilitado') }}</span></td>
                <td><span>{{ $docente['estado_pago'] === 'activo' ? __('admin.plan_activo') : __('admin.plan_inactivo') }}</span></td>
                <td>@if($gestion)<x-boton variante="secundario" type="button" data-modal-abrir="cuenta-{{ $docente['id'] }}" aria-label="{{ __('admin.gestionar') }} {{ $docente['nombre'] }}">{{ __('admin.gestionar') }}</x-boton>@else<x-boton variante="texto" :href="route('panel.docentes', ['q' => $docente['correo']]).'#docente-'.$docente['id']">{{ __('admin.ver_docente') }}</x-boton>@endif</td>
            </tr>
        @endforeach</tbody>
    </table>
</div>
