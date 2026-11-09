<x-layouts.panel :titulo="__('suscripcion.planes.encabezado')" :descripcion="__('suscripcion.planes.descripcion')">
    <div>
        <x-boton variante="secundario" :href="route('panel.suscripcion')">
            {{ __('acciones.volver_a_suscripcion') }}
        </x-boton>
    </div>

    <section aria-labelledby="titulo-configurador">
        <x-tarjeta class="space-y-4">
            <h2 id="titulo-configurador" class="text-xl font-semibold text-primario">{{ __('suscripcion.cotizacion.encabezado') }}</h2>

            <form method="GET" action="{{ route('panel.suscripcion.planes') }}" class="space-y-4"
                  data-configurador-plan="{{ json_encode($tarifasCentavos, JSON_THROW_ON_ERROR) }}">
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-campo-seleccion nombre="grupos" :etiqueta="__('suscripcion.cotizacion.grupos')"
                                       :opciones="array_combine($opcionesDeGrupos, $opcionesDeGrupos)" :valor="$grupos" />
                    <x-campo-seleccion nombre="alumnos" :etiqueta="__('suscripcion.cotizacion.alumnos')"
                                       :opciones="array_combine($opcionesDeAlumnos, $opcionesDeAlumnos)" :valor="$alumnos" />
                    <x-campo-seleccion nombre="preguntas" :etiqueta="__('suscripcion.cotizacion.preguntas')"
                                       :opciones="array_combine($opcionesDePreguntas, $opcionesDePreguntas)" :valor="$preguntas" />
                </div>

                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p id="etiqueta-cupo" class="text-sm text-texto-suave">{{ __('suscripcion.cotizacion.capacidad') }}</p>
                        <output for="campo-grupos campo-alumnos" aria-labelledby="etiqueta-cupo" data-cupo-plan class="text-xl font-semibold text-primario">{{ $grupos * $alumnos }}</output>
                    </div>
                    <div class="flex flex-wrap items-end gap-4">
                        <div>
                            <p id="etiqueta-precio" class="text-sm text-texto-suave">{{ __('suscripcion.cotizacion.precio') }}</p>
                            <output for="campo-grupos campo-alumnos campo-preguntas" aria-labelledby="etiqueta-precio" data-precio-plan class="text-3xl font-semibold text-primario">{{ __('suscripcion.precio', ['monto' => number_format($precioCentavos / 100, 2, '.', ',')]) }}</output>
                            <span class="text-sm text-texto-suave">{{ __('suscripcion.cotizacion.moneda') }}</span>
                        </div>
                        <x-boton type="submit" icono="credit-card" :formaction="route('panel.suscripcion.planes.pago')">
                            {{ __('acciones.continuar_al_pago') }}
                        </x-boton>
                    </div>
                </div>

                <noscript><x-boton type="submit">{{ __('suscripcion.cotizacion.calcular') }}</x-boton></noscript>
            </form>

            <p class="text-sm text-texto-suave">{{ __('suscripcion.cotizacion.leyenda') }}</p>
        </x-tarjeta>
    </section>

    <h2 class="text-xl font-semibold text-primario">{{ __('suscripcion.planes.registrados') }}</h2>

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
