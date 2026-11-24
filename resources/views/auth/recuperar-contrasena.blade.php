{{--
    Archivo: recuperar-contrasena.blade.php
    Módulo: resources/views/auth
    Descripción: Correo, confirmación con dominio visible y cambio de contraseña autorizado por el enlace.
    Autor: Axel Rebollo Zeferino
    Creado: 24/11/2026
    Modificado: 24/11/2026 · Equipo BigBitByte · Recuperación docente
--}}
@php
    $titulo = __('recuperacion.'.match($modo) {'correo' => 'correo_titulo', 'confirmar' => 'confirmar_titulo', 'enviado' => 'enviado_titulo', 'restablecer' => 'nueva_titulo', default => 'enlace_titulo'});
    $ayuda = __('recuperacion.'.match($modo) {'correo' => 'correo_ayuda', 'confirmar' => 'confirmar_ayuda', 'enviado' => 'enviado_ayuda', 'restablecer' => 'nueva_ayuda', default => 'enlace_ayuda'});
@endphp
<!doctype html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer"><title>{{ $titulo }} · BigBitByte</title>
    @vite(['resources/css/student-entry.css', 'resources/js/app.js'])
    @vite('resources/css/ui-standards.css')
</head><body>
<div class="portal recovery-page" data-recovery-root>
    <div class="ambient ambient-one" aria-hidden="true"></div><div class="ambient ambient-two" aria-hidden="true"></div><div class="background-grid" aria-hidden="true"></div>
    <header class="site-header"><div class="brand-row"><x-logo :alto="42" class="brand-logo" /><div class="wordmark">BigBit<span>Byte</span><span class="brand-period">.</span></div></div><div class="header-actions"><span class="portal-label">{{ __('sesion.perfiles.docente') }}</span><button type="button" class="icon-button theme-button" data-recovery-theme aria-label="{{ __('sesion.perfiles.cambiar_tema') }}"><span aria-hidden="true">☾</span></button></div></header>
    <main><div class="entry-layout"><section class="entry-card" aria-labelledby="recovery-title"><div class="card-top-line" aria-hidden="true"></div><div class="card-content">
        <div class="welcome-icon" aria-hidden="true"><x-icon name="user-circle" /></div><div class="heading-block"><h1 id="recovery-title">{{ $titulo }}</h1><p>{{ $ayuda }}</p></div>
        @if(session('error'))<p class="entry-message recovery-message" role="alert">{{ session('error') }}</p>@endif
        <p class="entry-message recovery-message" data-recovery-error role="alert" hidden>{{ __('recuperacion.enlace_invalido') }}</p>
        @isset($pista)<div class="group-info"><div><span class="group-kicker">{{ __('recuperacion.pista') }}</span><strong>{{ $pista }}</strong></div></div>@endisset
        @if($modo === 'correo' || $modo === 'confirmar')
            <form method="POST" action="{{ $modo === 'correo' ? route('password.prepare') : route('password.email') }}" data-submit-message="{{ __('mensajes.guardando') }}">
                @csrf
                @php($campo = $modo === 'correo' ? 'email' : 'email_confirmation')
                <div class="recovery-field"><label for="recovery-email">{{ __('recuperacion.'.($modo === 'correo' ? 'correo' : 'confirmacion')) }}</label><div class="input-wrap @error($campo) has-error @enderror"><input id="recovery-email" name="{{ $campo }}" type="email" autocomplete="{{ $modo === 'correo' ? 'email' : 'off' }}" maxlength="150" required autofocus value="{{ $modo === 'correo' ? old('email', '') : '' }}" placeholder="nombre@ejemplo.com" @error($campo) aria-invalid="true" aria-describedby="recovery-email-error" @enderror></div>@error($campo)<p id="recovery-email-error" class="form-error" role="alert">{{ $message }}</p>@enderror</div>
                <button type="submit" class="primary-button">{{ __('recuperacion.'.($modo === 'correo' ? 'continuar' : 'enviar')) }} <span aria-hidden="true">→</span></button>
            </form>
            @if($modo === 'confirmar')<a class="back-button" href="{{ route('password.request') }}">{{ __('recuperacion.cambiar_correo') }}</a>@endif
        @elseif($modo === 'enviado')
            <p class="recovery-note" role="status">{{ __('recuperacion.spam') }}</p>
        @elseif($modo === 'restablecer')
            <form method="POST" action="{{ route('password.update') }}" data-submit-message="{{ __('mensajes.guardando') }}">@csrf
                @foreach(['password', 'password_confirmation'] as $campo)
                    <div class="recovery-field"><label for="recovery-{{ $campo }}">{{ __('recuperacion.'.$campo) }}</label><div class="input-wrap @error($campo) has-error @enderror"><input id="recovery-{{ $campo }}" name="{{ $campo }}" type="password" autocomplete="new-password" minlength="10" maxlength="200" required @error($campo) aria-invalid="true" aria-describedby="recovery-{{ $campo }}-error" @enderror></div>@error($campo)<p id="recovery-{{ $campo }}-error" class="form-error" role="alert">{{ $message }}</p>@enderror</div>
                @endforeach
                <p class="field-hint">{{ __('recuperacion.password_ayuda') }}</p><button type="submit" class="primary-button">{{ __('recuperacion.guardar') }}</button>
            </form>
        @else
            <p class="recovery-note" data-recovery-progress role="status" hidden>{{ __('recuperacion.verificando') }}</p>
            <a class="secondary-button" href="{{ route('password.request') }}">{{ __('recuperacion.solicitar') }}</a>
        @endif
        <a class="back-button" href="{{ route('login') }}">← {{ __('recuperacion.volver') }}</a>
    </div><div class="card-footer">{{ __('recuperacion.seguridad') }}</div></section></div></main>
    <footer class="site-footer"><p>© {{ date('Y') }} BigBitByte</p><span>{{ __('recuperacion.correo_titulo') }}</span></footer>
</div>
<form method="POST" action="{{ route('password.verify') }}" data-recovery-bridge hidden>@csrf<input type="hidden" name="access_token" value=""></form>
</body></html>
