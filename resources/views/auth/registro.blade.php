{{--
    Archivo: registro.blade.php
    Módulo: resources/views/auth
    Descripción: Pantalla de registro de docentes: formulario de una columna con nombre, correo, contraseña
                 y teléfono opcional; avisa que la cuenta queda inactiva hasta que se asigne un plan
                 (Manual COD-WEB, secciones 6.14, 6.16 y 6.17).
    Autor: Leonardo Ballestero López
    Creado: 06/11/2026
    Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
--}}
<x-layouts.invitado :titulo="__('registro.titulo')">
    <x-alerta tipo="aviso">{{ __('registro.descripcion') }}</x-alerta>

    <x-tarjeta>
        <form method="POST" action="{{ route('registro.store') }}" novalidate class="space-y-4">
            @csrf

            <x-campo-texto
                nombre="nombre"
                :etiqueta="__('registro.campos.nombre')"
                autocomplete="name"
                autofocus
            />

            <x-campo-texto
                nombre="email"
                tipo="email"
                :etiqueta="__('registro.campos.email')"
                autocomplete="email"
            />

            <x-campo-texto
                nombre="password"
                tipo="password"
                :etiqueta="__('registro.campos.password')"
                :ayuda="__('registro.ayudas.password')"
                autocomplete="new-password"
            />

            <x-campo-texto
                nombre="telefono"
                tipo="tel"
                :etiqueta="__('registro.campos.telefono')"
                :ayuda="__('registro.ayudas.telefono')"
                autocomplete="tel"
            />

            {{-- Botón principal abajo a la derecha (§6.16); ancho completo solo en pantallas pequeñas (§6.17). --}}
            <div class="flex justify-end">
                <x-boton type="submit" ancho="movil">{{ __('acciones.crear_cuenta') }}</x-boton>
            </div>
        </form>
    </x-tarjeta>

    <p class="flex flex-wrap items-center gap-x-2 text-base">
        <span class="text-texto-suave">{{ __('registro.ya_tengo_cuenta') }}</span>
        <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center text-acento underline hover:no-underline">
            {{ __('registro.ir_a_iniciar_sesion') }}
        </a>
    </p>
</x-layouts.invitado>
