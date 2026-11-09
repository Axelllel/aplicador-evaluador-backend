{{--
    Archivo: dashboard.blade.php
    Módulo: resources/views/admin
    Descripción: Panel inicial del superadministrador: cifras de docentes y pagos, y tabla de docentes
                 de ejemplo con menú lateral de rol super_admin (Manual COD-WEB, secciones 6.16 y 6.17).
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
--}}
{{-- TODO(Leonardo Ballestero López): datos de ejemplo; sustituir por docentes y pagos reales. --}}
@php
    $tonosDeEstado = ['activo' => 'exito', 'suspendido' => 'error'];
@endphp
<x-layouts.panel
    :titulo="__('panel.inicio_administracion.titulo')"
    :descripcion="__('panel.inicio_administracion.descripcion')"
    :rol="\App\Enums\RolPanel::SuperAdmin"
>
    <section aria-labelledby="titulo-resumen-administracion" class="space-y-4">
        <h2 id="titulo-resumen-administracion" class="text-xl font-semibold text-primario">{{ __('panel.inicio_administracion.resumen') }}</h2>

        <div class="grid gap-6 md:grid-cols-3">
            @foreach (['activos', 'suspendidos', 'pagos_pendientes'] as $cifra)
                <x-tarjeta class="space-y-2">
                    <h3 class="text-base font-semibold text-primario">{{ __('panel.resumen_administracion.'.$cifra) }}</h3>
                    <p class="text-2xl font-semibold text-primario">{{ $resumen[$cifra] }}</p>
                </x-tarjeta>
            @endforeach
        </div>
    </section>

    <section aria-labelledby="titulo-docentes" class="space-y-4">
        <h2 id="titulo-docentes" class="text-xl font-semibold text-primario">{{ __('panel.inicio_administracion.docentes_registrados') }}</h2>

        <div class="space-y-4">
            <x-tabla :titulo="__('panel.tabla_docentes.titulo')" :columnas="array_values(__('panel.tabla_docentes.columnas'))">
                @foreach ($docentes as $docente)
                    <x-tabla.fila>
                        <x-tabla.celda class="font-semibold">{{ $docente['nombre'] }}</x-tabla.celda>
                        <x-tabla.celda><x-codigo>{{ $docente['correo'] }}</x-codigo></x-tabla.celda>
                        <x-tabla.celda>{{ $docente['plan'] }}</x-tabla.celda>
                        <x-tabla.celda>
                            <x-insignia :tono="$tonosDeEstado[$docente['estado']]">{{ __('panel.estados.'.$docente['estado']) }}</x-insignia>
                        </x-tabla.celda>
                        <x-tabla.celda>
                            <x-accion-tabla accion="abrir" :href="route('panel.docentes')" :contexto="$docente['nombre']" />
                        </x-tabla.celda>
                    </x-tabla.fila>
                @endforeach
            </x-tabla>

            {{ $docentes->links('pagination.panel') }}
        </div>
    </section>
</x-layouts.panel>
