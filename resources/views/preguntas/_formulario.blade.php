{{--
    Archivo: _formulario.blade.php
    Módulo: resources/views/preguntas
    Descripción: Formulario del editor de preguntas, compartido por el alta (banco) y la edición de una pregunta.
                 Recibe la ruta de envío, el método y los valores iniciales (Manual COD-WEB, secciones 6.16 y 6.17).
    Autor: Leonardo Ballestero López
    Creado: 24/11/2026
    Modificado: 24/11/2026 · Leonardo Ballestero López · Versión inicial
--}}
@php
    /** @var array<string, mixed> $valores */
    $tipos = (array) __('preguntas.tipos');
    $v = fn (string $campo) => old($campo, $valores[$campo] ?? null);
    $editorConfig = ['old' => $valores, 'errors' => $errors->messages(), 'types' => $tipos, 'text' => __('workspace.editor')];
    $lenguajes = array_combine(\App\Support\ReglasPregunta::LENGUAJES, \App\Support\ReglasPregunta::LENGUAJES)
        + ['otro' => __('preguntas.nueva.lenguaje_otro'), 'libre' => __('preguntas.nueva.lenguaje_libre')];
    $lineasDeOpciones = old('opciones') ?? (isset($valores['opciones_lista'])
        ? implode("\n", $valores['opciones_lista'])
        : (isset($valores['pares_lista']) ? implode("\n", array_map(fn (array $par): string => $par['izquierda'].' | '.$par['derecha'], $valores['pares_lista'])) : null));
@endphp
<form method="POST" action="{{ $accion }}" class="tp-question-form" data-question-editor>
    @csrf
    @if (($metodo ?? 'POST') !== 'POST')
        @method($metodo)
    @endif
    @if ($errors->any())<div class="tp-form-errors" role="alert" tabindex="-1" data-error-summary><strong>{{ __('workspace.editor.errors') }}</strong><ul>@foreach ($errors->all() as $mensaje)<li>{{ \Illuminate\Support\Str::ucfirst($mensaje) }}</li>@endforeach</ul></div>@endif
    <div class="tp-editor-grid"><div class="tp-editor-fields">
        <div class="tp-form-section"><span class="tp-step-number">01</span><div><h3>{{ __('workspace.editor.question') }}</h3><p>{{ __('workspace.editor.question_hint') }}</p></div></div>
        <x-campo-seleccion nombre="tipo" :etiqueta="__('preguntas.nueva.tipo')" :vacio="__('preguntas.nueva.tipo_vacio')" :opciones="$tipos" :valor="(string) $v('tipo')" required />
        <x-campo-area nombre="enunciado" :etiqueta="__('preguntas.nueva.enunciado')" :filas="4" maxlength="10000" required :valor="$v('enunciado')" :placeholder="__('workspace.editor.prompt_hint')" />
        <div class="grid gap-5 md:grid-cols-2" data-language-field>
            <x-campo-seleccion nombre="lenguaje" :etiqueta="__('preguntas.nueva.lenguaje')" :vacio="__('preguntas.nueva.lenguaje_vacio')" :opciones="$lenguajes" :valor="(string) $v('lenguaje')" :ayuda="__('preguntas.nueva.lenguaje_ayuda')" />
            <div data-language-other>
                <x-campo-texto nombre="lenguaje_otro" :etiqueta="__('preguntas.nueva.lenguaje_nombre')" :ayuda="__('preguntas.nueva.lenguaje_otro_ayuda')" maxlength="40" :valor="$v('lenguaje_otro')" />
            </div>
        </div>
        <div class="tp-legacy-editor" data-editor-fallback>
            <x-campo-area nombre="opciones" :etiqueta="__('preguntas.nueva.opciones')" :ayuda="__('preguntas.nueva.ayuda_opciones')" :filas="4" maxlength="5000" :valor="$lineasDeOpciones" />
            <x-campo-area nombre="respuesta_modelo" :etiqueta="__('preguntas.nueva.respuesta_modelo')" :ayuda="__('preguntas.nueva.ayuda_respuesta')" :filas="3" maxlength="20000" :valor="$v('respuesta_modelo')" />
        </div>
        <div data-editor-controls hidden></div>
        <p class="tp-editor-status" data-editor-status role="status" aria-live="polite"></p>
    </div>
    <aside class="tp-preview" aria-labelledby="question-preview-title"><p class="tp-section-label">{{ __('workspace.editor.preview_label') }}</p><h3 id="question-preview-title">{{ __('workspace.editor.preview_title') }}</h3><p class="tp-preview-type" data-preview-type>{{ __('workspace.editor.choose_type') }}</p><p class="tp-preview-prompt" data-preview-prompt>{{ __('workspace.editor.preview_empty') }}</p><div data-preview-answers></div><p class="tp-preview-note">{{ __('workspace.editor.preview_note') }}</p></aside></div>
    <div class="tp-form-actions"><p><x-icon name="information-circle" />{{ __('workspace.editor.save_hint') }}</p><x-boton type="submit" icono="check" ancho="movil">{{ $textoGuardar ?? __('workspace.editor.save') }}</x-boton></div>
    <script type="application/json" data-editor-config>@json($editorConfig)</script>
</form>
