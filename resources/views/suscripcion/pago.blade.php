@php
    $seleccion = ['grupos' => $grupos, 'alumnos' => $alumnos, 'preguntas' => $preguntas];
@endphp
<x-layouts.panel :titulo="__('suscripcion.resumen_pago.titulo')" :descripcion="__('suscripcion.resumen_pago.descripcion')">
    <div class="grid items-start gap-6 lg:grid-cols-3">
        <x-tarjeta class="space-y-4 lg:col-span-2">
            @isset($metodoSimulado)
                <h2 class="text-xl font-semibold text-primario">{{ __('suscripcion.resumen_pago.metodos.'.$metodoSimulado->value.'.resultado') }}</h2>
                <p class="text-sm text-texto-suave">{{ __('suscripcion.resumen_pago.metodos.'.$metodoSimulado->value.'.detalle') }}</p>

                @if ($metodoSimulado === \App\Enums\MetodoPagoSimulado::Spei)
                    <p class="text-sm text-texto-suave">{{ __('suscripcion.resumen_pago.referencia_demo') }}</p>
                    <code class="text-lg font-semibold text-primario">SPEI-DEMO</code>
                @endif

                <div>
                    <x-boton variante="secundario" :href="route('panel.suscripcion.planes.pago', $seleccion)">
                        {{ __('suscripcion.resumen_pago.elegir_otro') }}
                    </x-boton>
                </div>
            @else
                <form method="POST" action="{{ route('panel.suscripcion.planes.pago.simular') }}" id="simular-pago" class="tp-pago-formulario space-y-4">
                    @csrf
                    @foreach ($seleccion as $nombre => $valor)
                        <input type="hidden" name="{{ $nombre }}" value="{{ $valor }}">
                    @endforeach

                    <fieldset class="space-y-3" aria-describedby="ayuda-metodo-pago">
                        <legend class="text-xl font-semibold text-primario">{{ __('suscripcion.resumen_pago.seleccionar') }}</legend>
                        <p id="ayuda-metodo-pago" class="text-sm text-texto-suave">{{ __('suscripcion.resumen_pago.ayuda') }}</p>

                        @foreach ($metodos as $metodo)
                            <label class="tp-pago-opcion flex cursor-pointer items-start gap-3 rounded-lg border border-borde p-4">
                                <input type="radio" name="metodo" value="{{ $metodo->value }}" class="mt-0.5 size-4 shrink-0"
                                       @checked(old('metodo', 'tarjeta') === $metodo->value) required>
                                <span>
                                    <span class="block text-sm font-semibold text-primario">{{ __('suscripcion.resumen_pago.metodos.'.$metodo->value.'.titulo') }}</span>
                                    <span class="block text-sm font-normal text-texto-suave">{{ __('suscripcion.resumen_pago.metodos.'.$metodo->value.'.descripcion') }}</span>
                                </span>
                            </label>
                        @endforeach

                        @error('metodo')
                            <p class="text-sm text-error" role="alert">{{ $message }}</p>
                        @enderror
                    </fieldset>

                    <div class="tp-pago-tarjeta space-y-3">
                        <x-campo-texto nombre="demo_titular" :etiqueta="__('suscripcion.resumen_pago.tarjeta_demo.titular')" valor="Nombre de ejemplo" disabled />
                        <x-campo-texto nombre="demo_numero" :etiqueta="__('suscripcion.resumen_pago.tarjeta_demo.numero')" valor="•••• •••• •••• ••••" disabled />
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-campo-texto nombre="demo_vencimiento" :etiqueta="__('suscripcion.resumen_pago.tarjeta_demo.vencimiento')" valor="MM/AA" disabled />
                            <x-campo-texto nombre="demo_cvv" :etiqueta="__('suscripcion.resumen_pago.tarjeta_demo.cvv')" valor="•••" disabled />
                        </div>
                        <p class="text-sm text-texto-suave">{{ __('suscripcion.resumen_pago.tarjeta_demo.ayuda') }}</p>
                    </div>
                </form>
            @endisset
        </x-tarjeta>

        <x-tarjeta class="space-y-4">
            <h2 class="text-xl font-semibold text-primario">{{ __('suscripcion.resumen_pago.compra') }}</h2>

            <dl class="space-y-2">
                @foreach ($seleccion + ['capacidad' => $grupos * $alumnos] as $concepto => $cantidad)
                    <div class="flex justify-between gap-3">
                        <dt class="text-sm text-texto-suave">{{ __('suscripcion.cotizacion.'.$concepto) }}</dt>
                        <dd class="text-sm font-semibold text-primario">{{ $cantidad }}</dd>
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

            <div class="space-y-2">
                @empty($metodoSimulado)
                    <x-boton type="submit" form="simular-pago" ancho="completo">{{ __('suscripcion.resumen_pago.continuar') }}</x-boton>
                @endempty
                <x-boton variante="secundario" :href="route('panel.suscripcion.planes', $seleccion)" ancho="completo">
                    {{ __('acciones.editar_plan') }}
                </x-boton>
            </div>
        </x-tarjeta>
    </div>
</x-layouts.panel>
