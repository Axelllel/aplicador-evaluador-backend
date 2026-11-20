@php
    $seleccion = ['grupos' => $grupos, 'alumnos' => $alumnos, 'preguntas' => $preguntas];
@endphp
<x-layouts.panel :titulo="__('suscripcion.resumen_pago.titulo')" :descripcion="__('suscripcion.resumen_pago.descripcion')">
    <div class="grid items-start gap-6 lg:grid-cols-3">
        <x-tarjeta class="space-y-4 lg:col-span-2">
            <h2 class="text-xl font-semibold text-primario">{{ __('suscripcion.resumen_pago.estado') }}</h2>
            <p class="text-sm text-texto-suave">{{ __('suscripcion.resumen_pago.aviso') }}</p>

            <form method="POST" action="{{ route('panel.suscripcion.planes.pago.revisar') }}" class="space-y-4">
                @csrf
                @foreach ($seleccion as $nombre => $valor)
                    <input type="hidden" name="{{ $nombre }}" value="{{ $valor }}">
                @endforeach

                <fieldset class="space-y-3" aria-describedby="ayuda-datos-tarjeta">
                    <legend class="flex items-center gap-2 text-sm font-semibold text-primario">
                        <x-icon name="credit-card" />
                        {{ __('suscripcion.resumen_pago.metodos.tarjeta') }}
                    </legend>
                    <p id="ayuda-datos-tarjeta" class="text-sm text-texto-suave">{{ __('suscripcion.resumen_pago.tarjeta.ayuda') }}</p>

                    <x-campo-texto nombre="titular" :etiqueta="__('suscripcion.resumen_pago.tarjeta.titular')"
                                   :valor="$datosRevisados['titular'] ?? null" autocomplete="billing name" minlength="2" maxlength="150" required />
                    <x-campo-texto nombre="correo_facturacion" tipo="email" :etiqueta="__('suscripcion.resumen_pago.tarjeta.correo')"
                                   :valor="$datosRevisados['correo_facturacion'] ?? null" autocomplete="billing email" maxlength="254" required />
                </fieldset>

                <x-boton type="submit">{{ __('suscripcion.resumen_pago.tarjeta.revisar') }}</x-boton>
                @isset($datosRevisados)
                    <p class="text-sm font-semibold text-exito" role="status">{{ __('suscripcion.resumen_pago.tarjeta.revisados') }}</p>
                @endisset
            </form>

            <section class="space-y-2 border-t border-borde pt-4" aria-labelledby="titulo-spei">
                <h3 id="titulo-spei" class="flex items-center gap-2 text-sm font-semibold text-primario">
                    <x-icon name="banknotes" />
                    {{ __('suscripcion.resumen_pago.metodos.spei') }}
                </h3>
                <p class="text-sm text-texto-suave">{{ __('suscripcion.resumen_pago.spei_ayuda') }}</p>
            </section>
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

            <x-boton variante="secundario" :href="route('panel.suscripcion.planes', $seleccion)" ancho="completo">
                {{ __('acciones.editar_plan') }}
            </x-boton>
        </x-tarjeta>
    </div>
</x-layouts.panel>
