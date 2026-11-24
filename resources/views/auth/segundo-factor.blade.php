{{--
    Archivo: segundo-factor.blade.php
    Módulo: resources/views/auth
    Descripción: Alta, verificación y retiro del segundo factor mediante Supabase Auth.
    Autor: Axel Rebollo Zeferino
    Creado: 22/11/2026
    Modificado: 22/11/2026 · Equipo BigBitByte · Autenticación
--}}
<x-layouts.panel :titulo="__('mfa.titulo')" :descripcion="__('mfa.descripcion')">
    <div class="mx-auto max-w-2xl space-y-6">
        @if ($datos['alta_telefono_habilitada'])
            <x-tarjeta>
                <form method="POST" action="{{ route('panel.mfa.enroll') }}" class="space-y-4">
                    @csrf
                    <label for="mfa-phone" class="block font-semibold">{{ __('mfa.telefono') }}</label>
                    <input id="mfa-phone" class="w-full rounded-lg border border-borde p-3" name="telefono" type="tel" autocomplete="tel" required pattern="\+[1-9][0-9]{7,14}" placeholder="+52…">
                    <p class="text-sm text-texto-suave">{{ __('mfa.telefono_ayuda') }}</p>
                    <x-boton type="submit">{{ __('mfa.agregar') }}</x-boton>
                </form>
            </x-tarjeta>
        @else
            <x-alerta tipo="info">{{ __('mfa.configuracion_pendiente') }}</x-alerta>
        @endif
        @foreach ($datos['factores'] as $factor)
            <x-tarjeta class="space-y-4">
                <p class="font-semibold">{{ $factor['tipo'] === 'phone' ? __('mfa.telefono') : __('mfa.autenticador') }} @if ($factor['telefono_final']) · •••• {{ $factor['telefono_final'] }} @endif</p>
                <p>{{ $factor['estado'] === 'verified' ? __('mfa.activo') : __('mfa.sin_verificar') }}</p>
                <form method="POST" action="{{ route('panel.mfa.challenge') }}">
                    @csrf<input type="hidden" name="factor" value="{{ $factor['id'] }}">
                    <x-boton type="submit">{{ __('mfa.solicitar') }}</x-boton>
                </form>
                @if ($datos['nivel'] === 'aal2')
                    <form method="POST" action="{{ route('panel.mfa.destroy') }}">
                        @csrf @method('DELETE')<input type="hidden" name="factor" value="{{ $factor['id'] }}">
                        <x-boton type="submit" variante="peligro">{{ __('mfa.retirar') }}</x-boton>
                    </form>
                @endif
            </x-tarjeta>
        @endforeach
        @if (session()->has('mfa_challenge'))
            <x-tarjeta>
                <form method="POST" action="{{ route('panel.mfa.verify') }}" class="space-y-4">
                    @csrf<label for="mfa-code" class="block font-semibold">{{ __('mfa.codigo') }}</label>
                    <input class="w-full rounded-lg border border-borde p-3" id="mfa-code" name="codigo" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]{6,10}" required>
                    <x-boton type="submit">{{ __('mfa.verificar') }}</x-boton>
                </form>
            </x-tarjeta>
        @endif
    </div>
</x-layouts.panel>
