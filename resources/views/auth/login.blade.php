{{--
    Archivo: login.blade.php
    Módulo: resources/views/auth
    Descripción: Pantalla de inicio de sesión: logotipo de 96 px centrado, formulario de una columna
                 con correo y contraseña, botón principal abajo a la derecha y enlace al registro
                 (Manual COD-WEB, secciones 6.14, 6.16 y 6.17).
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 06/11/2026 · Leonardo Ballestero López · Sin maqueta: acceso real y enlace a registro
--}}
<x-layouts.invitado :titulo="__('sesion.titulo')">
    <x-tarjeta>
        <form method="POST" action="{{ route('sesion.store') }}" novalidate class="space-y-4">
            @csrf

            <x-campo-texto
                nombre="email"
                tipo="email"
                :etiqueta="__('sesion.campos.email')"
                autocomplete="username"
                autofocus
            />

            <x-campo-texto
                nombre="password"
                tipo="password"
                :etiqueta="__('sesion.campos.password')"
                autocomplete="current-password"
            />

            {{-- Botón principal abajo a la derecha (§6.16); ancho completo solo en pantallas pequeñas (§6.17). --}}
            <div class="flex justify-end">
                <x-boton type="submit" ancho="movil">{{ __('acciones.iniciar_sesion') }}</x-boton>
            </div>
        </form>
    </x-tarjeta>

    <p class="flex flex-wrap items-center gap-x-2 text-base">
        <span class="text-texto-suave">{{ __('sesion.sin_cuenta') }}</span>
        <a href="{{ route('registro') }}" class="inline-flex min-h-11 items-center text-acento underline hover:no-underline">
            {{ __('sesion.crear_cuenta') }}
        </a>
    </p>

    {{-- La guía de estilo es una herramienta de diseño: solo se enlaza en desarrollo. --}}
    @if (app()->environment('local'))
        <a href="{{ route('guia-estilo') }}" class="inline-flex min-h-11 items-center text-base text-acento underline hover:no-underline">
            {{ __('sesion.demostracion.ver_guia_de_estilo') }}
        </a>
    @endif
</x-layouts.invitado>
