{{--
    Archivo: index.blade.php
    Módulo: resources/views/preguntas
    Descripción: Banco de preguntas: formulario de alta de los cuatro tipos (opción múltiple, relación de columnas,
                 abierta y programación) y tabla de preguntas con 15 filas por página y filtro por tipo
                 (Manual COD-WEB, secciones 6.16 y 6.17).
    Autor: Leonardo Ballestero López
    Creado: 06/11/2026
    Modificado: 19/11/2026 · Axel Rebollo Zeferino · Mejora de espacios de trabajo y editor estructurado
--}}
@php
    $tipos = (array) __('preguntas.tipos');
    $editorAbierto = $errors->any() || old('enunciado') !== null;
@endphp
<x-layouts.panel :titulo="__('preguntas.titulo')" :descripcion="__('preguntas.descripcion')">
    <div class="tp-workspace-toolbar">
        <div class="tp-context"><span class="tp-context-icon"><x-icon name="question-mark-circle" /></span><span>{{ __('workspace.bank.context') }}</span></div>
        <div class="flex flex-wrap gap-3"><x-boton icono="question-mark-circle" href="#generar-con-ia" data-open-ai>{{ __('ia.titulo') }}</x-boton><x-boton variante="secundario" icono="plus" href="#nueva-pregunta" data-open-editor>{{ __('preguntas.nueva.encabezado') }}</x-boton></div>
    </div>
    <section aria-label="{{ __('workspace.bank.summary') }}" class="tp-metrics">
        <x-tarjeta class="tp-metric"><span class="tp-metric-icon"><x-icon name="clipboard-document" /></span><div><p>{{ __('workspace.bank.total') }}</p><strong>{{ $preguntas->total() }}</strong><span>{{ __('workspace.bank.current_view') }}</span></div></x-tarjeta>
        <x-tarjeta class="tp-metric"><span class="tp-metric-icon"><x-icon name="squares-2x2" /></span><div><p>{{ __('workspace.bank.formats') }}</p><strong>4</strong><span>{{ __('workspace.bank.formats_hint') }}</span></div></x-tarjeta>
        <x-tarjeta class="tp-metric tp-metric-note"><span class="tp-metric-icon"><x-icon name="check-circle" /></span><div><p>{{ __('workspace.bank.reusable') }}</p><span>{{ __('workspace.bank.reusable_hint') }}</span></div></x-tarjeta>
    </section>
    @include('preguntas.ia')
    <section aria-labelledby="titulo-lista-de-preguntas" class="tp-library">
        <div class="tp-section-heading"><div><p class="tp-section-label">{{ __('workspace.bank.library') }}</p><h2 id="titulo-lista-de-preguntas">{{ __('preguntas.tabla.titulo') }}</h2><p>{{ __('workspace.bank.list_hint') }}</p></div><span class="tp-count">{{ $preguntas->total() }}</span></div>
        <form method="GET" action="{{ route('panel.banco-de-preguntas') }}" class="tp-filterbar">
            <div class="tp-search"><x-campo-texto nombre="q" id="buscar-preguntas" :etiqueta="__('workspace.bank.search')" :valor="$busqueda" :placeholder="__('workspace.bank.search_hint')" maxlength="150" /></div>
            <x-campo-seleccion nombre="tipo" id="filtro-tipo" :etiqueta="__('preguntas.filtro.etiqueta')" :vacio="__('preguntas.filtro.todos')" :opciones="$tipos" :valor="(string) $tipoFiltrado" :error="''" />
            <x-boton type="submit" variante="secundario" accion="buscar" />
            @if ($busqueda !== '' || $tipoFiltrado !== null)<a class="tp-text-link" href="{{ route('panel.banco-de-preguntas') }}">{{ __('workspace.clear') }}</a>@endif
        </form>
        @if ($preguntas->total() === 0)
            <x-tarjeta class="tp-empty-state"><span class="tp-empty-icon"><x-icon name="magnifying-glass" /></span><h3>{{ __('workspace.bank.empty_title') }}</h3><p>{{ __('workspace.bank.empty_hint') }}</p><a class="tp-text-link" href="#nueva-pregunta" data-open-editor>{{ __('preguntas.nueva.encabezado') }}</a></x-tarjeta>
        @else
            <div class="tp-question-grid">
                @foreach ($preguntas as $pregunta)
                    <article class="tp-question-card">
                        <div class="tp-question-meta"><span class="tp-type-label"><x-icon name="document-text" />{{ $tipos[$pregunta['tipo']] ?? $pregunta['tipo'] }}</span><span class="tp-question-id">#{{ $pregunta['id'] }}</span></div>
                        <h3>{{ $pregunta['enunciado'] }}</h3>
                        <div class="tp-question-footer"><span>{{ is_array($pregunta['opciones']) ? trans_choice('workspace.bank.options_count', count($pregunta['opciones']), ['count' => count($pregunta['opciones'])]) : __('workspace.bank.reference_answer') }}@if (! empty($pregunta['lenguaje'])) · {{ $pregunta['lenguaje'] }}@endif</span>
                            <details class="tp-question-detail"><summary>{{ __('workspace.bank.view') }}</summary><div class="tp-question-answer">
                                @if (is_array($pregunta['opciones']))<ul>@foreach ($pregunta['opciones'] as $opcion)<li>{{ is_array($opcion) ? ($opcion['izquierda'] ?? '').' → '.($opcion['derecha'] ?? '') : $opcion }}</li>@endforeach</ul>@endif
                                <p class="tp-detail-label">{{ __('preguntas.nueva.respuesta_modelo') }}</p><p class="tp-reference-answer">{{ $pregunta['respuesta_modelo'] ?? __('workspace.bank.no_reference') }}</p>
                            </div></details>
                        </div>
                        <div class="tp-row-actions">
                            @if (($pregunta['en_uso'] ?? false) === true)
                                <x-insignia tono="neutro">{{ __('preguntas.editar.en_uso') }}</x-insignia>
                            @else
                                <x-accion-tabla accion="editar" :href="route('panel.banco-de-preguntas.editar', ['pregunta' => $pregunta['id']])" :contexto="'#'.$pregunta['id']" />
                                <x-accion-tabla accion="eliminar" :contexto="'#'.$pregunta['id']" data-modal-abrir="confirmar-eliminar-pregunta-{{ $pregunta['id'] }}" />
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
            {{ $preguntas->links('pagination.panel') }}
            @foreach ($preguntas as $pregunta)
                @unless (($pregunta['en_uso'] ?? false) === true)
                    <x-confirmar
                        :id="'confirmar-eliminar-pregunta-'.$pregunta['id']"
                        :titulo="__('preguntas.confirmar_eliminar.titulo')"
                        :mensaje="__('preguntas.confirmar_eliminar.mensaje', ['id' => $pregunta['id']])"
                        :destino="route('panel.banco-de-preguntas.eliminar', ['pregunta' => $pregunta['id']])"
                        metodo="DELETE"
                        accion="eliminar"
                        :peligro="true"
                    />
                @endunless
            @endforeach
        @endif
    </section>
    <details id="nueva-pregunta" class="tp-composer" @if($editorAbierto) open @endif>
        <summary class="tp-composer-summary"><span class="tp-context-icon"><x-icon name="plus" /></span><span><strong>{{ __('preguntas.nueva.encabezado') }}</strong><small>{{ __('workspace.editor.intro') }}</small></span><x-icon name="chevron-down" /></summary>
        <div class="tp-composer-body">
            @include('preguntas._formulario', ['accion' => route('panel.banco-de-preguntas.store'), 'metodo' => 'POST', 'valores' => $valores])
        </div>
    </details>
</x-layouts.panel>
