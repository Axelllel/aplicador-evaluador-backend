{{--
    Archivo: index.blade.php
    Módulo: resources/views/examenes
    Descripción: Página de exámenes: tabla de exámenes reales (15 filas por página, con publicar y abrir apartados)
                 y formulario de nuevo examen (grupo, nombre, fechas, orden, escala, plataforma y resultado visible)
                 (Manual COD-WEB, secciones 6.16 y 6.17).
    Autor: Leonardo Ballestero López
    Creado: 06/11/2026
    Modificado: 19/11/2026 · Axel Rebollo Zeferino · Mejora de espacios de trabajo y editor estructurado
--}}
<x-layouts.panel :titulo="__('examenes.titulo')" :descripcion="__('examenes.descripcion')">
    <div class="tp-workspace-toolbar"><div class="tp-context"><span class="tp-context-icon"><x-icon name="document-text" /></span><span>{{ __('workspace.exams.context') }}</span></div><x-boton href="#nuevo-examen" icono="plus">{{ __('examenes.nuevo.encabezado') }}</x-boton></div>
    <nav class="tp-workflow" aria-label="{{ __('workspace.exams.workflow') }}"><span class="is-current"><b>1</b>{{ __('workspace.exams.step_one') }}</span><span><b>2</b>{{ __('workspace.exams.step_two') }}</span><span><b>3</b>{{ __('workspace.exams.step_three') }}</span></nav>
    <section aria-labelledby="titulo-lista-de-examenes" class="space-y-4">
        <h2 id="titulo-lista-de-examenes" class="text-xl font-semibold text-primario">{{ __('panel.tabla_examenes.titulo') }}</h2>

        @if ($examenes->total() === 0)
            <x-tarjeta>
                <p>{{ __('panel.inicio.sin_examenes') }}</p>
            </x-tarjeta>
        @else
            <x-examenes.tabla :examenes="$examenes" />
        @endif
    </section>

    <section id="nuevo-examen" aria-labelledby="titulo-nuevo-examen" class="space-y-4">
        <h2 id="titulo-nuevo-examen" class="text-xl font-semibold text-primario">{{ __('examenes.nuevo.encabezado') }}</h2>

        @if ($gruposActivos === [])
            <x-tarjeta class="space-y-3">
                <p>{{ __('examenes.nuevo.sin_grupos') }}</p>
                <x-boton variante="secundario" icono="user-group" :href="route('panel.grupos')">{{ __('examenes.nuevo.ir_a_grupos') }}</x-boton>
            </x-tarjeta>
        @else
            <x-tarjeta class="tp-exam-composer">
                <form method="POST" action="{{ route('panel.examenes.store') }}" class="tp-exam-form space-y-6" data-submit-message="{{ __('examenes.nuevo.creando') }}">
                    @csrf

                    @if ($errors->any() || session('error') || session('aviso'))
                        <div role="alert" tabindex="-1" data-error-summary class="rounded-lg border border-error bg-error/10 p-3 text-sm text-error">
                            <p class="font-semibold">{{ __('examenes.errores.resumen') }}</p>
                            <ul class="mt-1 list-disc pl-5">
                                @foreach ($errors->all() as $mensajeDeError)
                                    <li>{{ $mensajeDeError }}</li>
                                @endforeach
                                @if (session('error')) <li>{{ session('error') }}</li> @endif
                                @if (session('aviso')) <li>{{ session('aviso') }}</li> @endif
                            </ul>
                        </div>
                    @endif

                    <div class="tp-form-section"><span class="tp-step-number">01</span><div><h3>{{ __('workspace.exams.identify') }}</h3><p>{{ __('workspace.exams.identify_hint') }}</p></div></div>
                    <div class="grid gap-5 md:grid-cols-2">
                    <x-campo-seleccion
                        nombre="grupo_id"
                        :etiqueta="__('examenes.nuevo.grupo')"
                        :vacio="__('examenes.nuevo.grupo_vacio')"
                        :opciones="$gruposActivos"
                    />

                    <x-campo-texto
                        nombre="nombre"
                        :etiqueta="__('examenes.nuevo.nombre')"
                        maxlength="30"
                        :ayuda="__('examenes.nuevo.ayuda_nombre')"
                    />

                    </div>
                    <div class="tp-form-section"><span class="tp-step-number">02</span><div><h3>{{ __('workspace.exams.schedule') }}</h3><p>{{ __('workspace.exams.schedule_hint') }}</p></div></div>
                    <div class="grid gap-5 md:grid-cols-2">
                        <x-campo-texto
                            nombre="fecha_apertura"
                            tipo="datetime-local"
                            data-selector-fecha="apertura"
                            :etiqueta="__('examenes.nuevo.fecha_apertura')"
                            :ayuda="__('examenes.nuevo.ayuda_fechas')"
                        />

                        <x-campo-texto
                            nombre="fecha_cierre"
                            tipo="datetime-local"
                            data-selector-fecha="cierre"
                            :etiqueta="__('examenes.nuevo.fecha_cierre')"
                        />
                    </div>

                    <details class="tp-settings" @if($errors->any()) open @endif><summary><span><strong>{{ __('workspace.exams.settings') }}</strong><small>{{ __('workspace.exams.settings_hint') }}</small></span><x-icon name="chevron-down" /></summary>
                    <div class="grid gap-5 md:grid-cols-2">
                        <x-campo-seleccion
                            nombre="orden"
                            :etiqueta="__('examenes.nuevo.orden')"
                            :opciones="__('examenes.opciones.orden')"
                            por-defecto="aleatorio"
                        />

                        <x-campo-seleccion
                            nombre="escala"
                            :etiqueta="__('examenes.nuevo.escala')"
                            :opciones="__('examenes.opciones.escala')"
                            por-defecto="0-10"
                        />

                        <x-campo-seleccion
                            nombre="plataforma_permitida"
                            :etiqueta="__('examenes.nuevo.plataforma')"
                            :opciones="__('examenes.opciones.plataforma')"
                            por-defecto="ambas"
                        />

                        <x-campo-seleccion
                            nombre="resultado_visible"
                            :etiqueta="__('examenes.nuevo.resultado_visible')"
                            :opciones="__('examenes.opciones.resultado_visible')"
                            por-defecto="oculto"
                        />
                    </div>

                    </details>
                    <div class="tp-form-actions"><p><x-icon name="information-circle" />{{ __('workspace.exams.draft_hint') }}</p>
                        <x-boton type="submit" icono="plus" ancho="movil">{{ __('workspace.exams.create') }}</x-boton>
                    </div>
                </form>
            </x-tarjeta>
        @endif
    </section>
</x-layouts.panel>
