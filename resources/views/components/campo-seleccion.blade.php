{{--
    Archivo: campo-seleccion.blade.php
    Módulo: resources/views/components
    Descripción: Lista desplegable de 44 px de alto con etiqueta arriba y mensaje de error debajo; mismo formato
                 que la caja de texto: anillo de 2 px en acento con foco y borde de error si falla
                 (Manual COD-WEB, secciones 6.16 y 6.17).
    Autor: Leonardo Ballestero López
    Creado: 06/11/2026
    Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
--}}
@props([
    'nombre',
    'etiqueta',
    'opciones' => [],
    'vacio' => null,
    'ayuda' => null,
    'error' => null,
    'valor' => null,
    'porDefecto' => null,
])
@php
    $idDelCampo = $attributes->get('id', 'campo-'.str_replace('_', '-', $nombre));
    $mensajeDeError = $error ?? $errors->first($nombre);
    $tieneError = $mensajeDeError !== null && $mensajeDeError !== '';
    // El valor enviado antes (old) gana al valor por defecto; «valor» fuerza uno fijo.
    $valorActual = (string) ($valor ?? old($nombre, $porDefecto ?? ''));
    $idDelError = $idDelCampo.'-error';
    $idDeLaAyuda = $idDelCampo.'-ayuda';
    $descripciones = trim(($tieneError ? $idDelError : '').' '.($ayuda !== null ? $idDeLaAyuda : ''));
@endphp
<div class="space-y-1">
    <label for="{{ $idDelCampo }}" class="block text-sm text-texto-suave">{{ $etiqueta }}</label>

    <select
        id="{{ $idDelCampo }}"
        name="{{ $nombre }}"
        @if ($tieneError) aria-invalid="true" @endif
        @if ($descripciones !== '') aria-describedby="{{ $descripciones }}" @endif
        {{ $attributes->except('id')->class([
            'h-11 w-full rounded-lg border bg-white px-3 text-base text-primario focus:outline-hidden focus:ring-2 focus:ring-acento',
            'border-borde' => ! $tieneError,
            'border-error' => $tieneError,
        ]) }}
    >
        @if ($vacio !== null)
            <option value="">{{ $vacio }}</option>
        @endif

        @foreach ($opciones as $valorDeLaOpcion => $textoDeLaOpcion)
            <option value="{{ $valorDeLaOpcion }}" @selected((string) $valorDeLaOpcion === $valorActual)>{{ $textoDeLaOpcion }}</option>
        @endforeach
    </select>

    @if ($ayuda !== null)
        <p id="{{ $idDeLaAyuda }}" class="text-xs text-texto-suave">{{ $ayuda }}</p>
    @endif

    @if ($tieneError)
        <p id="{{ $idDelError }}" class="flex items-center gap-1 text-xs text-error">
            <x-icon name="exclamation-circle" />
            <span>{{ \Illuminate\Support\Str::ucfirst($mensajeDeError) }}</span>
        </p>
    @endif
</div>
