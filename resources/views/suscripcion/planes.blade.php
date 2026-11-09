<x-layouts.panel :titulo="__('suscripcion.planes.encabezado')" :descripcion="__('suscripcion.planes.descripcion')">
    <div>
        <x-boton variante="secundario" :href="route('panel.suscripcion')">
            {{ __('acciones.volver_a_suscripcion') }}
        </x-boton>
    </div>

    @if ($planes->isEmpty())
        <x-tarjeta>
            <p>{{ __('suscripcion.planes.vacia') }}</p>
        </x-tarjeta>
    @else
        <x-tabla :titulo="__('suscripcion.planes.titulo')" :columnas="array_values(__('suscripcion.planes.columnas'))">
            @foreach ($planes as $plan)
                <x-tabla.fila>
                    <x-tabla.celda class="font-semibold">
                        <span>{{ $plan['nombre'] }}</span>
                        @if ($idDelPlanVigente === (int) $plan['id'])
                            <x-insignia tono="exito" class="ml-2">{{ __('suscripcion.planes.plan_actual') }}</x-insignia>
                        @endif
                    </x-tabla.celda>
                    <x-tabla.celda>{{ $plan['max_grupos'] }}</x-tabla.celda>
                    <x-tabla.celda>{{ $plan['max_preguntas'] }}</x-tabla.celda>
                    <x-tabla.celda>{{ $plan['max_alumnos'] }}</x-tabla.celda>
                    <x-tabla.celda>{{ __('suscripcion.precio', ['monto' => number_format((float) $plan['precio'], 2, '.', ',')]) }}</x-tabla.celda>
                </x-tabla.fila>
            @endforeach
        </x-tabla>
    @endif
</x-layouts.panel>
