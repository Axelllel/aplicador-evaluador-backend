{{--
    Archivo: ia.blade.php
    Módulo: resources/views/preguntas
    Descripción: Formulario de generación de preguntas con asistencia de IA.
    Autor: Axel Rebollo Zeferino
    Creado: 20/11/2026
    Modificado: 23/11/2026 · Leonardo Ballestero López · Cabecera de archivo
--}}
@php
    $erroresIa = $errors->getBag('ia');
    $entradaIa = old('operacion_id') !== null;
@endphp
<details id="generar-con-ia" class="tp-composer" @if($erroresIa->any() || $iaBorradores !== [] || session('ia_error')) open @endif>
    <summary class="tp-composer-summary"><span class="tp-context-icon"><x-icon name="question-mark-circle" /></span><span><strong>{{ __('ia.titulo') }}</strong><small>{{ __('ia.intro') }}</small></span><x-icon name="chevron-down" /></summary>
    <div class="tp-composer-body space-y-6">
        @if (session('ia_error'))<x-alerta tipo="error" :autoocultar="false">{{ session('ia_error') }}</x-alerta>@endif
        <form method="POST" action="{{ route('panel.ia.generar') }}" class="space-y-5" data-ai-generator data-submit-message="{{ __('ia.generando') }}">
            @csrf
            <input type="hidden" name="operacion_id" value="{{ old('operacion_id', $iaOperacion) }}">
            @if ($erroresIa->any())<div role="alert" class="tp-form-errors"><ul>@foreach ($erroresIa->all() as $mensaje)<li>{{ $mensaje }}</li>@endforeach</ul></div>@endif
            <x-campo-texto nombre="tema" id="ia-tema" :etiqueta="__('ia.tema')" :error="$erroresIa->first('tema')" :valor="$entradaIa ? old('tema') : ''" minlength="3" maxlength="200" required />
            <div class="tp-ai-settings">
                <x-campo-seleccion nombre="tipo" id="ia-tipo" :etiqueta="__('ia.tipo')" :opciones="$tipos" :valor="$entradaIa ? old('tipo') : 'opcion_multiple'" :error="$erroresIa->first('tipo')" required />
                <x-campo-seleccion nombre="nivel" id="ia-nivel" :etiqueta="__('ia.nivel')" :opciones="__('ia.niveles')" :valor="$entradaIa ? old('nivel') : 'intermedio'" :error="$erroresIa->first('nivel')" required />
                <x-campo-texto nombre="cantidad" id="ia-cantidad" tipo="number" :etiqueta="__('ia.cantidad')" :error="$erroresIa->first('cantidad')" :valor="$entradaIa ? old('cantidad') : 3" min="1" max="5" step="1" required />
            </div>
            <x-campo-area nombre="contexto" id="ia-contexto" :etiqueta="__('ia.contexto')" :ayuda="__('ia.contexto_ayuda')" :error="$erroresIa->first('contexto')" :valor="$entradaIa ? old('contexto') : ''" :filas="3" maxlength="6000" />
            <div class="flex justify-end"><x-boton type="submit" icono="question-mark-circle">{{ __('ia.generar') }}</x-boton></div>
        </form>
        @if ($iaBorradores !== [])
            <div class="space-y-4">
                <h2 class="text-xl font-semibold">{{ __('ia.borradores') }}</h2>
                <p class="tp-ai-caption">{{ __('ia.sin_guardar') }}</p>
                <div class="tp-question-grid">
                    @foreach ($iaBorradores as $indice => $borrador)
                        <article class="tp-question-card space-y-4">
                            <span class="tp-type-label">{{ $tipos[$borrador['tipo']] ?? $borrador['tipo'] }}</span>
                            <h3>{{ $borrador['enunciado'] }}</h3>
                            @if (is_array($borrador['opciones']))<ul class="list-disc pl-5">@foreach ($borrador['opciones'] as $opcion)<li>{{ is_array($opcion) ? $opcion['izquierda'].' → '.$opcion['derecha'] : $opcion }}</li>@endforeach</ul>@endif
                            <details class="tp-question-detail"><summary>{{ __('ia.respuesta_modelo') }}</summary><p class="tp-reference-answer">{{ $borrador['respuesta_modelo'] }}</p></details>
                            <form method="POST" action="{{ route('panel.ia.borrador', ['indice' => $indice]) }}">@csrf<x-boton type="submit" variante="secundario" icono="pencil-square">{{ __('ia.revisar') }}</x-boton></form>
                        </article>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</details>
