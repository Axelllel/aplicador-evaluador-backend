{{--
    Archivo: editar.blade.php
    Módulo: resources/views/preguntas
    Descripción: Edición de una pregunta del banco que todavía no forma parte de ningún examen
                 (Manual COD-WEB, secciones 6.16 y 6.17).
    Autor: Leonardo Ballestero López
    Creado: 24/11/2026
    Modificado: 24/11/2026 · Leonardo Ballestero López · Versión inicial
--}}
<x-layouts.panel :titulo="__('preguntas.editar.titulo')" :descripcion="__('preguntas.editar.descripcion')">
    <div class="tp-workspace-toolbar">
        <div class="tp-context"><span class="tp-context-icon"><x-icon name="question-mark-circle" /></span><span>{{ __('preguntas.editar.titulo') }} #{{ $pregunta['id'] }}</span></div>
        <x-boton variante="secundario" icono="folder-open" :href="route('panel.banco-de-preguntas')">{{ __('preguntas.editar.volver') }}</x-boton>
    </div>
    <section class="tp-composer"><div class="tp-composer-body">
        @include('preguntas._formulario', ['accion' => route('panel.banco-de-preguntas.actualizar', ['pregunta' => $pregunta['id']]), 'metodo' => 'PUT', 'valores' => $valores, 'textoGuardar' => __('preguntas.editar.guardar')])
    </div></section>
</x-layouts.panel>
