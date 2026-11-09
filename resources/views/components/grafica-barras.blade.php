{{--
    Archivo: grafica-barras.blade.php
    Módulo: resources/views/components
    Descripción: Gráfica de barras en SVG propio, sin librerías ni fuentes externas: título, ejes con unidad,
                 leyenda, fuente de los datos y etiquetas directas sobre las barras. Serie principal en acento claro,
                 secundaria en acento medio (con rayas, para no depender solo del color) y de apoyo en primario; sin 3D
                 ni sombras (Manual de Calidad, sección 3.10; Manual COD-WEB, secciones 6.14 y 8.3).
    Autor: Leonardo Ballestero López
    Creado: 06/11/2026
    Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
--}}
@php
    // Clases completas para que Tailwind las detecte; el color sale siempre de un token del tema.
    $rellenos = [
        'principal' => 'fill-acento-claro',
        'secundaria' => 'fill-acento-medio',
        'apoyo' => 'fill-primario',
    ];

    $tieneDatosParaDibujar = $tieneDatos();
    $dibujo = $tieneDatosParaDibujar ? $geometria() : null;
    $idDelPatron = $identificador.'-rayas';
    $mitadVertical = $dibujo !== null ? round(($dibujo['tope'] + $dibujo['base']) / 2, 2) : 0;
@endphp
<figure aria-labelledby="{{ $identificador }}-titulo" class="space-y-3 rounded-xl border border-borde bg-white p-4">
    <figcaption id="{{ $identificador }}-titulo" class="text-base font-semibold text-primario">{{ $titulo }}</figcaption>

    @if ($descripcion !== null)
        <p class="text-sm text-texto-suave">{{ $descripcion }}</p>
    @endif

    @if (! $tieneDatosParaDibujar)
        <p class="text-base text-primario">{{ __('estadisticas.vacio') }}</p>
    @else
        <ul class="flex flex-wrap gap-x-6 gap-y-2" aria-label="{{ __('estadisticas.grafica.leyenda') }}">
            @foreach ($series as $serie)
                @php
                    $tono = in_array($serie['tono'] ?? 'principal', \App\View\Components\GraficaBarras::TONOS, true) ? ($serie['tono'] ?? 'principal') : 'principal';
                @endphp
                <li class="flex items-center gap-2 text-sm text-primario">
                    <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false">
                        <rect x="0.5" y="0.5" width="15" height="15" class="{{ $rellenos[$tono] }} stroke-primario" stroke-width="1" />
                        @if ($tono === 'secundaria')
                            <rect x="0.5" y="0.5" width="15" height="15" fill="url(#{{ $idDelPatron }})" />
                        @endif
                    </svg>
                    <span>{{ $serie['nombre'] }}</span>
                </li>
            @endforeach
        </ul>

        <div class="overflow-x-auto rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acento" role="region" tabindex="0" aria-label="{{ $titulo }}">
            <svg
                role="img"
                aria-labelledby="{{ $identificador }}-titulo"
                width="{{ $dibujo['ancho'] }}"
                height="{{ $dibujo['alto'] }}"
                viewBox="0 0 {{ $dibujo['ancho'] }} {{ $dibujo['alto'] }}"
                style="width: {{ $dibujo['ancho'] }}px; max-width: none"
                class="mx-auto block h-auto"
                focusable="false"
            >
                <defs>
                    <pattern id="{{ $idDelPatron }}" width="8" height="8" patternUnits="userSpaceOnUse">
                        <path d="M-2 2L2 -2M0 8L8 0M6 10L10 6" class="stroke-primario" stroke-width="1" fill="none" />
                    </pattern>
                </defs>

                {{-- Líneas de referencia y marcas del eje vertical --}}
                @foreach ($dibujo['marcas'] as $marca)
                    @if ($marca['y'] !== (float) $dibujo['base'])
                        <line x1="{{ $dibujo['izquierda'] }}" x2="{{ $dibujo['derecha'] }}" y1="{{ $marca['y'] }}" y2="{{ $marca['y'] }}" class="stroke-borde" stroke-width="1" />
                    @endif
                    <text x="{{ $dibujo['izquierda'] - 8 }}" y="{{ $marca['y'] + 4 }}" text-anchor="end" class="fill-texto-suave text-xs">{{ $marca['etiqueta'] }}</text>
                @endforeach

                {{-- Ejes --}}
                <line x1="{{ $dibujo['izquierda'] }}" x2="{{ $dibujo['izquierda'] }}" y1="{{ $dibujo['tope'] }}" y2="{{ $dibujo['base'] }}" class="stroke-primario" stroke-width="1" />
                <line x1="{{ $dibujo['izquierda'] }}" x2="{{ $dibujo['derecha'] }}" y1="{{ $dibujo['base'] }}" y2="{{ $dibujo['base'] }}" class="stroke-primario" stroke-width="1" />

                {{-- Barras con su etiqueta directa --}}
                @foreach ($dibujo['grupos'] as $grupo)
                    @foreach ($grupo['barras'] as $barra)
                        <rect x="{{ $barra['x'] }}" y="{{ $barra['y'] }}" width="{{ $barra['ancho'] }}" height="{{ $barra['alto'] }}" class="{{ $rellenos[$barra['tono']] }} stroke-primario" stroke-width="1" />
                        @if ($barra['tono'] === 'secundaria')
                            <rect x="{{ $barra['x'] }}" y="{{ $barra['y'] }}" width="{{ $barra['ancho'] }}" height="{{ $barra['alto'] }}" fill="url(#{{ $idDelPatron }})" />
                        @endif
                        <text x="{{ $barra['x'] + $barra['ancho'] / 2 }}" y="{{ $barra['y'] - 6 }}" text-anchor="middle" class="fill-primario text-xs font-semibold">{{ $barra['etiqueta'] }}</text>
                    @endforeach

                    <text x="{{ $grupo['centro'] }}" y="{{ $dibujo['base'] + 18 }}" text-anchor="middle" class="fill-primario text-xs">
                        <title>{{ $grupo['completa'] }}</title>
                        {{ $grupo['etiqueta'] }}
                    </text>
                @endforeach

                {{-- Títulos de los ejes con su unidad --}}
                <text x="{{ $dibujo['izquierda'] + ($dibujo['derecha'] - $dibujo['izquierda']) / 2 }}" y="{{ $dibujo['alto'] - 12 }}" text-anchor="middle" class="fill-primario text-xs">{{ $ejeX }}</text>
                <text x="16" y="{{ $mitadVertical }}" text-anchor="middle" transform="rotate(-90 16 {{ $mitadVertical }})" class="fill-primario text-xs">{{ $ejeY }}</text>
            </svg>
        </div>

        {{-- Tabla equivalente para lectores de pantalla: el dibujo no expone sus valores. --}}
        <table class="sr-only">
            <caption>{{ $titulo }}</caption>
            <thead>
                <tr>
                    <th scope="col">{{ $ejeX }}</th>
                    @foreach ($series as $serie)
                        <th scope="col">{{ $serie['nombre'] }} ({{ $ejeY }})</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($filasDeDatos() as $fila)
                    <tr>
                        <th scope="row">{{ $fila[0] }}</th>
                        @foreach (array_slice($fila, 1) as $valor)
                            <td>{{ $valor }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="text-sm text-texto-suave">{{ __('estadisticas.grafica.fuente', ['fuente' => $fuente]) }}</p>
</figure>
