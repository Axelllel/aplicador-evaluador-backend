{{--
    Archivo: boton.blade.php
    Módulo: resources/views/components
    Descripción: Botón principal, secundario, de texto y de eliminación de 44 px de alto como mínimo;
                 si recibe una acción de la Tabla 17 usa su texto e ícono (Manual COD-WEB, secciones 6.17 y 6.18).
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 27/10/2026 · Leonardo Ballestero López · Botón de texto con color primario sobre superficie
--}}
@props([
    'variante' => 'principal',
    'accion' => null,
    'href' => null,
    'type' => 'button',
    'ancho' => 'auto',
    'icono' => null,
    'sobre' => 'blanco',
])
@php
    $accionPanel = $accion !== null ? \App\Enums\AccionPanel::from($accion) : null;
    $nombreDelIcono = $accionPanel?->icono() ?? $icono;

    $clasesBase = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-4 text-base font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acento disabled:cursor-not-allowed disabled:opacity-60';

    // El acento solo se usa sobre fondo blanco; sobre superficie el texto va en color primario subrayado.
    $textoSobreSuperficie = 'text-primario underline hover:no-underline';
    $textoSobreBlanco = 'text-acento underline hover:no-underline';

    $clasesDeVariante = match ($variante) {
        'principal' => 'border border-primario bg-primario text-white hover:opacity-90',
        'secundario' => 'border border-primario bg-transparent text-primario hover:bg-superficie',
        'texto' => $sobre === 'superficie' ? $textoSobreSuperficie : $textoSobreBlanco,
        'peligro' => 'border border-error bg-transparent text-error hover:bg-superficie',
        'peligro-solido' => 'border border-error bg-error text-white hover:opacity-90',
    };

    $clasesDeAncho = match ($ancho) {
        'completo' => 'w-full',
        'movil' => 'w-full sm:w-auto',
        default => '',
    };

    $etiqueta = $href !== null ? 'a' : 'button';
    $atributosPropios = $href !== null ? ['href' => $href] : ['type' => $type];
@endphp
<{{ $etiqueta }} {{ $attributes->merge($atributosPropios)->class([$clasesBase, $clasesDeVariante, $clasesDeAncho]) }}>
    @if ($nombreDelIcono !== null)
        <x-icon :name="$nombreDelIcono" />
    @endif
    <span>{{ $slot->isEmpty() ? $accionPanel?->texto() : $slot }}</span>
</{{ $etiqueta }}>
