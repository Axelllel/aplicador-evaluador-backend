{{--
    Archivo: index.blade.php
    Módulo: resources/views/resultados
    Descripción: Página de resultados: selector de examen y tabla de intentos de los alumnos (alumno, estado, fecha de
                 entrega y nota, o «Pendiente de calificar» mientras haya preguntas sin calificar) con 15 filas por
                 página y enlace al detalle de cada intento (Manual COD-WEB, secciones 6.16 y 6.17).
    Autor: Leonardo Ballestero López
    Creado: 06/11/2026
    Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
--}}
@php
    $tonosDeEstado = ['en_progreso' => 'aviso', 'finalizado' => 'exito', 'anulado' => 'neutro'];
@endphp
<x-layouts.panel :titulo="__('resultados.titulo')" :descripcion="__('resultados.descripcion')">
    @if ($opciones === [])
        <x-tarjeta class="space-y-3">
            <p>{{ __('resultados.vacio') }}</p>
            <p class="text-sm text-texto-suave">{{ __('resultados.vacio_sin_examenes') }}</p>
            <x-boton variante="secundario" icono="document-text" :href="route('panel.examenes')">{{ __('resultados.ir_a_examenes') }}</x-boton>
        </x-tarjeta>
    @else
        <section aria-labelledby="titulo-selector-de-examen" class="space-y-4">
            <h2 id="titulo-selector-de-examen" class="text-xl font-semibold text-primario">{{ __('resultados.selector.encabezado') }}</h2>

            <x-tarjeta>
                <form method="GET" action="{{ route('panel.resultados') }}" class="flex flex-col gap-4 sm:flex-row sm:items-end">
                    <div class="w-full max-w-xl">
                        <x-campo-seleccion
                            nombre="examen"
                            :etiqueta="__('resultados.selector.examen')"
                            :opciones="$opciones"
                            :valor="(string) $examen['id']"
                        />
                    </div>

                    <x-boton type="submit" variante="secundario" icono="clipboard-document-check" ancho="movil">{{ __('resultados.selector.ver') }}</x-boton>
                </form>
            </x-tarjeta>
        </section>

        <section aria-labelledby="titulo-intentos" class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h2 id="titulo-intentos" class="text-xl font-semibold text-primario">{{ __('resultados.intentos.encabezado', ['examen' => $examen['nombre']]) }}</h2>
                <x-boton variante="secundario" accion="estadisticas" :href="route('panel.estadisticas', ['examen' => $examen['id']])" />
            </div>

            @if ($intentos->total() === 0)
                <x-tarjeta class="space-y-2">
                    <p>{{ __('resultados.vacio') }}</p>
                    <p class="text-sm text-texto-suave">{{ __('resultados.vacio_sin_intentos') }}</p>
                </x-tarjeta>
            @else
                <ul class="flex flex-wrap gap-3" aria-label="{{ __('resultados.resumen.etiqueta') }}">
                    <li><x-insignia tono="primario">{{ __('resultados.resumen.intentos', ['cantidad' => $resumen['intentos']]) }}</x-insignia></li>
                    <li><x-insignia tono="exito">{{ __('resultados.resumen.calificados', ['cantidad' => $resumen['calificados']]) }}</x-insignia></li>
                    <li><x-insignia tono="aviso">{{ __('resultados.resumen.pendientes', ['cantidad' => $resumen['pendientes']]) }}</x-insignia></li>
                </ul>

                <div class="space-y-4">
                    <x-tabla :titulo="__('resultados.tabla.titulo')" :columnas="array_values(__('resultados.tabla.columnas'))">
                        @foreach ($intentos as $fila)
                            <x-tabla.fila>
                                <x-tabla.celda class="font-semibold">{{ $fila['alumno'] }}</x-tabla.celda>
                                <x-tabla.celda>
                                    <x-insignia :tono="$tonosDeEstado[$fila['estado']] ?? 'neutro'">{{ __('resultados.estados.'.$fila['estado']) }}</x-insignia>
                                </x-tabla.celda>
                                <x-tabla.celda>{{ $fila['entrega'] ?? __('resultados.sin_dato') }}</x-tabla.celda>
                                <x-tabla.celda>
                                    @if ($fila['estado_de_nota'] === 'calificada')
                                        <span class="font-semibold">{{ __('resultados.nota.valor', ['nota' => $fila['nota'], 'maximo' => $fila['escala'] === '0-10' ? 10 : 100]) }}</span>
                                    @elseif ($fila['estado_de_nota'] === 'pendiente')
                                        <x-insignia tono="aviso">{{ __('resultados.nota.pendiente') }}</x-insignia>
                                    @elseif ($fila['estado_de_nota'] === 'sin_entregar')
                                        <span class="text-texto-suave">{{ __('resultados.nota.sin_entregar') }}</span>
                                    @else
                                        <span class="text-texto-suave">{{ __('resultados.sin_dato') }}</span>
                                    @endif
                                </x-tabla.celda>
                                <x-tabla.celda>
                                    <x-accion-tabla
                                        accion="abrir"
                                        :href="route('panel.resultados.intento', ['examen' => $examen['id'], 'intento' => $fila['intento_id']])"
                                        :contexto="$fila['alumno']"
                                    />
                                </x-tabla.celda>
                            </x-tabla.fila>
                        @endforeach
                    </x-tabla>

                    {{ $intentos->links('pagination.panel') }}
                </div>
            @endif
        </section>
    @endif
</x-layouts.panel>
