{{--
    Archivo: eliminar-alumno.blade.php
    Módulo: resources/views/grupos
    Descripción: Confirmación del borrado del alumno limitado a un grupo del docente.
    Autor: Axel Rebollo Zeferino
    Creado: 24/11/2026
    Modificado: 24/11/2026 · Equipo BigBitByte · Gestión de grupos
--}}
<x-layouts.panel :titulo="__('grupos.eliminacion.titulo_alumno')" :descripcion="$grupo['materia']">
    <x-tarjeta class="max-w-3xl space-y-6">
        <div class="space-y-2"><p class="tp-section-label">{{ $grupo['materia'] }}</p><h2 class="text-xl font-semibold">{{ $alumno['nombre'] }}</h2><p class="text-sm text-texto-suave">Registro #{{ $alumno['id'] }} · {{ __('grupos.eliminacion.intentos') }}: {{ $alumno['intentos_en_grupo'] }}</p></div>
        <p>{{ __('grupos.eliminacion.alumno_aviso') }}</p>
        <p class="text-sm text-texto-suave">{{ __('grupos.eliminacion.identidad') }}</p>
        @if (($alumno['intentos_en_curso'] ?? 0) > 0)
            <x-alerta tipo="aviso" :autoocultar="false">{{ __('grupos.eliminacion.en_curso') }}</x-alerta>
            <x-boton variante="secundario" :href="route('panel.grupos.alumnos', $grupo['id'])">{{ __('grupos.eliminacion.cancelar') }}</x-boton>
        @else
            <form method="POST" action="{{ route('panel.grupos.alumnos.eliminar', ['grupo' => $grupo['id'], 'alumno' => $alumno['id']]) }}" class="space-y-5">
                @csrf @method('DELETE')
                <x-campo-texto nombre="confirmacion" :valor="''" :etiqueta="__('grupos.eliminacion.confirmacion')" :ayuda="__('grupos.eliminacion.confirmacion_ayuda')" autocomplete="off" spellcheck="false" pattern="ELIMINAR" maxlength="8" required />
                <div class="flex flex-wrap justify-end gap-3"><x-boton variante="secundario" :href="route('panel.grupos.alumnos', $grupo['id'])">{{ __('grupos.eliminacion.cancelar') }}</x-boton><x-boton type="submit" variante="peligro-solido" icono="trash">{{ __('grupos.eliminacion.confirmar_alumno') }}</x-boton></div>
            </form>
        @endif
    </x-tarjeta>
</x-layouts.panel>
