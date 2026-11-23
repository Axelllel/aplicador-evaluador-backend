{{--
    Archivo: apartados.blade.php
    Módulo: resources/views/examenes
    Descripción: Composición del examen: selección de preguntas, organización automática por tipo,
                 avance de ponderación y publicación con confirmación previa
                 (Manual COD-WEB, secciones 6.16, 6.17 y 6.18).
    Autor: Leonardo Ballestero López
    Creado: 06/11/2026
    Modificado: 19/11/2026 · Axel Rebollo Zeferino · Mejora de espacios de trabajo y editor estructurado
--}}
@php
    $tipos = (array) __('preguntas.tipos');
    $formato = \App\Services\ExamenesPanelService::FORMATO_DE_FECHA;
    $apertura = \Carbon\CarbonImmutable::parse($detalle['fecha_apertura'])->setTimezone(\App\Support\ReglasExamen::zonaDelDocente())->format($formato);
    $cierre = \Carbon\CarbonImmutable::parse($detalle['fecha_cierre'])->setTimezone(\App\Support\ReglasExamen::zonaDelDocente())->format($formato);
    $tonosDeEstado = ['borrador' => 'neutro', 'publicado' => 'exito', 'cerrado' => 'primario'];
    $ponderacionCompleta = abs($ponderacionTotal - 100) < 0.005;
    $textoComposicion = __('examenes.composicion');
    $cantidadTotal = $apartados->sum(fn (array $apartado): int => count($apartado['preguntas'] ?? []));
@endphp
<x-layouts.panel
    :titulo="__('examenes.apartados.titulo', ['examen' => $examen['nombre']])"
    :descripcion="__('examenes.apartados.descripcion', ['grupo' => $examen['grupo'], 'apertura' => $apertura, 'cierre' => $cierre])"
