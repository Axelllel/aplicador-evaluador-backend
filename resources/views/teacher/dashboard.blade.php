{{--
    Archivo: dashboard.blade.php
    Módulo: resources/views/teacher
    Descripción: Panel inicial del docente: aviso de límite o de falta de plan, tarjetas de consumo del plan
                 contra los límites del paquete y tabla de exámenes reales (Manual COD-WEB, secciones 6.16 y 6.17).
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 06/11/2026 · Leonardo Ballestero López · Datos reales de la API y caso sin plan activo
--}}
<x-layouts.panel :titulo="__('panel.inicio.titulo')" :descripcion="__('panel.inicio.descripcion')">
    @if ($plan === null)
        <x-alerta tipo="aviso">
            {{ __('mensajes.aviso.sin_plan') }}

            <x-slot:accion>
                <x-boton variante="secundario" icono="credit-card" :href="route('panel.suscripcion')">
                    {{ __('acciones.ver_suscripcion') }}
                </x-boton>
            </x-slot:accion>
        </x-alerta>

        <section aria-labelledby="titulo-sin-plan">
            <x-tarjeta class="space-y-3">
                <h2 id="titulo-sin-plan" class="text-xl font-semibold text-primario">{{ __('panel.inicio.sin_plan_encabezado') }}</h2>
                <p>{{ __('panel.inicio.sin_plan_mensaje') }}</p>
            </x-tarjeta>
        </section>
    @else
        @foreach ($plan['avisos'] as $recursoAlcanzado)
            <x-alerta tipo="aviso">{{ __('mensajes.aviso.limite_'.$recursoAlcanzado) }}</x-alerta>
        @endforeach

        <section aria-labelledby="titulo-resumen-del-plan" class="space-y-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="titulo-resumen-del-plan" class="text-xl font-semibold text-primario">{{ __('panel.inicio.resumen_del_plan') }}</h2>
                <p class="text-sm text-texto-suave">{{ __('panel.inicio.plan_actual', ['plan' => $plan['nombre']]) }}</p>
            </div>

            <div class="grid gap-6 md:grid-cols-3">
                @foreach ($plan['recursos'] as $recurso)
                    @php
                        $nombreDelRecurso = __('panel.recursos.'.$recurso['clave']);
                        $esLimite = $recurso['estado'] === 'alcanzado';
                    @endphp
                    <x-tarjeta class="space-y-3">
                        <h3 class="text-base font-semibold text-primario">{{ $nombreDelRecurso }}</h3>

                        <p class="text-2xl font-semibold text-primario">
                            {{ __('panel.inicio.uso', ['usado' => $recurso['usado'], 'limite' => $recurso['limite']]) }}
                        </p>

                        <div
                            role="progressbar"
                            aria-label="{{ __('panel.inicio.etiqueta_del_uso', ['recurso' => $nombreDelRecurso]) }}"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-valuenow="{{ $recurso['porcentaje'] }}"
                            class="h-2 w-full overflow-hidden rounded-lg border border-borde bg-white"
                        >
                            <div class="h-full {{ $esLimite ? 'bg-aviso' : 'bg-primario' }}" style="width: {{ $recurso['porcentaje'] }}%"></div>
                        </div>

                        <p class="flex flex-wrap items-center gap-2 text-sm {{ $recurso['estado'] === 'normal' ? 'text-exito' : 'text-aviso' }}">
                            <x-icon :name="$recurso['estado'] === 'normal' ? 'check-circle' : 'exclamation-triangle'" />
                            <span class="font-semibold">{{ __('panel.inicio.estado_'.$recurso['estado']) }}</span>
                            <span class="text-texto-suave">{{ __('panel.inicio.porcentaje_usado', ['valor' => $recurso['porcentaje']]) }}</span>
                        </p>
                    </x-tarjeta>
                @endforeach
            </div>
        </section>
    @endif

    <section aria-labelledby="titulo-examenes" class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 id="titulo-examenes" class="text-xl font-semibold text-primario">{{ __('panel.inicio.examenes') }}</h2>
            <x-boton accion="nuevo" :href="route('panel.examenes').'#nuevo-examen'" />
        </div>

        @if ($examenes->total() === 0)
            <x-tarjeta>
                <p>{{ __('panel.inicio.sin_examenes') }}</p>
            </x-tarjeta>
        @else
            <x-examenes.tabla :examenes="$examenes" />
        @endif
    </section>
</x-layouts.panel>
