{{--
    Archivo: confirmar.blade.php
    Módulo: resources/views/components
    Descripción: Ventana modal de confirmación con dos botones: Cancelar y la acción principal a la derecha;
                 la confirmación previa de Eliminar usa el botón en color de error
                 (Manual COD-WEB, secciones 6.17 y 6.18, Tablas 16 y 17).
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
--}}
@props([
    'id',
    'titulo',
    'mensaje',
    'accion' => 'aceptar',
    'destino' => null,
    'metodo' => 'POST',
    'peligro' => false,
])
@php
    $varianteDelBoton = $peligro ? 'peligro-solido' : 'principal';
@endphp
<x-modal :id="$id" :titulo="$titulo" aria-describedby="{{ $id }}-mensaje">
    <p id="{{ $id }}-mensaje">{{ $mensaje }}</p>

    <x-slot:acciones>
        <x-boton variante="secundario" accion="cancelar" data-modal-cerrar />

        @if ($destino !== null)
            <form method="POST" action="{{ $destino }}">
                @csrf
                @if ($metodo !== 'POST')
                    @method($metodo)
                @endif
                <x-boton type="submit" :variante="$varianteDelBoton" :accion="$accion" />
            </form>
        @else
            <x-boton :variante="$varianteDelBoton" :accion="$accion" data-modal-cerrar />
        @endif
    </x-slot:acciones>
</x-modal>
