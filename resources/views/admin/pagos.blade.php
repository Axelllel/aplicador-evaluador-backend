{{--
    Archivo: pagos.blade.php
    Módulo: resources/views/admin
    Descripción: Control administrativo del estado de pagos sin exponer datos bancarios.
    Autor: Axel Rebollo Zeferino
    Creado: 22/11/2026
    Modificado: 22/11/2026 · Equipo BigBitByte · Administración
--}}
<x-layouts.panel :titulo="__('admin.pagos')" :rol="\App\Enums\RolPanel::SuperAdmin">
    <div class="space-y-4">
        @forelse ($pagos as $pago)
            <x-tarjeta class="space-y-4">
                <h2 class="text-xl font-semibold">{{ $pago['nombre'] }} · #{{ $pago['id'] }}</h2>
                <p>${{ number_format((float) $pago['monto'], 2) }} · {{ $pago['estado'] }}</p>
                <p>{{ $pago['fecha_inicio_vigencia'] }} → {{ $pago['fecha_fin_vigencia'] }}</p>
                <form method="POST" action="{{ route('panel.pagos.estado', $pago['id']) }}" class="flex flex-wrap items-end gap-4">
                    @csrf @method('PATCH')
                    <label>{{ __('admin.estado_pago') }}<select name="estado" class="mt-2 block rounded-lg border border-borde p-3">@foreach (['pendiente','confirmado','rechazado'] as $estado)<option value="{{ $estado }}" @selected($pago['estado'] === $estado)>{{ __('admin.'.$estado) }}</option>@endforeach</select></label>
                    <label class="flex-1">{{ __('admin.motivo') }}<input class="mt-2 block w-full rounded-lg border border-borde p-3" name="motivo" required minlength="10" maxlength="1000"></label>
                    <x-boton type="submit">{{ __('acciones.guardar') }}</x-boton>
                </form>
            </x-tarjeta>
        @empty
            <x-tarjeta>{{ __('admin.sin_datos') }}</x-tarjeta>
        @endforelse
    </div>
</x-layouts.panel>
