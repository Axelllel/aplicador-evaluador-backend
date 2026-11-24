{{--
    Archivo: pago.blade.php
    Módulo: resources/views/suscripcion
    Descripción: Vista del módulo suscripcion de BigBitByte.
    Autor: Axel Rebollo Zeferino
    Creado: 22/11/2026
    Modificado: 22/11/2026 · Equipo BigBitByte · Integración y revisión
--}}
@php
    $seleccion = ['grupos' => $grupos, 'alumnos' => $alumnos, 'preguntas' => $preguntas];
@endphp
<x-layouts.panel :titulo="__('suscripcion.resumen_pago.titulo')" :descripcion="__('suscripcion.resumen_pago.descripcion')">
    <div class="grid items-start gap-6 lg:grid-cols-3">
        <x-tarjeta class="space-y-5 lg:col-span-2">
            <div class="tp-section-heading"><div><p class="tp-section-label">{{ __('suscripcion.simulacion.etiqueta') }}</p><h2>{{ __('suscripcion.simulacion.titulo') }}</h2></div></div>
            <form method="POST" action="{{ route('panel.suscripcion.planes.pago.simular') }}" class="space-y-5" data-payment-simulation data-submit-message="{{ __('suscripcion.simulacion.procesando') }}">
                @csrf
                <input type="hidden" name="operacion_id" value="{{ old('operacion_id', $operacionId) }}">
                @foreach ($seleccion as $nombre => $valor)
                    <input type="hidden" name="{{ $nombre }}" value="{{ $valor }}">
                @endforeach
                @error('operacion_id')<p class="tp-field-error" role="alert">{{ $message }}</p>@enderror
                <fieldset class="space-y-3">
                    <legend class="font-semibold">{{ __('suscripcion.simulacion.metodo') }}</legend>
                    <div class="tp-payment-methods">
                        @foreach (['tarjeta' => 'credit-card', 'spei' => 'banknotes'] as $metodo => $icono)
                            <label class="tp-payment-method"><input type="radio" name="metodo_pago" value="{{ $metodo }}" @checked(old('metodo_pago', 'tarjeta') === $metodo) required><x-icon :name="$icono" /><span>{{ __('suscripcion.resumen_pago.metodos.'.$metodo) }}</span></label>
                        @endforeach
                    </div>
                    @error('metodo_pago')<p class="tp-field-error" role="alert">{{ $message }}</p>@enderror
                </fieldset>
                <div class="grid gap-4 md:grid-cols-2">
                    <x-campo-texto nombre="titular" :etiqueta="__('suscripcion.resumen_pago.tarjeta.titular')" :valor="$datosRevisados['titular'] ?? null" autocomplete="name" minlength="2" maxlength="150" required />
                    <x-campo-texto nombre="correo_facturacion" tipo="email" :etiqueta="__('suscripcion.resumen_pago.tarjeta.correo')" :valor="$datosRevisados['correo_facturacion'] ?? null" autocomplete="email" maxlength="254" required />
                </div>
                <fieldset class="space-y-4" data-payment-fields="tarjeta">
                    <legend class="font-semibold">{{ __('suscripcion.simulacion.datos_tarjeta') }}</legend>
                    <x-campo-texto nombre="numero_tarjeta" :valor="''" :etiqueta="__('suscripcion.simulacion.numero')" autocomplete="off" inputmode="numeric" maxlength="23" placeholder="13 a 19 dígitos" data-payment-required />
                    <div class="grid gap-4 md:grid-cols-2">
                        <x-campo-texto nombre="vencimiento" :etiqueta="__('suscripcion.simulacion.vencimiento')" autocomplete="off" inputmode="numeric" maxlength="5" placeholder="12/30" data-payment-required />
                        <x-campo-texto nombre="cvv" :valor="''" tipo="password" :etiqueta="__('suscripcion.simulacion.cvv')" autocomplete="off" inputmode="numeric" maxlength="4" placeholder="3 o 4 dígitos" data-payment-required />
                    </div>
                </fieldset>
                <fieldset class="space-y-4" data-payment-fields="spei">
                    <legend class="font-semibold">{{ __('suscripcion.simulacion.datos_spei') }}</legend>
                    <div><label for="banco-prueba">{{ __('suscripcion.simulacion.banco') }}</label><select id="banco-prueba" name="banco" class="w-full" data-payment-required><option value="">{{ __('suscripcion.simulacion.elegir_banco') }}</option>@foreach(['Banco de prueba A','Banco de prueba B'] as $banco)<option @selected(old('banco') === $banco)>{{ $banco }}</option>@endforeach</select>@error('banco')<p class="tp-field-error" role="alert">{{ $message }}</p>@enderror</div>
                    <x-campo-texto nombre="clabe" :valor="''" :etiqueta="__('suscripcion.simulacion.clabe')" autocomplete="off" inputmode="numeric" maxlength="18" placeholder="032180000118359719" data-payment-required />
                </fieldset>
                @isset($datosRevisados)<p class="tp-composition-balance" role="status">{{ __('suscripcion.resumen_pago.tarjeta.revisados') }}</p>@endisset
                <div class="tp-form-actions"><x-boton type="submit" icono="check" ancho="movil">{{ __('suscripcion.simulacion.confirmar') }}</x-boton></div>
            </form>
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
