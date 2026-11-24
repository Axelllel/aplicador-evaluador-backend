{{--
    Archivo: admin-login.blade.php
    Módulo: resources/views/auth
    Descripción: Página independiente de acceso al portal de superadministración.
    Autor: Axel Rebollo Zeferino
    Creado: 23/11/2026
    Modificado: 23/11/2026 · Equipo BigBitByte · Acceso administrativo separado
--}}
<x-layouts.base titulo="Ingreso de superadministrador">
    <main class="min-h-screen flex items-center justify-center bg-fondo px-6 py-12">
        <section class="w-full max-w-md space-y-6 rounded-xl border border-borde bg-superficie p-6" aria-labelledby="admin-login-title">
            <x-logo class="h-12 w-12" />
            <div class="space-y-2">
                <h1 id="admin-login-title" class="text-2xl font-semibold">Administración del sistema</h1>
                <p>Acceso exclusivo para cuentas autorizadas de superadministración.</p>
            </div>
            @foreach (['error','aviso','exito'] as $tipo)
                @if (session($tipo))
                    <p role="{{ $tipo === 'error' ? 'alert' : 'status' }}">{{ session($tipo) }}</p>
                @endif
            @endforeach
            <form method="POST" action="{{ route('admin.login.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="admin-email" class="block font-semibold">Correo electrónico</label>
                    <input id="admin-email" name="email" type="email" autocomplete="username" value="{{ old('email') }}" required maxlength="255" class="mt-2 w-full rounded-lg border border-borde p-3" @error('email') aria-invalid="true" aria-describedby="admin-email-error" @enderror>
                    @error('email')<p id="admin-email-error" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="admin-password" class="block font-semibold">Contraseña</label>
                    <input id="admin-password" name="password" type="password" autocomplete="current-password" required maxlength="200" class="mt-2 w-full rounded-lg border border-borde p-3" @error('password') aria-invalid="true" aria-describedby="admin-password-error" @enderror>
                    @error('password')<p id="admin-password-error" role="alert">{{ $message }}</p>@enderror
                </div>
                <x-boton type="submit" ancho="completo">Entrar a administración</x-boton>
            </form>
        </section>
    </main>
</x-layouts.base>
