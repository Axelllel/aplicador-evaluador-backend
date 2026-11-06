{{--
    Archivo: campo-texto.blade.php
    Módulo: resources/views/components
    Descripción: Caja de texto de 44 px de alto con etiqueta arriba y mensaje de error debajo;
                 con foco muestra un anillo de 2 px en acento y con error el borde en color de error
                 (Manual COD-WEB, secciones 6.16 y 6.17).
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 27/10/2026 · Leonardo Ballestero López · Marcador de posición en texto-suave
--}}
@props([
    'nombre',
    'etiqueta',
    'tipo' => 'text',
    'ayuda' => null,
    'error' => null,
    'valor' => null,
])
@php
    $idDelCampo = $attributes->get('id', 'campo-'.str_replace('_', '-', $nombre));
    $mensajeDeError = $error ?? $errors->first($nombre);
    $tieneError = $mensajeDeError !== null && $mensajeDeError !== '';
    $valorActual = $tipo === 'password' ? null : ($valor ?? old($nombre));
    $idDelError = $idDelCampo.'-error';
    $idDeLaAyuda = $idDelCampo.'-ayuda';
    $descripciones = trim(($tieneError ? $idDelError : '').' '.($ayuda !== null ? $idDeLaAyuda : ''));
@endphp
<div class="space-y-1">
    <label for="{{ $idDelCampo }}" class="block text-sm text-texto-suave">{{ $etiqueta }}</label>

    <input
        id="{{ $idDelCampo }}"
        name="{{ $nombre }}"
        type="{{ $tipo }}"
        @if ($valorActual !== null) value="{{ $valorActual }}" @endif
        @if ($tieneError) aria-invalid="true" @endif
        @if ($descripciones !== '') aria-describedby="{{ $descripciones }}" @endif
        {{ $attributes->except('id')->class([
            'h-11 w-full rounded-lg border bg-white px-3 text-base text-primario placeholder:text-texto-suave focus:outline-hidden focus:ring-2 focus:ring-acento',
            'border-borde' => ! $tieneError,
            'border-error' => $tieneError,
        ]) }}
    >

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
