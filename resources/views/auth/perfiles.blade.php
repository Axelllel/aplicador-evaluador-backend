<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('sesion.perfiles.titulo') }} · BigBitByte</title>
    @vite('resources/css/student-entry.css')
</head>
<body>
<div class="portal" id="profile-entry">
    <div class="ambient ambient-one" aria-hidden="true"></div>
    <div class="ambient ambient-two" aria-hidden="true"></div>
    <div class="background-grid" aria-hidden="true"></div>

    <button type="button" id="toggle-theme" class="icon-button theme-button absolute top-5 right-5 z-10"
            title="{{ __('sesion.perfiles.cambiar_tema') }}"
            aria-label="{{ __('sesion.perfiles.activar_oscuro') }}"
            data-activar-claro="{{ __('sesion.perfiles.activar_claro') }}"
            data-activar-oscuro="{{ __('sesion.perfiles.activar_oscuro') }}" hidden>☾</button>

    <main>
        <section class="entry-card w-full" aria-labelledby="titulo-perfil">
            <div class="card-top-line" aria-hidden="true"></div>
            <div class="card-content">
                <div class="brand-row mb-5 justify-center">
                    <x-logo :alto="42" class="brand-logo" />
                    <p class="wordmark">BigBit<span>Byte</span><span class="brand-period">.</span></p>
                </div>

                <div class="heading-block">
                    <h1 id="titulo-perfil">{{ __('sesion.perfiles.titulo') }}</h1>
                    <p>{{ __('sesion.perfiles.descripcion') }}</p>
                </div>

                <nav class="grid gap-3 sm:grid-cols-2" aria-labelledby="titulo-perfil">
                    <a class="secondary-button focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-blue-600"
                       href="{{ route('login') }}">
                        <x-icon name="user-circle" />
                        <span class="text-left">
                            <span class="block text-sm font-semibold">{{ __('sesion.perfiles.docente') }}</span>
                            <span class="block font-normal">{{ __('sesion.perfiles.docente_ayuda') }}</span>
                        </span>
                    </a>
                    <a class="secondary-button focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-blue-600"
                       href="{{ route('student.entry') }}">
                        <x-icon name="user-group" />
                        <span class="text-left">
                            <span class="block text-sm font-semibold">{{ __('sesion.perfiles.alumno') }}</span>
                            <span class="block font-normal">{{ __('sesion.perfiles.alumno_ayuda') }}</span>
                        </span>
                    </a>
                </nav>
            </div>
        </section>
    </main>
</div>
<script>
(() => {
    const portal = document.getElementById('profile-entry');
    const boton = document.getElementById('toggle-theme');

    const aplicarTema = (oscuro) => {
        portal.classList.toggle('dark', oscuro);
        boton.textContent = oscuro ? '☀' : '☾';
        boton.setAttribute('aria-label', oscuro ? boton.dataset.activarClaro : boton.dataset.activarOscuro);
    };

    let oscuro = false;
    try { oscuro = localStorage.getItem('bbb-theme') === 'dark'; } catch (_) { /* Almacenamiento opcional. */ }
    aplicarTema(oscuro);
    boton.hidden = false;

    boton.addEventListener('click', () => {
        oscuro = !oscuro;
        aplicarTema(oscuro);
        try { localStorage.setItem('bbb-theme', oscuro ? 'dark' : 'light'); } catch (_) { /* Almacenamiento opcional. */ }
    });
})();
</script>
</body>
</html>
