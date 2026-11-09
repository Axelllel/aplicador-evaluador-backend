{{--
    Archivo: index.blade.php
    Módulo: resources/views/suscripcion
    Descripción: Suscripción del docente en solo lectura: plan vigente, enlace al catálogo y tabla de pagos
                 con 15 filas por página (Manual COD-WEB, secciones 6.16 y 6.17).
    Autor: Leonardo Ballestero López
    Creado: 06/11/2026
    Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
--}}
@php
    $formatoDeFecha = 'd/m/Y';
    $dinero = fn ($monto): string => __('suscripcion.precio', ['monto' => number_format((float) $monto, 2, '.', ',')]);
    $tonosDeEstado = ['confirmado' => 'exito', 'pendiente' => 'aviso', 'rechazado' => 'error', 'cancelado' => 'error'];
@endphp
<x-layouts.panel :titulo="__('suscripcion.titulo')" :descripcion="__('suscripcion.descripcion')">
    @if ($vigente === null)
        <x-alerta tipo="aviso">{{ __('mensajes.aviso.sin_plan') }}</x-alerta>
    @endif

    <section aria-labelledby="titulo-tu-plan" class="space-y-4">
        <h2 id="titulo-tu-plan" class="text-xl font-semibold text-primario">{{ __('suscripcion.vigente.encabezado') }}</h2>

        <x-tarjeta class="space-y-2">
            @if ($vigente === null)
                <p class="font-semibold">{{ __('suscripcion.vigente.sin_plan') }}</p>
                <p class="text-sm text-texto-suave">{{ __('suscripcion.vigente.sin_plan_ayuda') }}</p>
            @else
                <p class="text-2xl font-semibold text-primario">{{ $vigente['plan']['nombre'] }}</p>
                <p class="text-sm text-texto-suave">
                    {{ __('suscripcion.vigente.vigente_hasta', ['fecha' => \Carbon\CarbonImmutable::parse($vigente['pago']['fecha_fin_vigencia'])->format($formatoDeFecha)]) }}
                </p>
            @endif

            <x-boton variante="secundario" :href="route('panel.suscripcion.planes')">{{ __('acciones.ver_planes') }}</x-boton>
        </x-tarjeta>
    </section>

    <section aria-labelledby="titulo-pagos" class="space-y-4">
        <h2 id="titulo-pagos" class="text-xl font-semibold text-primario">{{ __('suscripcion.pagos.encabezado') }}</h2>

        @if ($pagos->total() === 0)
            <x-tarjeta>
                <p>{{ __('suscripcion.pagos.vacia') }}</p>
            </x-tarjeta>
        @else
            <div class="space-y-4">
                <x-tabla :titulo="__('suscripcion.pagos.titulo')" :columnas="array_values(__('suscripcion.pagos.columnas'))">
                    @foreach ($pagos as $pago)
                        <x-tabla.fila>
                            <x-tabla.celda>{{ \Carbon\CarbonImmutable::parse($pago['fecha'])->format($formatoDeFecha) }}</x-tabla.celda>
                            <x-tabla.celda>{{ $nombresDePlanes[$pago['paquete_id']] ?? '—' }}</x-tabla.celda>
                            <x-tabla.celda>{{ $dinero($pago['monto']) }}</x-tabla.celda>
                            <x-tabla.celda>
                                <x-insignia :tono="$tonosDeEstado[$pago['estado']] ?? 'neutro'">
                                    {{ \Illuminate\Support\Facades\Lang::has('suscripcion.pagos.estados.'.$pago['estado']) ? __('suscripcion.pagos.estados.'.$pago['estado']) : $pago['estado'] }}
                                </x-insignia>
                            </x-tabla.celda>
                            <x-tabla.celda>
                                {{ __('suscripcion.pagos.vigencia', [
                                    'inicio' => \Carbon\CarbonImmutable::parse($pago['fecha_inicio_vigencia'])->format($formatoDeFecha),
                                    'fin' => \Carbon\CarbonImmutable::parse($pago['fecha_fin_vigencia'])->format($formatoDeFecha),
                                ]) }}
                            </x-tabla.celda>
                        </x-tabla.fila>
                    @endforeach
                </x-tabla>

                {{ $pagos->links('pagination.panel') }}
            </div>
        @endif
    </section>
</x-layouts.panel>
