{{--
    Archivo: apartados.blade.php
    Módulo: resources/views/examenes
    Descripción: Apartados de un examen: avance de la ponderación, apartados con sus preguntas, formularios para
                 agregar apartados y preguntas del banco, y botón Publicar con confirmación previa
                 (Manual COD-WEB, secciones 6.16, 6.17 y 6.18).
    Autor: Leonardo Ballestero López
    Creado: 06/11/2026
    Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
--}}
@php
    $tipos = (array) __('preguntas.tipos');
    $formato = \App\Services\ExamenesPanelService::FORMATO_DE_FECHA;
    $apertura = \Carbon\CarbonImmutable::parse($detalle['fecha_apertura'])->format($formato);
    $cierre = \Carbon\CarbonImmutable::parse($detalle['fecha_cierre'])->format($formato);
    $tonosDeEstado = ['borrador' => 'neutro', 'publicado' => 'exito', 'cerrado' => 'primario'];
    $ponderacionCompleta = abs($ponderacionTotal - 100) < 0.005;
@endphp
<x-layouts.panel
    :titulo="__('examenes.apartados.titulo', ['examen' => $examen['nombre']])"
    :descripcion="__('examenes.apartados.descripcion', ['grupo' => $examen['grupo'], 'apertura' => $apertura, 'cierre' => $cierre])"
