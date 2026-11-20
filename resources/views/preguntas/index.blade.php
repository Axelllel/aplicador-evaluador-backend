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
    $editorConfig = ['old' => session()->getOldInput(), 'errors' => $errors->messages(), 'types' => $tipos, 'text' => __('workspace.editor')];
@endphp
<x-layouts.panel :titulo="__('preguntas.titulo')" :descripcion="__('preguntas.descripcion')">
    <div class="tp-workspace-toolbar">
        <div class="tp-context"><span class="tp-context-icon"><x-icon name="question-mark-circle" /></span><span>{{ __('workspace.bank.context') }}</span></div>
        <x-boton icono="plus" href="#nueva-pregunta" data-open-editor>{{ __('preguntas.nueva.encabezado') }}</x-boton>
    </div>
    <section aria-label="{{ __('workspace.bank.summary') }}" class="tp-metrics">
        <x-tarjeta class="tp-metric"><span class="tp-metric-icon"><x-icon name="clipboard-document" /></span><div><p>{{ __('workspace.bank.total') }}</p><strong>{{ $preguntas->total() }}</strong><span>{{ __('workspace.bank.current_view') }}</span></div></x-tarjeta>
        <x-tarjeta class="tp-metric"><span class="tp-metric-icon"><x-icon name="squares-2x2" /></span><div><p>{{ __('workspace.bank.formats') }}</p><strong>4</strong><span>{{ __('workspace.bank.formats_hint') }}</span></div></x-tarjeta>
        <x-tarjeta class="tp-metric tp-metric-note"><span class="tp-metric-icon"><x-icon name="check-circle" /></span><div><p>{{ __('workspace.bank.reusable') }}</p><span>{{ __('workspace.bank.reusable_hint') }}</span></div></x-tarjeta>
    </section>
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
                        <div class="tp-question-footer"><span>{{ is_array($pregunta['opciones']) ? trans_choice('workspace.bank.options_count', count($pregunta['opciones']), ['count' => count($pregunta['opciones'])]) : __('workspace.bank.reference_answer') }}</span>
                            <details class="tp-question-detail"><summary>{{ __('workspace.bank.view') }}</summary><div class="tp-question-answer">
                                @if (is_array($pregunta['opciones']))<ul>@foreach ($pregunta['opciones'] as $opcion)<li>{{ is_array($opcion) ? ($opcion['izquierda'] ?? '').' → '.($opcion['derecha'] ?? '') : $opcion }}</li>@endforeach</ul>@endif
                                <p class="tp-detail-label">{{ __('preguntas.nueva.respuesta_modelo') }}</p><p class="tp-reference-answer">{{ $pregunta['respuesta_modelo'] ?? __('workspace.bank.no_reference') }}</p>
                            </div></details>
                        </div>
                    </article>
                @endforeach
            </div>
            {{ $preguntas->links('pagination.panel') }}
        @endif
    </section>
    <details id="nueva-pregunta" class="tp-composer" @if($editorAbierto) open @endif>
        <summary class="tp-composer-summary"><span class="tp-context-icon"><x-icon name="plus" /></span><span><strong>{{ __('preguntas.nueva.encabezado') }}</strong><small>{{ __('workspace.editor.intro') }}</small></span><x-icon name="chevron-down" /></summary>
        <div class="tp-composer-body">
            <form method="POST" action="{{ route('panel.banco-de-preguntas.store') }}" class="tp-question-form" data-question-editor>
                @csrf
                @if ($errors->any())<div class="tp-form-errors" role="alert" tabindex="-1" data-error-summary><strong>{{ __('workspace.editor.errors') }}</strong><ul>@foreach ($errors->all() as $mensaje)<li>{{ $mensaje }}</li>@endforeach</ul></div>@endif
                <div class="tp-editor-grid"><div class="tp-editor-fields">
                    <div class="tp-form-section"><span class="tp-step-number">01</span><div><h3>{{ __('workspace.editor.question') }}</h3><p>{{ __('workspace.editor.question_hint') }}</p></div></div>
                    <x-campo-seleccion nombre="tipo" :etiqueta="__('preguntas.nueva.tipo')" :vacio="__('preguntas.nueva.tipo_vacio')" :opciones="$tipos" required />
                    <x-campo-area nombre="enunciado" :etiqueta="__('preguntas.nueva.enunciado')" :filas="4" maxlength="10000" required :placeholder="__('workspace.editor.prompt_hint')" />
                    <div class="tp-legacy-editor" data-editor-fallback>
                        <x-campo-area nombre="opciones" :etiqueta="__('preguntas.nueva.opciones')" :ayuda="__('preguntas.nueva.ayuda_opciones')" :filas="4" maxlength="5000" />
                        <x-campo-area nombre="respuesta_modelo" :etiqueta="__('preguntas.nueva.respuesta_modelo')" :ayuda="__('preguntas.nueva.ayuda_respuesta')" :filas="3" maxlength="20000" />
                    </div>
                    <div data-editor-controls hidden></div>
                    <p class="tp-editor-status" data-editor-status role="status" aria-live="polite"></p>
                </div>
                <aside class="tp-preview" aria-labelledby="question-preview-title"><p class="tp-section-label">{{ __('workspace.editor.preview_label') }}</p><h3 id="question-preview-title">{{ __('workspace.editor.preview_title') }}</h3><p class="tp-preview-type" data-preview-type>{{ __('workspace.editor.choose_type') }}</p><p class="tp-preview-prompt" data-preview-prompt>{{ __('workspace.editor.preview_empty') }}</p><div data-preview-answers></div><p class="tp-preview-note">{{ __('workspace.editor.preview_note') }}</p></aside></div>
                <div class="tp-form-actions"><p><x-icon name="information-circle" />{{ __('workspace.editor.save_hint') }}</p><x-boton type="submit" icono="check" ancho="movil">{{ __('workspace.editor.save') }}</x-boton></div>
                <script type="application/json" data-editor-config>@json($editorConfig)</script>
            </form>
        </div>
    </details>
</x-layouts.panel>
