{{--
    Archivo: panel.blade.php
    Módulo: resources/views/pagination
    Descripción: Paginación de las tablas del panel (15 filas por página) con controles de 44 × 44 px
                 y página actual marcada con texto y borde (Manual COD-WEB, sección 6.17, Tabla 15).
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
--}}
@if ($paginator->hasPages())
    <nav aria-label="{{ __('panel.paginacion.etiqueta') }}" class="flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-texto-suave">
            {{ __('panel.paginacion.resumen', ['desde' => $paginator->firstItem(), 'hasta' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
        </p>

        <ul class="flex flex-wrap items-center gap-2">
            <li>
                @if ($paginator->onFirstPage())
                    <span aria-disabled="true" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-borde px-4 text-base text-texto-suave">{{ __('panel.paginacion.anterior') }}</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-primario px-4 text-base font-semibold text-primario hover:bg-superficie focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acento">{{ __('panel.paginacion.anterior') }}</a>
                @endif
            </li>

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="inline-flex min-h-11 min-w-11 items-center justify-center text-base text-texto-suave">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $pagina => $url)
                        <li>
                            @if ($pagina === $paginator->currentPage())
                                <span aria-current="page" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-primario bg-primario px-4 text-base font-semibold text-white">{{ $pagina }}</span>
                            @else
                                <a href="{{ $url }}" aria-label="{{ __('panel.paginacion.pagina', ['numero' => $pagina]) }}" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-borde px-4 text-base text-primario hover:bg-superficie focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acento">{{ $pagina }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach

            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-primario px-4 text-base font-semibold text-primario hover:bg-superficie focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acento">{{ __('panel.paginacion.siguiente') }}</a>
                @else
                    <span aria-disabled="true" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-borde px-4 text-base text-texto-suave">{{ __('panel.paginacion.siguiente') }}</span>
                @endif
            </li>
        </ul>
    </nav>
@endif
