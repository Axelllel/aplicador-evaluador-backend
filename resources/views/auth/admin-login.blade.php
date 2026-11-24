{{--
    Archivo: admin-login.blade.php
    Módulo: resources/views/auth
    Descripción: Acceso administrativo con la identidad visual del docente y autenticación separada.
    Autor: Axel Rebollo Zeferino
    Creado: 23/11/2026
    Modificado: 24/11/2026 · Equipo BigBitByte · Diseño de acceso administrativo
--}}
<!doctype html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>{{ __('admin.ingreso_titulo') }} · BigBitByte</title>
    @vite(['resources/css/student-entry.css', 'resources/js/app.js'])
    @vite('resources/css/ui-standards.css')
</head><body>
<div class="portal admin-login" data-admin-login>
    <div class="ambient ambient-one" aria-hidden="true"></div><div class="ambient ambient-two" aria-hidden="true"></div><div class="background-grid" aria-hidden="true"></div>
    <a href="#contenido" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-3 focus:text-primario">{{ __('panel.saltar_al_contenido') }}</a>
    <header class="site-header"><div class="brand-row"><x-logo :alto="42" class="brand-logo" /><div class="wordmark">BigBit<span>Byte</span><span class="brand-period">.</span></div></div><div class="header-actions"><span class="portal-label"><span class="status-dot" aria-hidden="true"></span>{{ __('admin.ingreso_portal') }}</span><span class="header-divider" aria-hidden="true"></span><button type="button" class="icon-button theme-button" data-admin-theme aria-label="{{ __('admin.tema_oscuro') }}" data-label-dark="{{ __('admin.tema_oscuro') }}" data-label-light="{{ __('admin.tema_claro') }}"><svg data-theme-moon aria-hidden="true" viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M20.2 15.4A8.5 8.5 0 0 1 8.6 3.8 9 9 0 1 0 20.2 15.4Z" /></svg><svg data-theme-sun hidden aria-hidden="true" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"/></svg></button></div></header>
    <main id="contenido" tabindex="-1"><div class="entry-layout"><div class="eyebrow"><span aria-hidden="true"></span>{{ __('admin.ingreso_etiqueta') }}</div>
        <section class="entry-card" aria-labelledby="admin-login-title"><div class="card-top-line" aria-hidden="true"></div><div class="card-content">
            <div class="welcome-icon" aria-hidden="true"><x-icon name="squares-2x2" /></div><div class="heading-block"><h1 id="admin-login-title">{{ __('admin.ingreso_titulo') }}</h1><p>{{ __('admin.ingreso_subtitulo') }}</p></div>
            @foreach(['error','aviso','exito','info'] as $tipo)@if(session($tipo))<div class="entry-message login-notice" role="{{ $tipo === 'error' ? 'alert' : 'status' }}">{{ session($tipo) }}</div>@endif @endforeach
            <form method="POST" action="{{ route('admin.login.store') }}" data-submit-message="{{ __('sesion.estado_ingresando') }}" novalidate>
                @csrf
                <div class="teacher-field"><label for="admin-email">{{ __('sesion.campos.email') }}</label><div class="input-wrap @error('email') has-error border-error @enderror"><input id="admin-email" name="email" type="email" autocomplete="username" placeholder="nombre@ejemplo.com" value="{{ old('email') }}" maxlength="255" required autofocus @error('email') aria-invalid="true" aria-describedby="admin-email-error" @enderror></div>@error('email')<p class="form-error" id="admin-email-error" role="alert">{{ $message }}</p>@enderror</div>
                <div class="teacher-field"><label for="admin-password">{{ __('sesion.campos.password') }}</label><div class="input-wrap @error('password') has-error border-error @enderror"><input id="admin-password" name="password" type="password" autocomplete="current-password" required maxlength="200" @error('password') aria-invalid="true" aria-describedby="admin-password-error" @enderror></div>@error('password')<p class="form-error" id="admin-password-error" role="alert">{{ $message }}</p>@enderror</div>
                <button type="submit" class="primary-button teacher-signin-button w-full">{{ __('admin.ingreso_boton') }} <span aria-hidden="true">→</span></button>
            </form>
            <p class="admin-login-help">{{ __('admin.ingreso_ayuda') }}</p>
        </div><div class="card-footer"><x-icon name="clipboard-document-check" />{{ __('admin.ingreso_pie') }}</div></section>
        <div class="below-card"><span class="mini-line" aria-hidden="true"></span><p>BigBitByte · {{ __('admin.superadministrador') }}</p><span class="mini-line" aria-hidden="true"></span></div>
    </div></main>
    <footer class="site-footer"><p>© {{ date('Y') }} BigBitByte</p><span>{{ __('admin.ingreso_portal') }}</span></footer>
</div>
</body></html>
