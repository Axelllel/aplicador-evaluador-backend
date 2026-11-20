{{--
    Archivo: tabla.blade.php
    Módulo: resources/views/components/examenes
    Descripción: Tabla de exámenes (15 filas por página) con estado en texto, acciones con ícono, título y texto
                 alternativo, y confirmación previa antes de publicar o eliminar (Manual COD-WEB, secciones 6.17 y 6.18).
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 06/11/2026 · Leonardo Ballestero López · Resultados y estadísticas abren el examen elegido
--}}
@props(['examenes', 'idDeConfirmacion' => 'confirmar-eliminar-examen'])
@php
    // Los exámenes reales traen «id»; los de la guía de estilo no, y sus acciones apuntan a la lista.
    $destinos = [
        'abrir' => route('panel.examenes'),
        'editar' => route('panel.examenes'),
        'publicar' => route('panel.examenes'),
        'resultados' => route('panel.resultados'),
        'estadisticas' => route('panel.estadisticas'),
    ];

    $tonosDeEstado = [
        'borrador' => 'neutro',
        'publicado' => 'exito',
        'cerrado' => 'primario',
    ];

    $porEliminar = collect($examenes->items())
        ->filter(fn (array $examen): bool => in_array('eliminar', $examen['acciones'], true))
        ->unique('id');
    $porPublicar = collect($examenes->items())->filter(fn (array $examen): bool => isset($examen['id']) && in_array('publicar', $examen['acciones'], true));
@endphp
<div class="space-y-4">
    <x-tabla :titulo="__('panel.tabla_examenes.titulo')" :columnas="array_values(__('panel.tabla_examenes.columnas'))">
        @foreach ($examenes as $examen)
            <x-tabla.fila>
                <x-tabla.celda class="font-semibold">{{ $examen['nombre'] }}</x-tabla.celda>
                <x-tabla.celda>{{ $examen['grupo'] }}</x-tabla.celda>
                <x-tabla.celda>
                    <x-insignia :tono="$tonosDeEstado[$examen['estado']]">{{ __('panel.estados.'.$examen['estado']) }}</x-insignia>
                </x-tabla.celda>
                <x-tabla.celda>{{ $examen['fecha'] }}</x-tabla.celda>
                <x-tabla.celda>
                    <div class="flex gap-1">
                        @foreach ($examen['acciones'] as $accion)
                            @if ($accion === 'eliminar')
                                <x-accion-tabla accion="eliminar" :contexto="$examen['nombre']"
                                    :data-modal-abrir="isset($examen['id']) ? $idDeConfirmacion.'-'.$examen['id'] : $idDeConfirmacion" />
                            @elseif ($accion === 'publicar' && isset($examen['id']))
                                <x-accion-tabla accion="publicar" :contexto="$examen['nombre']" data-modal-abrir="confirmar-publicar-examen-{{ $examen['id'] }}" />
                            @elseif ($accion === 'abrir' && isset($examen['id']))
                                <x-accion-tabla accion="abrir" :href="route('panel.examenes.apartados', ['examen' => $examen['id']])" :contexto="$examen['nombre']" />
                            @elseif ($accion === 'resultados' && isset($examen['id']))
                                <x-accion-tabla accion="resultados" :href="route('panel.resultados', ['examen' => $examen['id']])" :contexto="$examen['nombre']" />
                            @elseif ($accion === 'estadisticas' && isset($examen['id']))
                                <x-accion-tabla accion="estadisticas" :href="route('panel.estadisticas', ['examen' => $examen['id']])" :contexto="$examen['nombre']" />
                            @else
                                <x-accion-tabla :accion="$accion" :href="$destinos[$accion]" :contexto="$examen['nombre']" />
                            @endif
                        @endforeach
                    </div>
                </x-tabla.celda>
            </x-tabla.fila>
        @endforeach
    </x-tabla>

    {{ $examenes->links('pagination.panel') }}
</div>

@foreach ($porEliminar as $examen)
    <x-confirmar
        :id="isset($examen['id']) ? $idDeConfirmacion.'-'.$examen['id'] : $idDeConfirmacion"
        :titulo="__('mensajes.confirmar.eliminar_examen.titulo')"
        :mensaje="isset($examen['id'])
            ? __('mensajes.confirmar.eliminar_examen.mensaje_con_nombre', ['examen' => $examen['nombre']])
            : __('mensajes.confirmar.eliminar_examen.mensaje')"
        :destino="isset($examen['id']) ? route('panel.examenes.eliminar', ['examen' => $examen['id']]) : null"
        metodo="DELETE"
        accion="eliminar"
        :peligro="true"
    />
@endforeach

@foreach ($porPublicar as $examen)
    <x-confirmar
        :id="'confirmar-publicar-examen-'.$examen['id']"
        :titulo="__('mensajes.confirmar.publicar_examen.titulo')"
        :mensaje="__('mensajes.confirmar.publicar_examen.mensaje')"
        accion="publicar"
        :destino="route('panel.examenes.publicar', ['examen' => $examen['id']])"
    />
@endforeach
