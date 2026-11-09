{{--
    Archivo: login.blade.php
    Módulo: resources/views/auth
    Descripción: Acceso docente con la identidad visual compartida del portal del alumno.
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 08/11/2026 · Integración visual BigBitByte sin alterar la autenticación
--}}
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1F2937">
    <title>{{ __('sesion.titulo') }} · {{ __('panel.nombre_sistema') }}</title>
    {{-- Reutiliza los mismos estilos del ingreso del alumno; sin afectar al resto del panel. --}}
    @vite(['resources/css/student-entry.css', 'resources/js/app.js'])
    <style>
        .teacher-login .card-content { padding-top: 43px; padding-bottom: 32px; }
        .teacher-login .teacher-welcome-icon svg { width: 29px; height: 29px; }
        .teacher-login .heading-block { margin-bottom: 29px; }
        .teacher-login .teacher-field + .teacher-field { margin-top: 19px; }
        .teacher-login .teacher-register { margin-top: 26px; text-align: center; font-size: 12px; color: var(--muted); }
        .teacher-login .teacher-register a { color: var(--blue); font-weight: 600; text-decoration: underline; text-underline-offset: 3px; }
        .teacher-login .teacher-register a:hover { text-decoration-thickness: 2px; }
        .teacher-login .login-notice { margin-bottom: 18px; }
        .teacher-login .login-notice-ok { padding: 12px 14px; background: var(--pale); border: 1px solid var(--line); border-radius: 9px; color: var(--ink); font-size: 12px; line-height: 1.55; }
        .teacher-login .login-dev-link { display: block; text-align: center; margin-top: 15px; color: var(--blue); text-decoration: underline; font-size: 11px; }
        .teacher-login .teacher-field .form-error { margin-bottom: 0; }
        .teacher-login .teacher-signin-button { margin-top: 26px; }
        .teacher-login .input-wrap input { letter-spacing: normal; }
        @media (max-width: 600px) {
            .teacher-login .card-content { padding-top: 35px; padding-bottom: 27px; }
            .teacher-login .teacher-register { margin-top: 22px; }
        }
    </style>
