{{--
    Archivo: eliminar.blade.php
    Módulo: resources/views/grupos
    Descripción: Confirmación del borrado definitivo con su alcance y bloqueo de exámenes en curso.
    Autor: Axel Rebollo Zeferino
    Creado: 24/11/2026
    Modificado: 24/11/2026 · Equipo BigBitByte · Gestión de grupos
--}}
<x-layouts.panel :titulo="__('grupos.eliminacion.titulo_grupo')" :descripcion="$grupo['materia']">
    <x-tarjeta class="max-w-3xl space-y-6">
        <div class="space-y-2"><p class="tp-section-label">{{ __('grupos.eliminacion.eliminar_grupo') }}</p><h2 class="text-xl font-semibold">{{ $grupo['materia'] }}</h2><p class="break-all text-sm text-texto-suave">{{ __('grupos.lista.folio') }}: {{ $grupo['folio_codigo'] }}</p></div>
        <p>{{ __('grupos.eliminacion.grupo_aviso') }}</p>
        <dl class="grid grid-cols-2 gap-4">
            @foreach (['alumnos', 'examenes', 'intentos', 'evaluaciones'] as $dato)
                <div class="rounded-lg border border-borde p-3"><dt class="text-sm text-texto-suave">{{ __('grupos.eliminacion.'.$dato) }}</dt><dd class="text-xl font-semibold">{{ $grupo[$dato] }}</dd></div>
            @endforeach
        </dl>
        <p class="text-sm text-texto-suave">{{ __('grupos.eliminacion.identidad') }} {{ __('grupos.eliminacion.banco') }}</p>
        @if ($grupo['intentos_en_curso'] > 0)
            <x-alerta tipo="aviso" :autoocultar="false">{{ __('grupos.eliminacion.en_curso') }}</x-alerta>
            <x-boton variante="secundario" :href="route('panel.grupos')">{{ __('grupos.eliminacion.cancelar') }}</x-boton>
        @else
            <form method="POST" action="{{ route('panel.grupos.eliminar', $grupo['id']) }}" class="space-y-5">
                @csrf @method('DELETE')
                <x-campo-texto nombre="confirmacion" :valor="''" :etiqueta="__('grupos.eliminacion.confirmacion')" :ayuda="__('grupos.eliminacion.confirmacion_ayuda')" autocomplete="off" spellcheck="false" pattern="ELIMINAR" maxlength="8" required />
                <div class="flex flex-wrap justify-end gap-3"><x-boton variante="secundario" :href="route('panel.grupos')">{{ __('grupos.eliminacion.cancelar') }}</x-boton><x-boton type="submit" variante="peligro-solido" icono="trash">{{ __('grupos.eliminacion.confirmar_grupo') }}</x-boton></div>
            </form>
        @endif
    </x-tarjeta>
</x-layouts.panel>