>
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3">
            <x-boton variante="secundario" icono="folder-open" :href="route('panel.examenes')">{{ __('examenes.apartados.volver') }}</x-boton>
            <x-insignia :tono="$tonosDeEstado[$examen['estado']]">{{ __('panel.estados.'.$examen['estado']) }}</x-insignia>
        </div>

        @if ($esBorrador)
            <x-boton variante="principal" accion="publicar" data-modal-abrir="confirmar-publicar-examen-{{ $examen['id'] }}" />
        @endif
    </div>

    @unless ($esBorrador)
        <x-alerta tipo="info" :autoocultar="false">{{ __('examenes.apartados.publicado_aviso') }}</x-alerta>
    @endunless

    <section aria-labelledby="titulo-ponderacion" class="space-y-2">
        <x-tarjeta class="space-y-3">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="titulo-ponderacion" class="text-xl font-semibold text-primario">{{ __('examenes.apartados.ponderacion') }}</h2>
                <p class="text-2xl font-semibold text-primario">{{ __('examenes.apartados.ponderacion_valor', ['total' => $ponderacionTotal]) }}</p>
            </div>

            <div
                role="progressbar"
                aria-label="{{ __('examenes.apartados.ponderacion') }}"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow="{{ min(100, $ponderacionTotal) }}"
                class="h-2 w-full overflow-hidden rounded-lg border border-borde bg-white"
            >
                <div class="h-full {{ $ponderacionCompleta ? 'bg-exito' : 'bg-primario' }}" style="width: {{ min(100, $ponderacionTotal) }}%"></div>
            </div>

            <p class="text-sm text-texto-suave">{{ __('examenes.apartados.ponderacion_ayuda') }}</p>
        </x-tarjeta>
    </section>

    @error('nueva_ponderacion')
        <x-alerta tipo="error">{{ $message }}</x-alerta>
    @enderror

    <section aria-labelledby="titulo-lista-de-apartados" class="space-y-4">
        <h2 id="titulo-lista-de-apartados" class="text-xl font-semibold text-primario">{{ __('examenes.apartados.lista') }}</h2>

        @forelse ($apartados as $apartado)
            @php
                $preguntasDelApartado = $apartado['preguntas'] ?? [];
            @endphp
            <x-tarjeta class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-base font-semibold text-primario">{{ $tipos[$apartado['tipo']] ?? $apartado['tipo'] }}</h3>
                    <x-insignia :tono="count($preguntasDelApartado) >= $apartado['cantidad_preguntas'] ? 'exito' : 'aviso'">
                        {{ __('examenes.apartados.avance', ['usadas' => count($preguntasDelApartado), 'cantidad' => $apartado['cantidad_preguntas']]) }}
                    </x-insignia>
                </div>

                @if ($preguntasDelApartado === [])
                    <p class="text-sm text-texto-suave">{{ __('examenes.apartados.sin_preguntas') }}</p>
                @else
                    <x-tabla :titulo="__('examenes.apartados.tabla.titulo')" :columnas="array_values(__('examenes.apartados.tabla.columnas'))">
                        @foreach ($preguntasDelApartado as $pregunta)
                            <x-tabla.fila>
                                <x-tabla.celda>{{ \Illuminate\Support\Str::limit($pregunta['enunciado'], 140) }}</x-tabla.celda>
                                <x-tabla.celda>{{ __('examenes.apartados.tiempo', ['segundos' => $pregunta['tiempo_segundos']]) }}</x-tabla.celda>
                                <x-tabla.celda>
                                    @if ($esBorrador)
                                        <form method="POST" action="{{ route('panel.examenes.preguntas.ponderacion', ['examen' => $examen['id'], 'examenPregunta' => $pregunta['examen_pregunta_id']]) }}"
                                              class="flex flex-wrap items-center gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <label for="ponderacion-{{ $pregunta['examen_pregunta_id'] }}" class="sr-only">
                                                {{ __('examenes.apartados.editar_ponderacion', ['pregunta' => \Illuminate\Support\Str::limit($pregunta['enunciado'], 80)]) }}
                                            </label>
                                            <input id="ponderacion-{{ $pregunta['examen_pregunta_id'] }}" name="nueva_ponderacion" type="number"
                                                   min="0.01" max="100" step="0.01" required value="{{ $pregunta['ponderacion'] }}"
                                                   class="h-11 w-24 rounded-lg border border-borde bg-white px-3 text-primario">
                                            <x-boton type="submit" variante="secundario">
                                                {{ __('examenes.apartados.guardar_ponderacion') }}
                                            </x-boton>
                                        </form>
                                    @else
                                        {{ rtrim(rtrim(number_format((float) $pregunta['ponderacion'], 2, '.', ''), '0'), '.') }} %
                                    @endif
                                </x-tabla.celda>
                            </x-tabla.fila>
                        @endforeach
                    </x-tabla>
                @endif
            </x-tarjeta>
        @empty
            <x-tarjeta>
                <p>{{ __('examenes.apartados.sin_apartados') }}</p>
            </x-tarjeta>
        @endforelse
    </section>

    @if ($esBorrador)
        <section aria-labelledby="titulo-nuevo-apartado" class="space-y-4">
            <h2 id="titulo-nuevo-apartado" class="text-xl font-semibold text-primario">{{ __('examenes.apartados.nuevo.encabezado') }}</h2>

            <x-tarjeta>
                <form method="POST" action="{{ route('panel.examenes.apartados.store', ['examen' => $examen['id']]) }}" novalidate class="max-w-xl space-y-4">
                    @csrf

                    <x-campo-seleccion
                        nombre="tipo"
                        :etiqueta="__('examenes.apartados.nuevo.tipo')"
                        :vacio="__('examenes.apartados.nuevo.tipo_vacio')"
                        :opciones="$tipos"
                    />

                    <x-campo-texto
                        nombre="cantidad_preguntas"
                        tipo="number"
                        :etiqueta="__('examenes.apartados.nuevo.cantidad')"
                        min="1"
                        max="200"
                        step="1"
                        inputmode="numeric"
                    />

                    <div class="flex justify-end">
                        <x-boton type="submit" accion="nuevo" ancho="movil" />
                    </div>
                </form>
            </x-tarjeta>
        </section>

        <section aria-labelledby="titulo-asignar-pregunta" class="space-y-4">
            <h2 id="titulo-asignar-pregunta" class="text-xl font-semibold text-primario">{{ __('examenes.apartados.asignar.encabezado') }}</h2>

            <x-tarjeta>
                @if ($apartadosConEspacio === [])
                    <p>{{ __('examenes.apartados.asignar.sin_espacio') }}</p>
                @elseif ($preguntasDisponibles === [])
                    <div class="space-y-3">
                        <p>{{ __('examenes.apartados.asignar.sin_preguntas') }}</p>
                        <x-boton variante="secundario" icono="question-mark-circle" :href="route('panel.banco-de-preguntas')">{{ __('panel.menu.banco_preguntas') }}</x-boton>
                    </div>
                @else
                    <form method="POST" action="{{ route('panel.examenes.preguntas.store', ['examen' => $examen['id']]) }}" novalidate class="max-w-xl space-y-4">
                        @csrf

                        <x-campo-seleccion
                            nombre="apartado_id"
                            :etiqueta="__('examenes.apartados.asignar.apartado')"
                            :vacio="__('examenes.apartados.asignar.apartado_vacio')"
                            :opciones="$apartadosConEspacio"
                        />

                        <x-campo-seleccion
                            nombre="pregunta_id"
                            :etiqueta="__('examenes.apartados.asignar.pregunta')"
                            :vacio="__('examenes.apartados.asignar.pregunta_vacia')"
                            :opciones="$preguntasDisponibles"
                            :ayuda="__('examenes.apartados.asignar.ayuda')"
                        />

                        <x-campo-texto
                            nombre="tiempo_segundos"
                            tipo="number"
                            :etiqueta="__('examenes.apartados.asignar.tiempo')"
                            min="1"
                            max="7200"
                            step="1"
                            inputmode="numeric"
                        />

                        <x-campo-texto
                            nombre="ponderacion"
                            tipo="number"
                            :etiqueta="__('examenes.apartados.asignar.ponderacion')"
                            min="0.01"
                            max="100"
                            step="0.01"
                            inputmode="decimal"
                        />

                        <div class="flex justify-end">
                            <x-boton type="submit" accion="guardar" ancho="movil" />
                        </div>
                    </form>
                @endif
            </x-tarjeta>
        </section>

        <x-confirmar
            :id="'confirmar-publicar-examen-'.$examen['id']"
            :titulo="__('mensajes.confirmar.publicar_examen.titulo')"
            :mensaje="__('mensajes.confirmar.publicar_examen.mensaje')"
            accion="publicar"
            :destino="route('panel.examenes.publicar', ['examen' => $examen['id']])"
        />
    @endif
</x-layouts.panel>
