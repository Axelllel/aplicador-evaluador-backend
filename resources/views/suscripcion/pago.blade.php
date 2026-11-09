<x-layouts.panel :titulo="__('suscripcion.resumen_pago.titulo')" :descripcion="__('suscripcion.resumen_pago.descripcion')">
    <x-tarjeta class="space-y-4">
        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (['grupos' => $grupos, 'alumnos' => $alumnos, 'preguntas' => $preguntas, 'capacidad' => $grupos * $alumnos] as $concepto => $cantidad)
                <div>
                    <dt class="text-sm text-texto-suave">{{ __('suscripcion.cotizacion.'.$concepto) }}</dt>
                    <dd class="text-xl font-semibold text-primario">{{ $cantidad }}</dd>
                </div>
            @endforeach
        </dl>

        <div class="border-t border-borde pt-4">
            <p class="text-sm text-texto-suave">{{ __('suscripcion.cotizacion.precio') }}</p>
            <p class="text-3xl font-semibold text-primario">
                {{ __('suscripcion.precio', ['monto' => number_format($precioCentavos / 100, 2, '.', ',')]) }}
                <span class="text-sm text-texto-suave">{{ __('suscripcion.cotizacion.moneda') }}</span>
            </p>
        </div>

        <p class="text-sm text-texto-suave">{{ __('suscripcion.resumen_pago.aviso') }}</p>

        <x-boton variante="secundario" :href="route('panel.suscripcion.planes', ['grupos' => $grupos, 'alumnos' => $alumnos, 'preguntas' => $preguntas])">
            {{ __('acciones.editar_plan') }}
        </x-boton>
    </x-tarjeta>
</x-layouts.panel>
