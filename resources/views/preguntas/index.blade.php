{{--
    Archivo: index.blade.php
    Módulo: resources/views/preguntas
    Descripción: Banco de preguntas: formulario de alta de los cuatro tipos (opción múltiple, relación de columnas,
                 abierta y programación) y tabla de preguntas con 15 filas por página y filtro por tipo
                 (Manual COD-WEB, secciones 6.16 y 6.17).
    Autor: Leonardo Ballestero López
    Creado: 06/11/2026
    Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
--}}
@php
    $tipos = (array) __('preguntas.tipos');
@endphp
<x-layouts.panel :titulo="__('preguntas.titulo')" :descripcion="__('preguntas.descripcion')">
    <section aria-labelledby="titulo-nueva-pregunta" class="space-y-4">
        <h2 id="titulo-nueva-pregunta" class="text-xl font-semibold text-primario">{{ __('preguntas.nueva.encabezado') }}</h2>

        <x-tarjeta>
            <form method="POST" action="{{ route('panel.banco-de-preguntas.store') }}" novalidate class="max-w-3xl space-y-4">
                @csrf

                <x-campo-seleccion
                    nombre="tipo"
                    :etiqueta="__('preguntas.nueva.tipo')"
                    :vacio="__('preguntas.nueva.tipo_vacio')"
                    :opciones="$tipos"
                />

                <x-campo-area
                    nombre="enunciado"
                    :etiqueta="__('preguntas.nueva.enunciado')"
                    :filas="3"
                    maxlength="10000"
                />

                <x-campo-area
                    nombre="opciones"
                    :etiqueta="__('preguntas.nueva.opciones')"
                    :ayuda="__('preguntas.nueva.ayuda_opciones')"
                    :filas="4"
                    maxlength="5000"
                />

                <x-campo-area
                    nombre="respuesta_modelo"
                    :etiqueta="__('preguntas.nueva.respuesta_modelo')"
                    :ayuda="__('preguntas.nueva.ayuda_respuesta')"
                    :filas="3"
                    maxlength="20000"
                />

                <div class="flex justify-end">
                    <x-boton type="submit" accion="guardar" ancho="movil" />
                </div>
            </form>
        </x-tarjeta>
    </section>

    <section aria-labelledby="titulo-lista-de-preguntas" class="space-y-4">
        <h2 id="titulo-lista-de-preguntas" class="text-xl font-semibold text-primario">{{ __('preguntas.tabla.titulo') }}</h2>

        <form method="GET" action="{{ route('panel.banco-de-preguntas') }}" class="flex flex-wrap items-end gap-3">
            <div class="w-full max-w-xs">
                <x-campo-seleccion
                    nombre="tipo"
                    id="filtro-tipo"
                    :etiqueta="__('preguntas.filtro.etiqueta')"
                    :vacio="__('preguntas.filtro.todos')"
                    :opciones="$tipos"
                    :valor="(string) $tipoFiltrado"
                    :error="''"
                />
            </div>
            <x-boton type="submit" variante="secundario" accion="buscar" />
        </form>

        @if ($preguntas->total() === 0)
            <x-tarjeta>
                <p>{{ __('preguntas.tabla.vacia') }}</p>
            </x-tarjeta>
        @else
            <div class="space-y-4">
                <x-tabla :titulo="__('preguntas.tabla.titulo')" :columnas="array_values(__('preguntas.tabla.columnas'))">
                    @foreach ($preguntas as $pregunta)
                        <x-tabla.fila>
                            <x-tabla.celda>{{ \Illuminate\Support\Str::limit($pregunta['enunciado'], 140) }}</x-tabla.celda>
                            <x-tabla.celda>{{ $tipos[$pregunta['tipo']] ?? $pregunta['tipo'] }}</x-tabla.celda>
                            <x-tabla.celda>{{ is_array($pregunta['opciones']) ? count($pregunta['opciones']) : '—' }}</x-tabla.celda>
                        </x-tabla.fila>
                    @endforeach
                </x-tabla>

                {{ $preguntas->links('pagination.panel') }}
            </div>
        @endif
    </section>
</x-layouts.panel>
