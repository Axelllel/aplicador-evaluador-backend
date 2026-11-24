{{--
    Archivo: administracion.blade.php
    Módulo: resources/views/components/layouts
    Descripción: Espacio administrativo con navegación, búsqueda, tema y sesión independientes.
    Autor: Axel Rebollo Zeferino
    Creado: 24/11/2026
    Modificado: 24/11/2026 · Equipo BigBitByte · Rediseño administrativo
--}}
@props(['titulo', 'descripcion' => null])
@php
    $nombre = app(\App\Services\SesionPanelService::class)->nombre() ?? __('admin.administracion');
    $menu = [
        ['ruta' => 'admin.dashboard', 'icono' => 'squares-2x2', 'texto' => __('admin.resumen_general')],
        ['ruta' => 'panel.docentes', 'icono' => 'users', 'texto' => __('admin.cuentas_docentes')],
        ['ruta' => 'panel.pagos', 'icono' => 'credit-card', 'texto' => __('admin.gestion_pagos')],
    ];
@endphp
<x-layouts.base :titulo="$titulo">
    <div class="tp-shell ad-shell" data-admin-shell>
        <a href="#contenido" class="ad-skip">{{ __('panel.saltar_al_contenido') }}</a>
        <aside class="ad-sidebar" id="admin-sidebar" aria-label="{{ __('admin.navegacion') }}">
            <a class="ad-brand" href="{{ route('admin.dashboard') }}"><x-logo :alto="38" /><span>BigBit<span>Byte</span><b>.</b></span></a>
            <div class="ad-sidebar-heading"><span class="ad-overline">{{ __('admin.superadministracion') }}</span><button type="button" class="ad-icon-button ad-mobile-only" data-admin-close aria-label="{{ __('admin.cerrar_menu') }}"><x-icon name="x-mark" /></button></div>
            <nav aria-label="{{ __('admin.navegacion') }}">
                @foreach ($menu as $item)
                    <a class="ad-nav-item {{ request()->routeIs($item['ruta'], $item['ruta'].'.*') ? 'is-active' : '' }}" href="{{ route($item['ruta']) }}" @if(request()->routeIs($item['ruta'], $item['ruta'].'.*')) aria-current="page" @endif><x-icon :name="$item['icono']" /><span>{{ $item['texto'] }}</span><span class="ad-nav-indicator" aria-hidden="true"></span></a>
                @endforeach
            </nav>
            <div class="ad-sidebar-bottom">
                <div class="ad-workspace-note"><span class="ad-note-icon"><x-icon name="clipboard-document-check" /></span><strong>{{ __('admin.control_central') }}</strong><small>{{ __('admin.control_ayuda') }}</small></div>
                <a class="ad-nav-item" href="{{ route('admin.mfa') }}" @if(request()->routeIs('admin.mfa*')) aria-current="page" @endif><x-icon name="user-circle" /><span>{{ __('mfa.titulo') }}</span></a>
                <div class="ad-sidebar-user"><span class="ad-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($nombre, 0, 1)) }}</span><div><strong>{{ $nombre }}</strong><small>{{ __('admin.superadministrador') }}</small></div></div>
                <form method="POST" action="{{ route('admin.logout') }}" data-submit-message="{{ __('admin.cerrando') }}">@csrf<button type="submit" class="ad-nav-item ad-logout"><x-icon name="arrow-right-on-rectangle" /><span>{{ __('acciones.cerrar_sesion') }}</span></button></form>
            </div>
        </aside>
        <button type="button" class="ad-overlay" data-admin-close hidden aria-label="{{ __('admin.cerrar_menu') }}"></button>
        <div class="ad-workspace" id="admin-workspace">
            <header class="ad-topbar">
                <div class="ad-topbar-left"><button type="button" class="ad-icon-button ad-mobile-only" data-admin-menu aria-controls="admin-sidebar" aria-expanded="false" aria-label="{{ __('admin.abrir_menu') }}"><x-icon name="bars-3" /></button><span class="ad-breadcrumb"><a href="{{ route('admin.dashboard') }}">{{ __('admin.administracion') }}</a><span aria-hidden="true">/</span><strong>{{ $titulo }}</strong></span></div>
                <div class="ad-topbar-actions">
                    <form method="GET" action="{{ route('panel.docentes') }}" role="search" class="ad-quick-search"><x-icon name="magnifying-glass" /><label for="admin-quick-search" class="sr-only">{{ __('admin.buscar_docente') }}</label><input id="admin-quick-search" name="q" maxlength="150" placeholder="{{ __('admin.buscar_docente') }}"><button type="submit" aria-label="{{ __('admin.buscar') }}"><x-icon name="chevron-down" class="-rotate-90" /></button></form>
                    <button type="button" class="ad-icon-button" data-admin-theme aria-label="{{ __('admin.tema_oscuro') }}" data-label-dark="{{ __('admin.tema_oscuro') }}" data-label-light="{{ __('admin.tema_claro') }}"><svg data-theme-moon aria-hidden="true" viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M20.2 15.4A8.5 8.5 0 0 1 8.6 3.8 9 9 0 1 0 20.2 15.4Z" /></svg><svg data-theme-sun hidden aria-hidden="true" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"/></svg></button>
                    <span class="ad-avatar ad-topbar-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($nombre, 0, 1)) }}</span>
                </div>
            </header>
            <main class="ad-main" id="contenido" tabindex="-1">
                <x-alertas-sesion />
                <header class="ad-page-heading"><div><span class="ad-overline">{{ __('admin.operacion') }}</span><h1>{{ $titulo }}</h1>@if($descripcion)<p>{{ $descripcion }}</p>@endif</div><span class="ad-date">{{ now('America/Mexico_City')->locale('es')->translatedFormat('d M Y') }}</span></header>
                {{ $slot }}
                <footer class="ad-footer"><span>© {{ date('Y') }} BigBitByte</span><span>{{ __('admin.sesion_independiente') }}</span></footer>
            </main>
        </div>
    </div>
</x-layouts.base>
