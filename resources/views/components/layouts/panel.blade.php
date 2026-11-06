{{--
    Archivo: panel.blade.php
    Módulo: resources/views/components/layouts
    Descripción: Diseño del panel: barra superior de 64 px, barra lateral fija de 256 px con el menú
                 por rol y contenido centrado de 1280 px como máximo (Manual COD-WEB, sección 6.16).
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
--}}
<x-layouts.base :titulo="$titulo">
    <a href="#contenido" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:inline-flex focus:min-h-11 focus:items-center focus:rounded-lg focus:border focus:border-primario focus:bg-white focus:px-4 focus:font-semibold focus:text-primario focus:underline">
        {{ __('panel.saltar_al_contenido') }}
    </a>

    {{-- Barra superior: 64 px (h-16), color primario con texto blanco. --}}
    <header class="fixed inset-x-0 top-0 z-40 flex h-16 items-center justify-between gap-5 bg-primario px-5 text-white">
        <div class="flex min-w-0 items-center gap-5">
            <button
                type="button"
                data-menu-alternar
                aria-controls="menu-lateral"
                aria-expanded="false"
                title="{{ __('panel.barra_superior.abrir_menu') }}"
                class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white lg:hidden"
            >
                <x-icon name="bars-3" />
                <span class="sr-only">{{ __('panel.barra_superior.abrir_menu') }}</span>
            </button>

            {{-- Logotipo de 40 px con 20 px de margen libre a su izquierda y a su derecha. --}}
            <a
                href="{{ $rol->urlDeInicio() }}"
                title="{{ __('panel.barra_superior.ir_al_panel') }}"
                class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white"
            >
                <x-logo :alto="40" />
                <span class="sr-only">{{ __('panel.barra_superior.ir_al_panel') }}</span>
            </a>
        </div>

        <div class="relative">
            <button
                type="button"
                data-cuenta-alternar
                aria-haspopup="true"
                aria-expanded="false"
                aria-controls="menu-cuenta"
                title="{{ __('panel.barra_superior.menu_de_cuenta') }}"
                class="inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-base hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
            >
                <x-icon name="user-circle" />
                <span class="text-left">{{ $nombreDelUsuario() }}</span>
                <x-icon name="chevron-down" />
                <span class="sr-only">{{ __('panel.barra_superior.menu_de_cuenta') }}</span>
            </button>

            <div id="menu-cuenta" class="absolute right-0 top-full z-50 mt-2 hidden w-64 space-y-2 rounded-xl border border-borde bg-white p-4 text-primario">
                <p class="text-sm text-texto-suave">{{ $rol->etiqueta() }}</p>
                <form method="POST" action="{{ route('sesion.destroy') }}">
                    @csrf
                    <x-boton type="submit" variante="secundario" accion="cerrar_sesion" ancho="completo" />
                </form>
            </div>
        </div>
    </header>

    {{-- Barra lateral: 256 px (w-64), fija desde 1024 px; por debajo se abre con el botón de menú. --}}
    <aside id="menu-lateral" class="fixed bottom-0 left-0 top-16 z-30 hidden w-64 overflow-y-auto bg-primario text-white lg:block">
        <nav aria-label="{{ __('panel.menu.etiqueta') }}" class="p-4">
            <ul class="space-y-1">
                @foreach ($destinos() as $destino)
                    <li>
                        <a
                            href="{{ $destino['url'] }}"
                            @if ($destino['activo']) aria-current="page" @endif
                            class="flex min-h-11 items-center gap-3 rounded-lg border-l-4 px-3 text-base focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white {{ $destino['activo'] ? 'border-white bg-white/10 font-semibold' : 'border-transparent font-normal hover:bg-white/10' }}"
                        >
                            <x-icon :name="$destino['icono']" />
                            <span>{{ $destino['etiqueta'] }}</span>
                            @if ($destino['activo'])
                                <span class="sr-only">{{ __('panel.menu.pagina_actual') }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </aside>
    <div id="menu-fondo" data-menu-alternar class="fixed bottom-0 left-0 right-0 top-16 z-20 hidden bg-primario/60 lg:hidden"></div>

    <main id="contenido" tabindex="-1" class="pt-16 focus:outline-hidden lg:pl-64">
        <div class="mx-auto w-full max-w-[1280px] space-y-6 break-words p-6">
            <x-alertas-sesion />

            <header class="space-y-1">
                <h1 class="text-2xl font-semibold text-primario">{{ $titulo }}</h1>
                @if ($descripcion !== null)
                    <p class="text-sm text-texto-suave">{{ $descripcion }}</p>
                @endif
            </header>

            {{ $slot }}
        </div>
    </main>
</x-layouts.base>
