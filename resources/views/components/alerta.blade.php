{{--
    Archivo: alerta.blade.php
    Módulo: resources/views/components
    Descripción: Mensaje de éxito, error, advertencia o información. Éxito e información se ocultan a los 5 s;
                 el error es un banner persistente con botón de cierre (Manual COD-WEB, sección 6.18, Tabla 16).
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
--}}
@props(['tipo' => 'info', 'autoocultar' => true])
@php
    $seOcultaSola = in_array($tipo, ['exito', 'info'], true) && $autoocultar;
    $esPersistente = $tipo === 'error';

    $clasesDelFondo = match ($tipo) {
        'exito' => 'bg-exito',
        'error' => 'bg-error',
        'aviso' => 'bg-aviso',
        default => 'bg-primario',
    };

    $nombreDelIcono = match ($tipo) {
        'exito' => 'check-circle',
        'error' => 'x-circle',
        'aviso' => 'exclamation-triangle',
        default => 'information-circle',
    };
@endphp
<div
    data-alerta
    @if ($seOcultaSola) data-autoocultar="5000" @endif
    role="{{ $esPersistente ? 'alert' : 'status' }}"
    {{ $attributes->class(['flex items-start gap-3 rounded-lg px-4 py-3 text-base text-white', $clasesDelFondo]) }}
>
    <x-icon :name="$nombreDelIcono" class="mt-0.5" />

    <div class="flex min-w-0 flex-1 flex-wrap items-center gap-x-4 gap-y-2">
        <p class="min-w-0 flex-1 basis-48 py-0.5">
            <span class="sr-only">{{ __('mensajes.tipos.'.$tipo) }}: </span>{{ $slot }}
        </p>

        {{ $accion ?? '' }}
    </div>

    @if ($esPersistente)
        <button
            type="button"
            data-alerta-cerrar
            class="-my-2 -mr-2 inline-flex min-h-11 min-w-11 items-center justify-center gap-2 rounded-lg px-3 font-semibold hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
        >
            <x-icon name="x-mark" />
            <span>{{ __('acciones.cerrar') }}</span>
        </button>
    @endif
</div>
