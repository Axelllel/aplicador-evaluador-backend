{{--
    Archivo: panel.blade.php
    Módulo: resources/views/components/layouts
    Descripción: Encabezado y barra lateral del docente unificados con la identidad visual del alumno.
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 19/11/2026 · Axel Rebollo Zeferino · Mejora de espacios de trabajo y editor estructurado
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
                <span class="tp-portal-tag"><span class="tp-status-dot" aria-hidden="true"></span>{{ $rol === \App\Enums\RolPanel::SuperAdmin ? 'Administración del sistema' : 'Portal del docente' }}</span>
                <span class="tp-header-divider" aria-hidden="true"></span>
                <button type="button" class="tp-icon-button tp-theme-button" id="tp-theme-toggle"
                        aria-label="Activar modo oscuro" title="Cambiar tema"><svg data-theme-moon xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M20.2 15.4A8.5 8.5 0 0 1 8.6 3.8 9 9 0 1 0 20.2 15.4Z" /></svg><svg data-theme-sun hidden xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><circle cx="12" cy="12" r="4" /><path d="M11 1h2v3h-2zm0 19h2v3h-2zM1 11h3v2H1zm19 0h3v2h-3zM3.5 5l1.4-1.5L7 5.6 5.6 7zM17 18.4l1.4-1.4 2.1 2.1-1.4 1.4zM3.5 19l2.1-2.1L7 18.3l-2.1 2.1zM17 5.6l2.1-2.1L20.5 5l-2.1 2.1z" /></svg></button>
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
                        <a href="{{ route(\App\Services\SesionPanelService::rutaMfa()) }}" class="block py-3">{{ __('mfa.titulo') }}</a>
                        <form method="POST" action="{{ route(\App\Services\SesionPanelService::esAdministracion() ? 'admin.logout' : 'sesion.destroy') }}">
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
        const teacherShell = document.getElementById('teacher-panel');
        const button = document.getElementById('tp-theme-toggle');
        if (!teacherShell || !button) return;
        const aplicar = (oscuro) => {
            teacherShell.classList.toggle('tp-dark', oscuro);
            button.querySelector('[data-theme-moon]').hidden = oscuro;
            button.querySelector('[data-theme-sun]').hidden = !oscuro;
            button.setAttribute('aria-label', oscuro ? 'Activar modo claro' : 'Activar modo oscuro');
            try { localStorage.setItem('bbb-theme', oscuro ? 'dark' : 'light'); }
            catch (_) { /* La navegación funciona sin almacenamiento local. */ }
        };
        let oscuro = false;
        try { oscuro = localStorage.getItem('bbb-theme') === 'dark'; }
        catch (_) { /* Se usa modo claro por defecto. */ }
        aplicar(oscuro);
        button.addEventListener('click', () => aplicar(!teacherShell.classList.contains('tp-dark')));
    })();
    </script>
</x-layouts.base>