>
    <nav class="tp-workflow" aria-label="{{ __('workspace.exams.workflow') }}"><span><b><x-icon name="check" /></b>{{ __('workspace.exams.step_one') }}</span><span class="is-current"><b>2</b>{{ __('workspace.exams.step_two') }}</span><span><b>3</b>{{ __('workspace.exams.step_three') }}</span></nav>
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3">
            <x-boton variante="secundario" icono="folder-open" :href="route('panel.examenes')">{{ __('examenes.apartados.volver') }}</x-boton>
            <x-insignia :tono="$tonosDeEstado[$examen['estado']]">{{ __('panel.estados.'.$examen['estado']) }}</x-insignia>
        </div>

        @if ($esBorrador)
            <x-boton variante="principal" accion="publicar" data-modal-abrir="confirmar-publicar-examen-{{ $examen['id'] }}" :disabled="! $ponderacionCompleta || $cantidadTotal === 0" />
        @endif
    </div>

    @unless ($esBorrador)
        <x-alerta tipo="info" :autoocultar="false">{{ __('examenes.apartados.publicado_aviso') }}</x-alerta>
    @endunless

    @error('nueva_ponderacion')
        <x-alerta tipo="error">{{ $message }}</x-alerta>
    @enderror

    @if ($esBorrador)
        <div class="tp-composition-workbench">
        <section aria-labelledby="titulo-asignar-pregunta" class="space-y-4">
            <div class="tp-section-heading"><div><p class="tp-section-label">{{ __('examenes.composicion.etiqueta') }}</p><h2 id="titulo-asignar-pregunta">{{ __('examenes.apartados.asignar.encabezado') }}</h2><p>{{ __('examenes.composicion.ayuda') }}</p></div></div>
            <x-tarjeta>
                @if ($preguntasDisponibles === [])
                    <div class="space-y-3"><p>{{ __('examenes.apartados.asignar.sin_preguntas') }}</p><x-boton variante="secundario" icono="question-mark-circle" :href="route('panel.banco-de-preguntas')">{{ __('panel.menu.banco_preguntas') }}</x-boton></div>
                @else
                    <form method="POST" action="{{ route('panel.examenes.preguntas.store', ['examen' => $examen['id']]) }}" class="tp-section-form space-y-5" data-exam-composer data-current-weight="{{ $ponderacionTotal }}">
                        @csrf
                        <div class="grid gap-5 md:grid-cols-2">
                            <div><label for="filtro-tipo-pregunta">{{ __('examenes.composicion.filtro') }}</label><select id="filtro-tipo-pregunta" class="w-full px-3 py-2" data-composition-filter><option value="">{{ __('preguntas.filtro.todos') }}</option>@foreach($tipos as $tipo => $nombre)<option value="{{ $tipo }}">{{ $nombre }}</option>@endforeach</select></div>
                            <div><label for="pregunta-del-banco">{{ __('examenes.apartados.asignar.pregunta') }}</label>
                                <select id="pregunta-del-banco" name="pregunta_id" class="w-full px-3 py-2" required aria-describedby="pregunta-del-banco-ayuda @error('pregunta_id') pregunta-del-banco-error @enderror" @error('pregunta_id') aria-invalid="true" @enderror>
                                    <option value="">{{ __('examenes.apartados.asignar.pregunta_vacia') }}</option>
                                    @foreach($preguntasDisponibles as $id => $etiqueta)<option value="{{ $id }}" data-question-type="{{ $tiposDePreguntas[$id] }}" data-question-prompt="{{ $enunciadosDePreguntas[$id] }}" @selected((string) old('pregunta_id') === (string) $id)>{{ $etiqueta }}</option>@endforeach
                                </select>
                                <p id="pregunta-del-banco-ayuda" class="text-xs text-texto-suave">{{ __('examenes.composicion.tipo_automatico') }}</p>
                                @error('pregunta_id')<p id="pregunta-del-banco-error" class="tp-field-error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <p class="tp-composition-balance" data-composition-empty role="status" hidden>{{ __('examenes.composicion.sin_tipo') }}</p>
                        <p class="tp-composition-preview" data-composition-preview hidden></p>
                        <div class="grid gap-5 md:grid-cols-2">
                            <x-campo-texto nombre="tiempo_segundos" tipo="number" :etiqueta="__('examenes.apartados.asignar.tiempo')" min="1" max="7200" step="1" inputmode="numeric" required />
                            <x-campo-texto nombre="ponderacion" tipo="number" :etiqueta="__('examenes.apartados.asignar.ponderacion')" min="0.01" max="100" step="0.01" inputmode="decimal" required />
                        </div>
                        <p class="tp-composition-balance" data-composition-total aria-live="polite" aria-atomic="true"></p>
                        <div class="tp-form-actions"><p><x-icon name="information-circle" />{{ __('examenes.composicion.nota') }}</p><x-boton type="submit" icono="plus" ancho="movil">{{ __('examenes.composicion.agregar') }}</x-boton></div>
                        <script type="application/json" data-composition-text>@json($textoComposicion)</script>
                    </form>
                @endif
            </x-tarjeta>
        </section>

            <aside class="tp-composition-overview">
    <section aria-labelledby="titulo-ponderacion" class="space-y-2">
        <x-tarjeta class="tp-section-card space-y-3">
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
            <p class="tp-composition-balance">{{ $ponderacionCompleta ? __('examenes.composicion.completa') : ($ponderacionTotal > 100 ? __('examenes.composicion.exceso', ['valor' => number_format($ponderacionTotal - 100, 2)]) : __('examenes.composicion.falta', ['valor' => number_format(100 - $ponderacionTotal, 2)])) }}</p>
        </x-tarjeta>
    </section>

            </aside>
        </div>
    @else
    <section aria-labelledby="titulo-ponderacion" class="space-y-2">
        <x-tarjeta class="tp-section-card space-y-3">
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
            <p class="tp-composition-balance">{{ $ponderacionCompleta ? __('examenes.composicion.completa') : ($ponderacionTotal > 100 ? __('examenes.composicion.exceso', ['valor' => number_format($ponderacionTotal - 100, 2)]) : __('examenes.composicion.falta', ['valor' => number_format(100 - $ponderacionTotal, 2)])) }}</p>
        </x-tarjeta>
    </section>

    @endif

    <section aria-labelledby="titulo-lista-de-apartados" class="space-y-4">
        <h2 id="titulo-lista-de-apartados" class="text-xl font-semibold text-primario">{{ __('examenes.apartados.lista') }}</h2>

        @forelse ($apartados->filter(fn (array $apartado): bool => count($apartado['preguntas'] ?? []) > 0) as $apartado)
            @php
                $preguntasDelApartado = $apartado['preguntas'] ?? [];
            @endphp
            <x-tarjeta class="tp-section-card space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-base font-semibold text-primario">{{ $tipos[$apartado['tipo']] ?? $apartado['tipo'] }}</h3>
                    <x-insignia tono="neutro">{{ trans_choice('examenes.composicion.cantidad', count($preguntasDelApartado), ['count' => count($preguntasDelApartado)]) }}</x-insignia>
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
        <x-confirmar
            :id="'confirmar-publicar-examen-'.$examen['id']"
            :titulo="__('mensajes.confirmar.publicar_examen.titulo')"
            :mensaje="__('mensajes.confirmar.publicar_examen.mensaje')"
            accion="publicar"
            :destino="route('panel.examenes.publicar', ['examen' => $examen['id']])"
        />
    @endif
</x-layouts.panel>
