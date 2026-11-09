{{--
    Archivo: intento.blade.php
    Módulo: resources/views/resultados
    Descripción: Detalle de un intento: nota actual en la escala del examen, respuestas del alumno con la respuesta
                 modelo (solo para el docente), formulario de calificación manual (PATCH, con motivo obligatorio si ya
                 había nota), botón Autocalificar opción múltiple e incidencias del intento
                 (Manual COD-WEB, secciones 6.16, 6.17 y 6.18).
    Autor: Leonardo Ballestero López
    Creado: 06/11/2026
    Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
--}}
@php
    $tipos = (array) __('preguntas.tipos');
    $maximo = $detalle['escala'] === '0-10' ? 10 : 100;
    $tonosDeEstado = ['en_progreso' => 'aviso', 'finalizado' => 'exito', 'anulado' => 'neutro'];
    $tonosDeOrigen = ['manual' => 'primario', 'automatica' => 'exito', 'sin_calificar' => 'aviso'];
    $tonosDeSeveridad = ['baja' => 'neutro', 'media' => 'aviso', 'alta' => 'error'];
@endphp
<x-layouts.panel
    :titulo="__('resultados.intento.titulo', ['alumno' => $detalle['alumno']])"
    :descripcion="__('resultados.intento.descripcion', ['examen' => $detalle['examen'], 'numero' => $detalle['numero_de_intento']])"
