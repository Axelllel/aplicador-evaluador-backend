{{--
    Archivo: index.blade.php
    Módulo: resources/views/examenes
    Descripción: Página de exámenes: tabla de exámenes reales (15 filas por página, con publicar y abrir apartados)
                 y formulario de nuevo examen (grupo, nombre, fechas, orden, escala, plataforma y resultado visible)
                 (Manual COD-WEB, secciones 6.16 y 6.17).
    Autor: Leonardo Ballestero López
    Creado: 06/11/2026
    Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
--}}
<x-layouts.panel :titulo="__('examenes.titulo')" :descripcion="__('examenes.descripcion')">
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
            <x-tarjeta>
                <form method="POST" action="{{ route('panel.examenes.store') }}" novalidate class="max-w-3xl space-y-4">
                    @csrf

                    <x-campo-seleccion
                        nombre="grupo_id"
                        :etiqueta="__('examenes.nuevo.grupo')"
                        :vacio="__('examenes.nuevo.grupo_vacio')"
                        :opciones="$gruposActivos"
                    />

                    <x-campo-texto
                        nombre="nombre"
                        :etiqueta="__('examenes.nuevo.nombre')"
                        maxlength="150"
                    />

                    <div class="grid gap-4 md:grid-cols-2">
                        <x-campo-texto
                            nombre="fecha_apertura"
                            tipo="datetime-local"
                            :etiqueta="__('examenes.nuevo.fecha_apertura')"
                            :ayuda="__('examenes.nuevo.ayuda_fechas')"
                        />

                        <x-campo-texto
                            nombre="fecha_cierre"
                            tipo="datetime-local"
                            :etiqueta="__('examenes.nuevo.fecha_cierre')"
                        />
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
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

                    <div class="flex justify-end">
                        <x-boton type="submit" accion="guardar" ancho="movil" />
                    </div>
                </form>
            </x-tarjeta>
        @endif
    </section>
</x-layouts.panel>
