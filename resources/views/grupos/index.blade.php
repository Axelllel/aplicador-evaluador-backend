{{--
    Archivo: index.blade.php
    Módulo: resources/views/grupos
    Descripción: Gestión real de grupos con estilo visual compartido con el portal del alumno.
    Autor: Leonardo Ballestero López
    Creado: 06/11/2026
    Modificado: 08/11/2026 · Adaptación visual sin cambiar formulario, QR ni endpoints
--}}
<x-layouts.panel :titulo="__('grupos.titulo')" :descripcion="__('grupos.descripcion')">
    <div class="tp-groups">
        <section class="tp-feature" aria-label="Organización de grupos">
            <div class="tp-feature-content">
                <p class="tp-feature-label"><span aria-hidden="true"></span>TU ESPACIO PARA ENSEÑAR</p>
                <h2>Organiza tu clase.<br>Conecta con tu grupo.</h2>
                <p>Comparte el folio o el código QR para que tus estudiantes puedan ingresar.</p>
            </div>
            <div class="tp-feature-art" aria-hidden="true">
                <span class="tp-orbit tp-orbit-one"></span>
                <span class="tp-orbit tp-orbit-two"></span>
                <span class="tp-art-card">▤<span>Grupo</span><b>✓</b></span>
            </div>
        </section>

        <section aria-labelledby="titulo-nuevo-grupo" class="tp-group-section space-y-4">
            <div class="tp-section-heading">
                <div>
                    <p class="tp-section-label">GESTIÓN ACADÉMICA</p>
                    <h2 id="titulo-nuevo-grupo">{{ __('grupos.nuevo.encabezado') }}</h2>
                    <p>Asigna una materia y obtén un folio para compartir.</p>
                </div>
            </div>

            <x-tarjeta class="tp-group-card">
                <form method="POST" action="{{ route('panel.grupos.store') }}" novalidate class="max-w-xl space-y-4">
                    @csrf
                    <x-campo-texto
                        nombre="materia"
                        :etiqueta="__('grupos.nuevo.materia')"
                        :ayuda="__('grupos.nuevo.ayuda')"
                        maxlength="150"
                    />
                    <div class="flex justify-end">
                        <x-boton type="submit" accion="guardar" ancho="movil" class="tp-primary-action" />
                    </div>
                </form>
            </x-tarjeta>
        </section>

        <section aria-labelledby="titulo-lista-de-grupos" class="tp-group-section space-y-4">
            <div class="tp-section-heading">
                <div>
                    <p class="tp-section-label">TUS RECURSOS</p>
                    <h2 id="titulo-lista-de-grupos">{{ __('grupos.lista.encabezado') }}</h2>
                    <p>Consulta y comparte los grupos que ya creaste.</p>
                </div>
            </div>

            @if ($grupos->isEmpty())
                <x-tarjeta class="tp-group-card tp-empty-card">
                    <div class="tp-empty-icon" aria-hidden="true">▦</div>
                    <p>{{ __('grupos.lista.vacia') }}</p>
                </x-tarjeta>
            @else
                <div class="grid gap-6 md:grid-cols-2">
                    @foreach ($grupos as $grupo)
                        <x-tarjeta class="tp-group-card space-y-4">
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
                                    class="tp-secondary-action"
                                />
                                <x-boton
                                    variante="secundario"
                                    accion="copiar_folio"
                                    data-copiar="{{ $grupo['folio_codigo'] }}"
                                    data-copiar-aviso="aviso-folio-copiado"
                                    class="tp-secondary-action"
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
    </div>
</x-layouts.panel>
