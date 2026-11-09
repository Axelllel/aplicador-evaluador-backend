<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('sesion.perfiles.titulo') }} · BigBitByte</title>
    @vite('resources/css/student-entry.css')
</head>
<body>
<div class="portal">
    <div class="ambient ambient-one" aria-hidden="true"></div>
    <div class="ambient ambient-two" aria-hidden="true"></div>
    <div class="background-grid" aria-hidden="true"></div>

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
</body>
</html>
