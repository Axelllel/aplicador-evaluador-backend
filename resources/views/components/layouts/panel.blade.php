{{--
    Archivo: panel.blade.php
    Módulo: resources/views/components/layouts
    Descripción: Encabezado y barra lateral del docente unificados con la identidad visual del alumno.
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 08/11/2026 · Ajuste visual BigBitByte sin cambios de rutas ni lógica
--}}
<x-layouts.base :titulo="$titulo">
    <div class="tp-shell" id="teacher-panel">
        <a href="#contenido" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-3 focus:text-primario">
            {{ __('panel.saltar_al_contenido') }}
        </a>

        <header class="tp-header">
            <div class="tp-brand-area">
                <button type="button" data-menu-alternar aria-controls="menu-lateral" aria-expanded="false"
                        title="{{ __('panel.menu.etiqueta') }}" class="tp-icon-button tp-menu-button">
                    <x-icon name="bars-3" />
                    <span class="sr-only">{{ __('panel.menu.etiqueta') }}</span>
                </button>
                <a href="{{ $rol->urlDeInicio() }}" class="tp-brand" title="{{ __('panel.barra_superior.ir_al_panel') }}">
                    <x-logo :alto="35" />
                    <span class="tp-wordmark">BigBit<span>Byte</span><b>.</b></span>
                </a>
            </div>

            <div class="tp-header-actions">
                <span class="tp-portal-tag"><span class="tp-status-dot" aria-hidden="true"></span>Portal del docente</span>
                <span class="tp-header-divider" aria-hidden="true"></span>
                <button type="button" class="tp-icon-button tp-theme-button" id="tp-theme-toggle"
                        aria-label="Activar modo oscuro" title="Cambiar tema">☾</button>
                <div class="tp-account">
                    <button type="button" data-cuenta-alternar aria-haspopup="true" aria-expanded="false"
                            aria-controls="menu-cuenta" title="{{ __('panel.barra_superior.menu_de_cuenta') }}"
                            class="tp-account-toggle">
                        <span class="tp-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($nombreDelUsuario(), 0, 1)) }}</span>
                        <span class="tp-account-name">{{ $nombreDelUsuario() }}</span>
                        <x-icon name="chevron-down" />
                        <span class="sr-only">{{ __('panel.barra_superior.menu_de_cuenta') }}</span>
                    </button>
                    <div id="menu-cuenta" class="tp-account-menu hidden">
                        <p class="tp-account-role">{{ $rol->etiqueta() }}</p>
                        <form method="POST" action="{{ route('sesion.destroy') }}">
                            @csrf
                            <x-boton type="submit" variante="secundario" accion="cerrar_sesion" ancho="completo" />
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <aside id="menu-lateral" class="tp-sidebar fixed bottom-0 left-0 z-30 overflow-y-auto" inert>
            <div class="tp-sidebar-caption">MI ESPACIO DOCENTE</div>
            <nav aria-label="{{ __('panel.menu.etiqueta') }}">
                <ul class="tp-nav-list">
                    @foreach ($destinos() as $destino)
                        <li>
                            <a href="{{ $destino['url'] }}"
                               @if ($destino['activo']) aria-current="page" @endif
                               class="tp-nav-item {{ $destino['activo'] ? 'tp-nav-active font-semibold' : '' }}">
                                <x-icon :name="$destino['icono']" />
                                <span>{{ $destino['etiqueta'] }}</span>
                                @if ($destino['activo'])
                                    <span class="sr-only">{{ __('panel.menu.pagina_actual') }}</span>
                                    <span class="tp-nav-dot" aria-hidden="true"></span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
            <div class="tp-sidebar-footer">BigBitByte · {{ date('Y') }}</div>
        </aside>
        <div id="menu-fondo" data-menu-alternar class="tp-menu-overlay hidden lg:hidden"></div>

        <main id="contenido" tabindex="-1" class="tp-main focus:outline-hidden">
            <div class="tp-container">
                <x-alertas-sesion />
                <header class="tp-page-heading">
                    <p class="tp-page-kicker">TU ESPACIO ACADÉMICO</p>
                    <h1>{{ $titulo }}<span>.</span></h1>
                    @if ($descripcion !== null)
                        <p class="tp-page-description">{{ $descripcion }}</p>
                    @endif
                </header>
                {{ $slot }}
            </div>
        </main>
    </div>

    <script>
    (() => {
        const panel = document.getElementById('teacher-panel');
        const button = document.getElementById('tp-theme-toggle');
        if (!panel || !button) return;
        const aplicar = (oscuro) => {
            panel.classList.toggle('tp-dark', oscuro);
            button.textContent = oscuro ? '☀' : '☾';
            button.setAttribute('aria-label', oscuro ? 'Activar modo claro' : 'Activar modo oscuro');
            try { localStorage.setItem('bbb-theme', oscuro ? 'dark' : 'light'); }
            catch (_) { /* La navegación funciona sin almacenamiento local. */ }
        };
        let oscuro = false;
        try { oscuro = localStorage.getItem('bbb-theme') === 'dark'; }
        catch (_) { /* Se usa modo claro por defecto. */ }
        aplicar(oscuro);
        button.addEventListener('click', () => aplicar(!panel.classList.contains('tp-dark')));
    })();
    </script>
</x-layouts.base>