>
    <div class="flex flex-wrap items-center gap-3">
        <x-boton variante="secundario" icono="clipboard-document-check" :href="route('panel.resultados', ['examen' => $examenId])">{{ __('resultados.intento.volver') }}</x-boton>
    </div>

    <section aria-labelledby="titulo-resumen-del-intento" class="space-y-4">
        <h2 id="titulo-resumen-del-intento" class="text-xl font-semibold text-primario">{{ __('resultados.intento.resumen') }}</h2>

        <x-tarjeta class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="space-y-1">
                    <p class="text-sm text-texto-suave">{{ __('resultados.intento.nota_actual') }}</p>
                    @if ($detalle['estado_de_nota'] === 'calificada')
                        <p class="text-2xl font-semibold text-primario">{{ __('resultados.nota.valor', ['nota' => $detalle['nota'], 'maximo' => $maximo]) }}</p>
                    @elseif ($detalle['estado_de_nota'] === 'pendiente')
                        <x-insignia tono="aviso">{{ __('resultados.nota.pendiente') }}</x-insignia>
                    @else
                        <p class="text-base text-texto-suave">{{ __('resultados.nota.sin_entregar') }}</p>
                    @endif
                </div>

                <div class="space-y-1">
                    <p class="text-sm text-texto-suave">{{ __('resultados.intento.estado') }}</p>
                    <x-insignia :tono="$tonosDeEstado[$detalle['estado']] ?? 'neutro'">{{ __('resultados.estados.'.$detalle['estado']) }}</x-insignia>
                </div>

                <div class="space-y-1">
                    <p class="text-sm text-texto-suave">{{ __('resultados.intento.entrega') }}</p>
                    <p>{{ $detalle['entrega'] ?? __('resultados.sin_dato') }}</p>
                </div>

                <div class="space-y-1">
                    <p class="text-sm text-texto-suave">{{ __('resultados.intento.escala') }}</p>
                    <p>{{ __('examenes.opciones.escala.'.$detalle['escala']) }}</p>
                </div>
            </div>

            <p class="text-sm text-texto-suave">{{ __('resultados.intento.avance', ['calificadas' => $detalle['calificadas'], 'total' => $detalle['total_preguntas']]) }}</p>

            @if ($detalle['sin_responder'] > 0)
                <p class="text-sm text-primario">{{ trans_choice('resultados.intento.sin_responder', $detalle['sin_responder'], ['cantidad' => $detalle['sin_responder']]) }}</p>
            @endif

            @if ($detalle['puede_calificar'])
                <form method="POST" action="{{ route('panel.resultados.autocalificar', ['examen' => $examenId, 'intento' => $intentoId]) }}" class="space-y-2">
                    @csrf
                    <x-boton type="submit" variante="secundario" :disabled="! $detalle['puede_autocalificar']">{{ __('resultados.intento.autocalificar') }}</x-boton>
                    <p class="text-sm text-texto-suave">
                        {{ $detalle['puede_autocalificar'] ? __('resultados.intento.autocalificar_ayuda') : __('resultados.intento.autocalificar_sin_pendientes') }}
                    </p>
                </form>
            @else
                <p class="text-sm text-texto-suave">{{ __('resultados.intento.no_calificable') }}</p>
            @endif
        </x-tarjeta>
    </section>

    <section aria-labelledby="titulo-respuestas" class="space-y-4">
        <h2 id="titulo-respuestas" class="text-xl font-semibold text-primario">{{ __('resultados.intento.respuestas') }}</h2>

        @forelse ($detalle['respuestas'] as $respuesta)
            @php
                $bolsa = \App\Http\Requests\CalificarRespuestaRequest::PREFIJO_DE_BOLSA.$respuesta['id'];
                $conError = $errors->hasBag($bolsa);
                $erroresDeLaRespuesta = $errors->getBag($bolsa);
                $yaCalificada = $respuesta['calificacion'] !== null;
            @endphp
            <x-tarjeta id="respuesta-{{ $respuesta['id'] }}" class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-base font-semibold text-primario">{{ __('resultados.intento.pregunta', ['numero' => $respuesta['numero']]) }}</h3>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-insignia tono="neutro">{{ $tipos[$respuesta['tipo']] ?? $respuesta['tipo'] }}</x-insignia>
                        <x-insignia :tono="$tonosDeOrigen[$respuesta['origen']]">{{ __('resultados.intento.origen.'.$respuesta['origen']) }}</x-insignia>
                    </div>
                </div>

                <p class="font-semibold text-primario">{{ $respuesta['enunciado'] }}</p>

                <div class="space-y-1">
                    <p class="text-sm text-texto-suave">{{ __('resultados.intento.respuesta_alumno') }}</p>
                    <p class="whitespace-pre-line rounded-lg border border-borde bg-white p-3">{{ $respuesta['respuesta'] ?? __('resultados.intento.sin_respuesta') }}</p>
                </div>

                @if ($respuesta['modelo'] !== null)
                    <div class="space-y-1">
                        <p class="text-sm text-texto-suave">{{ __('resultados.intento.respuesta_modelo') }}</p>
                        <p class="whitespace-pre-line rounded-lg border border-borde bg-white p-3">{{ $respuesta['modelo'] }}</p>
                    </div>
                @endif

                <ul class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-primario">
                    <li>{{ __('resultados.intento.ponderacion', ['valor' => $respuesta['ponderacion']]) }}</li>
                    <li>
                        @if ($yaCalificada)
                            {{ __('resultados.intento.calificacion_actual', ['valor' => $respuesta['calificacion_texto']]) }}
                        @else
                            {{ __('resultados.intento.calificacion_ninguna') }}
                        @endif
                    </li>
                    @if ($respuesta['aporte'] !== null)
                        <li>{{ __('resultados.intento.aporte', ['valor' => $respuesta['aporte'], 'maximo' => $maximo]) }}</li>
                    @endif
                </ul>

                @if ($detalle['puede_calificar'])
                    <form
                        method="POST"
                        action="{{ route('panel.resultados.calificar', ['examen' => $examenId, 'intento' => $intentoId, 'respuesta' => $respuesta['id']]) }}"
                        novalidate
                        class="max-w-xl space-y-4 border-t border-borde pt-4"
                    >
                        @csrf
                        @method('PATCH')

                        <x-campo-texto
                            nombre="calificacion"
                            tipo="number"
                            id="calificacion-{{ $respuesta['id'] }}"
                            :etiqueta="__('resultados.intento.form.calificacion')"
                            :ayuda="__('resultados.intento.form.calificacion_ayuda')"
                            :error="$erroresDeLaRespuesta->first('calificacion')"
                            :valor="$conError ? (string) old('calificacion', '') : (string) ($respuesta['calificacion_texto'] ?? '')"
                            min="0"
                            max="100"
                            step="0.01"
                            inputmode="decimal"
                        />

                        <x-campo-area
                            nombre="motivo"
                            id="motivo-{{ $respuesta['id'] }}"
                            :etiqueta="$yaCalificada ? __('resultados.intento.form.motivo_obligatorio') : __('resultados.intento.form.motivo')"
                            :ayuda="$yaCalificada ? __('resultados.intento.form.motivo_ayuda_cambio') : __('resultados.intento.form.motivo_ayuda_nueva')"
                            :error="$erroresDeLaRespuesta->first('motivo')"
                            :valor="$conError ? (string) old('motivo', '') : ''"
                            :filas="2"
                            maxlength="2000"
                        />

                        <div class="flex justify-end">
                            <x-boton type="submit" accion="guardar" ancho="movil" aria-label="{{ __('resultados.intento.form.guardar_pregunta', ['numero' => $respuesta['numero']]) }}" />
                        </div>
                    </form>
                @endif
            </x-tarjeta>
        @empty
            <x-tarjeta>
                <p>{{ __('resultados.intento.sin_respuestas') }}</p>
            </x-tarjeta>
        @endforelse
    </section>

    <section aria-labelledby="titulo-incidencias" class="space-y-4">
        <h2 id="titulo-incidencias" class="text-xl font-semibold text-primario">{{ __('resultados.incidencias.encabezado') }}</h2>

        @if ($incidencias->total() === 0)
            <x-tarjeta>
                <p>{{ __('resultados.incidencias.vacia') }}</p>
            </x-tarjeta>
        @else
            <div class="space-y-4">
                <x-tabla :titulo="__('resultados.incidencias.tabla.titulo')" :columnas="array_values(__('resultados.incidencias.tabla.columnas'))">
                    @foreach ($incidencias as $incidencia)
                        <x-tabla.fila>
                            <x-tabla.celda>{{ __('resultados.incidencias.tipos.'.$incidencia['tipo']) }}</x-tabla.celda>
                            <x-tabla.celda>
                                <x-insignia :tono="$tonosDeSeveridad[$incidencia['severidad']] ?? 'neutro'">{{ __('resultados.incidencias.severidades.'.$incidencia['severidad']) }}</x-insignia>
                            </x-tabla.celda>
                            <x-tabla.celda>{{ $incidencia['fecha'] ?? __('resultados.sin_dato') }}</x-tabla.celda>
                        </x-tabla.fila>
                    @endforeach
                </x-tabla>

                {{ $incidencias->links('pagination.panel') }}
            </div>
        @endif
    </section>
</x-layouts.panel>
