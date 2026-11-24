{{--
    Archivo: docentes.blade.php
    Módulo: resources/views/admin
    Descripción: Lista de docentes y suspensión o rehabilitación con motivo auditable.
    Autor: Axel Rebollo Zeferino
    Creado: 22/11/2026
    Modificado: 22/11/2026 · Equipo BigBitByte · Administración
--}}
<x-layouts.panel :titulo="__('admin.docentes')" :rol="\App\Enums\RolPanel::SuperAdmin">
    <div class="space-y-4">
        @forelse ($docentes as $docente)
            <x-tarjeta class="space-y-4">
                <h2 class="text-xl font-semibold">{{ $docente['nombre'] }}</h2>
                <p>{{ $docente['correo'] }} · {{ $docente['suspendido_en'] ? __('admin.suspendido') : __('admin.habilitado') }}</p>
                <p>{{ __('admin.estado_pago') }}: {{ $docente['estado_pago'] }}</p>
                <form method="POST" action="{{ route('panel.docentes.estado', $docente['id']) }}" class="flex flex-wrap items-end gap-4">
                    @csrf @method('PATCH')<input type="hidden" name="suspender" value="{{ $docente['suspendido_en'] ? 0 : 1 }}">
                    <label class="flex-1">{{ __('admin.motivo') }}<input class="mt-2 block w-full rounded-lg border border-borde p-3" name="motivo" required minlength="10" maxlength="1000"></label>
                    <x-boton type="submit" :variante="$docente['suspendido_en'] ? 'secundario' : 'peligro'">{{ $docente['suspendido_en'] ? __('admin.rehabilitar') : __('admin.suspender') }}</x-boton>
                </form>
            </x-tarjeta>
        @empty
            <x-tarjeta>{{ __('admin.sin_datos') }}</x-tarjeta>
        @endforelse
    </div>
</x-layouts.panel>