</head>
<body>
<div class="portal teacher-login" id="teacher-login">
    <div class="ambient ambient-one" aria-hidden="true"></div>
    <div class="ambient ambient-two" aria-hidden="true"></div>
    <div class="background-grid" aria-hidden="true"></div>

    <a href="#contenido" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-3 focus:text-primario">
        {{ __('panel.saltar_al_contenido') }}
    </a>

    <header class="site-header">
        <div class="brand-row">
            <x-logo :alto="42" class="brand-logo" />
            <div class="wordmark">BigBit<span>Byte</span><span class="brand-period">.</span></div>
        </div>
        <div class="header-actions">
            <span class="portal-label"><span class="status-dot" aria-hidden="true"></span>Portal del docente</span>
            <span class="header-divider" aria-hidden="true"></span>
            <button type="button" class="icon-button theme-button" id="toggle-theme" title="Cambiar tema" aria-label="Activar modo oscuro">☾</button>
        </div>
    </header>

    <main id="contenido" tabindex="-1">
        <div class="entry-layout">
            <div class="eyebrow"><span aria-hidden="true"></span>TU SIGUIENTE GRAN IDEA EMPIEZA AQUÍ</div>

            <section class="entry-card" aria-labelledby="teacher-login-title">
                <div class="card-top-line" aria-hidden="true"></div>
                <div class="card-content">
                    <div class="welcome-icon teacher-welcome-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="8" r="3.4"></circle>
                            <path d="M5.5 20v-1.5a6.5 6.5 0 0 1 13 0V20H5.5Z"></path>
                            <path d="M19 5.5h2m-1-1v2"></path>
                        </svg>
                    </div>
                    <div class="heading-block">
                        <h1 id="teacher-login-title">Bienvenido a <span>BigBitByte</span></h1>
                        <p>Inicia sesión en tu espacio docente</p>
                    </div>

                    @if (session('error'))
                        <div class="entry-message login-notice" role="alert"><strong>{{ __('mensajes.tipos.error') }}.</strong> {{ session('error') }}</div>
                    @endif
                    @if (session('aviso'))
                        <div class="entry-message login-notice" role="status"><strong>{{ __('mensajes.tipos.aviso') }}.</strong> {{ session('aviso') }}</div>
                    @endif
                    @if (session('exito'))
                        <div class="login-notice-ok login-notice" role="status"><strong>{{ __('mensajes.tipos.exito') }}.</strong> {{ session('exito') }}</div>
                    @endif
                    @if (session('info'))
                        <div class="login-notice-ok login-notice" role="status"><strong>{{ __('mensajes.tipos.info') }}.</strong> {{ session('info') }}</div>
                    @endif

                    <form method="POST" action="{{ route('sesion.store') }}" novalidate>
                        @csrf
                        <div class="teacher-field">
                            <label for="teacher-email">{{ __('sesion.campos.email') }}</label>
                            <div class="input-wrap @error('email') has-error border-error @enderror">
                                <input id="teacher-email" name="email" type="email"
                                       autocomplete="username" placeholder="nombre@ejemplo.com"
                                       maxlength="255" value="{{ old('email') }}" required autofocus
                                       @error('email') aria-invalid="true" aria-describedby="teacher-email-error" @enderror>
                            </div>
                            @error('email')
                                <p class="form-error" id="teacher-email-error" role="alert">{{ \Illuminate\Support\Str::ucfirst($message) }}</p>
                            @enderror
                        </div>

                        <div class="teacher-field">
                            <label for="teacher-password">{{ __('sesion.campos.password') }}</label>
                            <div class="input-wrap @error('password') has-error border-error @enderror">
                                <input id="teacher-password" name="password" type="password"
                                       autocomplete="current-password" placeholder="Ingresa tu contraseña"
                                       maxlength="200" required
                                       @error('password') aria-invalid="true" aria-describedby="teacher-password-error" @enderror>
                            </div>
                            @error('password')
                                <p class="form-error" id="teacher-password-error" role="alert">{{ \Illuminate\Support\Str::ucfirst($message) }}</p>
                            @enderror
                        </div>

                        <button type="submit" class="primary-button teacher-signin-button w-full">
                            {{ __('acciones.iniciar_sesion') }} <span aria-hidden="true">→</span>
                        </button>
                    </form>

                    <p class="teacher-register">{{ __('sesion.sin_cuenta') }}
                        <a href="{{ route('registro') }}">{{ __('sesion.crear_cuenta') }}</a>
                    </p>
                    @if (app()->environment('local'))
                        <a class="login-dev-link" href="{{ route('guia-estilo') }}">{{ __('sesion.demostracion.ver_guia_de_estilo') }}</a>
                    @endif
                </div>
                <div class="card-footer"><span aria-hidden="true">♢</span>Un espacio seguro para enseñar y crear.</div>
            </section>
            <div class="below-card">
                <span class="mini-line" aria-hidden="true"></span>
                <p>Pequeños pasos. <strong>Grandes posibilidades.</strong></p>
                <span class="mini-line" aria-hidden="true"></span>
            </div>
        </div>
    </main>

    <footer class="site-footer">
        <p>© {{ date('Y') }} BigBitByte <span>Aprender. Crear. Crecer.</span></p>
        <span>Portal del docente</span>
    </footer>
</div>
<script>
(() => {
    const portal = document.getElementById('teacher-login');
    const button = document.getElementById('toggle-theme');
    const applyTheme = (dark) => {
        portal.classList.toggle('dark', dark);
        button.textContent = dark ? '☀' : '☾';
        button.setAttribute('aria-label', dark ? 'Activar modo claro' : 'Activar modo oscuro');
        try { localStorage.setItem('bbb-theme', dark ? 'dark' : 'light'); } catch (_) { /* Sin almacenamiento */ }
    };
    let dark = false;
    try { dark = localStorage.getItem('bbb-theme') === 'dark'; } catch (_) { /* Sin almacenamiento */ }
    applyTheme(dark);
    button.addEventListener('click', () => applyTheme(!portal.classList.contains('dark')));
})();
</script>
</body>
</html>
