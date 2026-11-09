{{--
    Archivo: index.blade.php
    Módulo: resources/views/grupos
    Descripción: Página de grupos: formulario de nuevo grupo y, por cada grupo, su folio y su código QR de
                 256 × 256 px con los botones Copiar folio y Descargar QR (Manual COD-WEB, secciones 6.16 y 6.17, Tabla 15).
    Autor: Leonardo Ballestero López
    Creado: 06/11/2026
    Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
--}}
<x-layouts.panel :titulo="__('grupos.titulo')" :descripcion="__('grupos.descripcion')">
    <section aria-labelledby="titulo-nuevo-grupo" class="space-y-4">
        <h2 id="titulo-nuevo-grupo" class="text-xl font-semibold text-primario">{{ __('grupos.nuevo.encabezado') }}</h2>

        <x-tarjeta>
            <form method="POST" action="{{ route('panel.grupos.store') }}" novalidate class="max-w-xl space-y-4">
                @csrf

                <x-campo-texto
                    nombre="materia"
                    :etiqueta="__('grupos.nuevo.materia')"
                    :ayuda="__('grupos.nuevo.ayuda')"
                    maxlength="150"
                />

                <div class="flex justify-end">
                    <x-boton type="submit" accion="guardar" ancho="movil" />
                </div>
            </form>
        </x-tarjeta>
    </section>

    <section aria-labelledby="titulo-lista-de-grupos" class="space-y-4">
        <h2 id="titulo-lista-de-grupos" class="text-xl font-semibold text-primario">{{ __('grupos.lista.encabezado') }}</h2>

        @if ($grupos->isEmpty())
            <x-tarjeta>
                <p>{{ __('grupos.lista.vacia') }}</p>
            </x-tarjeta>
        @else
            <div class="grid gap-6 md:grid-cols-2">
                @foreach ($grupos as $grupo)
                    <x-tarjeta class="space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-base font-semibold text-primario">{{ $grupo['materia'] }}</h3>
                            <x-insignia :tono="$grupo['activo'] ? 'exito' : 'neutro'">
                                {{ $grupo['activo'] ? __('grupos.lista.activo') : __('grupos.lista.inactivo') }}
                            </x-insignia>
                        </div>

                        <x-qr-grupo :folio="$grupo['folio_codigo']" :svg="$grupo['qr_svg']" />

                        <div class="flex flex-wrap justify-center gap-3">
                            <x-boton
                                variante="secundario"
                                accion="descargar_qr"
                                :href="$grupo['qr_descarga']"
                                download="qr-{{ $grupo['folio_codigo'] }}.svg"
                            />
                            <x-boton
                                variante="secundario"
                                accion="copiar_folio"
                                data-copiar="{{ $grupo['folio_codigo'] }}"
                                data-copiar-aviso="aviso-folio-copiado"
                            />
                        </div>
                    </x-tarjeta>
                @endforeach
            </div>

            <div class="pointer-events-none fixed inset-x-0 top-20 z-50 flex justify-center px-4">
                <x-alerta id="aviso-folio-copiado" tipo="exito" hidden class="pointer-events-auto w-full max-w-xl">{{ __('mensajes.exito.folio_copiado') }}</x-alerta>
            </div>
        @endif
    </section>
</x-layouts.panel>
