{{--
    Archivo: index.blade.php
    Módulo: resources/views/guia-estilo
    Descripción: Guía de estilo con todos los elementos visuales del panel (paleta, contraste, tipografía,
                 botones, campos, tarjeta, tabla, mensajes, modal, QR, íconos y logotipo) para auditarlos
                 (Manual COD-WEB, secciones 6.14 a 6.19).
    Autor: Leonardo Ballestero López
    Creado: 27/10/2026
    Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
--}}
@php
    // Clases completas para que Tailwind las detecte; el color sale siempre de un token.
    $muestras = [
        'primario' => 'bg-primario',
        'acento' => 'bg-acento',
        'acento-claro' => 'bg-acento-claro',
        'acento-medio' => 'bg-acento-medio',
        'borde' => 'bg-borde',
        'texto-suave' => 'bg-texto-suave',
        'white' => 'bg-white',
        'superficie' => 'bg-superficie',
        'exito' => 'bg-exito',
        'aviso' => 'bg-aviso',
        'error' => 'bg-error',
    ];

    $formatear = fn (float $relacion): string => number_format($relacion, 2, '.', '').':1';
    $estilos = __('guia.tipografia.estilos');
    $secciones = __('guia.secciones');
@endphp
<x-layouts.panel :titulo="__('guia.titulo')" :descripcion="__('guia.descripcion')">
    <nav aria-label="{{ __('guia.en_esta_pagina') }}" class="space-y-2">
        <h2 class="text-xl font-semibold text-primario">{{ __('guia.en_esta_pagina') }}</h2>
        <ul class="grid gap-x-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($secciones as $idDeSeccion => $tituloDeSeccion)
                <li>
                    <a href="#{{ $idDeSeccion }}" class="inline-flex min-h-11 items-center text-base text-acento underline hover:no-underline">{{ $tituloDeSeccion }}</a>
                </li>
            @endforeach
        </ul>
    </nav>

    {{-- Paleta --}}
    <x-guia.seccion id="paleta" :titulo="$secciones['paleta']" referencia="6.14, Tabla 12">
        <p class="text-sm text-texto-suave">{{ __('guia.paleta.descripcion') }}</p>

        <x-tabla :titulo="$secciones['paleta']" :columnas="array_values(__('guia.paleta.columnas'))">
            @foreach ($colores as $color)
                <x-tabla.fila>
                    <x-tabla.celda>
                        <span class="block h-11 w-11 rounded-lg border border-borde {{ $muestras[$color['token']] ?? '' }}" aria-hidden="true"></span>
                    </x-tabla.celda>
                    <x-tabla.celda><x-codigo class="whitespace-nowrap">{{ $color['token'] }}</x-codigo></x-tabla.celda>
                    <x-tabla.celda><x-codigo class="whitespace-nowrap">{{ $color['hex'] }}</x-codigo></x-tabla.celda>
                    <x-tabla.celda>{{ __('guia.paleta.usos.'.$color['token']) }}</x-tabla.celda>
                    <x-tabla.celda>{{ $formatear($color['sobre_blanco']) }}</x-tabla.celda>
                    <x-tabla.celda>{{ $formatear($color['texto_blanco']) }}</x-tabla.celda>
                </x-tabla.fila>
            @endforeach
        </x-tabla>
    </x-guia.seccion>

    {{-- Relaciones de contraste --}}
    <x-guia.seccion id="contraste" :titulo="$secciones['contraste']" referencia="6.14">
        <p class="text-sm text-texto-suave">{{ __('guia.contraste.descripcion') }}</p>

        <x-tabla :titulo="$secciones['contraste']" :columnas="array_values(__('guia.contraste.columnas'))">
            @foreach ($pares as $par)
                <x-tabla.fila>
                    <x-tabla.celda><x-codigo>{{ $par['texto'] }} {{ $par['hex_texto'] }}</x-codigo></x-tabla.celda>
                    <x-tabla.celda><x-codigo>{{ $par['fondo'] }} {{ $par['hex_fondo'] }}</x-codigo></x-tabla.celda>
                    <x-tabla.celda>{{ __('guia.contraste.usos.'.$par['clave']) }}</x-tabla.celda>
                    <x-tabla.celda>{{ $formatear($par['relacion']) }}</x-tabla.celda>
                    <x-tabla.celda>{{ $formatear($par['minimo']) }}</x-tabla.celda>
                    <x-tabla.celda><x-resultado-contraste :cumple="$par['cumple']" /></x-tabla.celda>
                </x-tabla.fila>
            @endforeach
        </x-tabla>
    </x-guia.seccion>

    {{-- Hallazgos de contraste --}}
    <x-guia.seccion id="hallazgos" :titulo="$secciones['hallazgos']" referencia="6.14, 6.17">
        <p class="text-sm text-texto-suave">{{ __('guia.hallazgos.descripcion') }}</p>

        <x-tabla :titulo="$secciones['hallazgos']" :columnas="array_values(__('guia.hallazgos.columnas'))">
            @foreach ($hallazgos as $hallazgo)
                <x-tabla.fila>
                    <x-tabla.celda>
                        <p class="font-semibold">{{ __('guia.hallazgos.items.'.$hallazgo['clave'].'.titulo') }}</p>
                        <p class="text-sm text-texto-suave">{{ __('guia.hallazgos.items.'.$hallazgo['clave'].'.detalle') }}</p>
                    </x-tabla.celda>
                    <x-tabla.celda>
                        <x-codigo>{{ __('guia.hallazgos.colores', [
                            'texto' => $hallazgo['texto'].' '.$hallazgo['hex_texto'],
                            'fondo' => $hallazgo['fondo'].' '.$hallazgo['hex_fondo'],
                        ]) }}</x-codigo>
                    </x-tabla.celda>
                    <x-tabla.celda>{{ $formatear($hallazgo['relacion']) }}</x-tabla.celda>
                    <x-tabla.celda>{{ $formatear($hallazgo['minimo']) }}</x-tabla.celda>
                    <x-tabla.celda><x-resultado-contraste :cumple="$hallazgo['cumple']" /></x-tabla.celda>
                    <x-tabla.celda>{{ __('guia.hallazgos.items.'.$hallazgo['clave'].'.referencia') }}</x-tabla.celda>
                </x-tabla.fila>
            @endforeach
        </x-tabla>
    </x-guia.seccion>

    {{-- Tipografía --}}
    <x-guia.seccion id="tipografia" :titulo="$secciones['tipografia']" referencia="6.15, Tabla 13">
        <p class="text-sm text-texto-suave">{{ __('guia.tipografia.descripcion') }}</p>

        <x-tabla :titulo="$secciones['tipografia']" :columnas="array_values(__('guia.tipografia.columnas'))">
            <x-tabla.fila>
                <x-tabla.celda class="font-semibold">{{ $estilos['titulo_pagina']['nombre'] }}</x-tabla.celda>
                <x-tabla.celda><p class="text-2xl font-semibold text-primario">{{ $estilos['titulo_pagina']['muestra'] }}</p></x-tabla.celda>
                <x-tabla.celda>{{ $estilos['titulo_pagina']['medidas'] }}</x-tabla.celda>
                <x-tabla.celda><x-codigo>{{ $estilos['titulo_pagina']['clase'] }}</x-codigo></x-tabla.celda>
            </x-tabla.fila>
            <x-tabla.fila>
                <x-tabla.celda class="font-semibold">{{ $estilos['encabezado_seccion']['nombre'] }}</x-tabla.celda>
                <x-tabla.celda><p class="text-xl font-semibold text-primario">{{ $estilos['encabezado_seccion']['muestra'] }}</p></x-tabla.celda>
                <x-tabla.celda>{{ $estilos['encabezado_seccion']['medidas'] }}</x-tabla.celda>
                <x-tabla.celda><x-codigo>{{ $estilos['encabezado_seccion']['clase'] }}</x-codigo></x-tabla.celda>
            </x-tabla.fila>
            <x-tabla.fila>
                <x-tabla.celda class="font-semibold">{{ $estilos['cuerpo']['nombre'] }}</x-tabla.celda>
                <x-tabla.celda><p class="text-base leading-normal text-primario">{{ $estilos['cuerpo']['muestra'] }}</p></x-tabla.celda>
                <x-tabla.celda>{{ $estilos['cuerpo']['medidas'] }}</x-tabla.celda>
                <x-tabla.celda><x-codigo>{{ $estilos['cuerpo']['clase'] }}</x-codigo></x-tabla.celda>
            </x-tabla.fila>
            <x-tabla.fila>
                <x-tabla.celda class="font-semibold">{{ $estilos['boton']['nombre'] }}</x-tabla.celda>
                <x-tabla.celda>
                    <span class="inline-block rounded-lg bg-primario px-4 py-2 text-base font-semibold text-white">{{ $estilos['boton']['muestra'] }}</span>
                </x-tabla.celda>
                <x-tabla.celda>{{ $estilos['boton']['medidas'] }}</x-tabla.celda>
                <x-tabla.celda><x-codigo>{{ $estilos['boton']['clase'] }}</x-codigo></x-tabla.celda>
            </x-tabla.fila>
            <x-tabla.fila>
                <x-tabla.celda class="font-semibold">{{ $estilos['etiqueta']['nombre'] }}</x-tabla.celda>
                <x-tabla.celda><p class="text-sm text-texto-suave">{{ $estilos['etiqueta']['muestra'] }}</p></x-tabla.celda>
                <x-tabla.celda>{{ $estilos['etiqueta']['medidas'] }}</x-tabla.celda>
                <x-tabla.celda><x-codigo>{{ $estilos['etiqueta']['clase'] }}</x-codigo></x-tabla.celda>
            </x-tabla.fila>
            <x-tabla.fila>
                <x-tabla.celda class="font-semibold">{{ $estilos['leyenda']['nombre'] }}</x-tabla.celda>
                <x-tabla.celda><p class="text-xs text-texto-suave">{{ $estilos['leyenda']['muestra'] }}</p></x-tabla.celda>
                <x-tabla.celda>{{ $estilos['leyenda']['medidas'] }}</x-tabla.celda>
                <x-tabla.celda><x-codigo>{{ $estilos['leyenda']['clase'] }}</x-codigo></x-tabla.celda>
            </x-tabla.fila>
            <x-tabla.fila>
                <x-tabla.celda class="font-semibold">{{ $estilos['validacion']['nombre'] }}</x-tabla.celda>
                <x-tabla.celda><p class="text-xs text-error">{{ $estilos['validacion']['muestra'] }}</p></x-tabla.celda>
                <x-tabla.celda>{{ $estilos['validacion']['medidas'] }}</x-tabla.celda>
                <x-tabla.celda><x-codigo>{{ $estilos['validacion']['clase'] }}</x-codigo></x-tabla.celda>
            </x-tabla.fila>
            <x-tabla.fila>
                <x-tabla.celda class="font-semibold">{{ $estilos['codigo']['nombre'] }}</x-tabla.celda>
                <x-tabla.celda><x-codigo>{{ $estilos['codigo']['muestra'] }}</x-codigo></x-tabla.celda>
                <x-tabla.celda>{{ $estilos['codigo']['medidas'] }}</x-tabla.celda>
                <x-tabla.celda><x-codigo>{{ $estilos['codigo']['clase'] }}</x-codigo></x-tabla.celda>
            </x-tabla.fila>
        </x-tabla>
    </x-guia.seccion>

    {{-- Distribución --}}
    <x-guia.seccion id="distribucion" :titulo="$secciones['distribucion']" referencia="6.16, Tabla 14">
        <p class="text-sm text-texto-suave">{{ __('guia.distribucion.descripcion') }}</p>

        <x-tabla :titulo="$secciones['distribucion']" :columnas="array_values(__('guia.distribucion.columnas'))">
            @foreach (__('guia.distribucion.medidas') as $medida)
                <x-tabla.fila>
                    <x-tabla.celda class="font-semibold">{{ $medida['elemento'] }}</x-tabla.celda>
                    <x-tabla.celda>{{ $medida['medida'] }}</x-tabla.celda>
                    <x-tabla.celda><x-codigo>{{ $medida['clase'] }}</x-codigo></x-tabla.celda>
                </x-tabla.fila>
            @endforeach
        </x-tabla>
    </x-guia.seccion>

    {{-- Botones --}}
    <x-guia.seccion id="botones" :titulo="$secciones['botones']" referencia="6.17, Tabla 15">
        <p class="text-sm text-texto-suave">{{ __('guia.botones.descripcion') }}</p>

        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <div class="space-y-2">
                <p class="text-sm text-texto-suave">{{ __('guia.botones.principal') }}</p>
                <x-boton accion="guardar" />
            </div>
            <div class="space-y-2">
                <p class="text-sm text-texto-suave">{{ __('guia.botones.secundario') }}</p>
                <x-boton variante="secundario" accion="cancelar" />
            </div>
            <div class="space-y-2">
                <p class="text-sm text-texto-suave">{{ __('guia.botones.texto') }}</p>
                <x-boton variante="texto" accion="reintentar" />
            </div>
            <div class="space-y-2">
                <p class="text-sm text-texto-suave">{{ __('guia.botones.texto_en_tarjeta') }}</p>
                <x-tarjeta class="w-fit">
                    <x-boton variante="texto" sobre="superficie" accion="reintentar" />
                </x-tarjeta>
            </div>
            <div class="space-y-2">
                <p class="text-sm text-texto-suave">{{ __('guia.botones.eliminar') }}</p>
                <x-boton variante="peligro" accion="eliminar" />
            </div>
            <div class="space-y-2">
                <p class="text-sm text-texto-suave">{{ __('guia.botones.ancho_completo') }}</p>
                <div class="max-w-xs">
                    <x-boton accion="guardar" ancho="completo" />
                </div>
            </div>
        </div>

        <p class="text-xs text-texto-suave">{{ __('guia.botones.nota_acento') }}</p>
    </x-guia.seccion>

    {{-- Cajas de texto --}}
    <x-guia.seccion id="campos" :titulo="$secciones['campos']" referencia="6.17, Tabla 15">
        <p class="text-sm text-texto-suave">{{ __('guia.campos.descripcion') }}</p>

        <div class="grid gap-6 md:grid-cols-3">
            <div class="space-y-2">
                <p class="text-sm text-texto-suave">{{ __('guia.campos.normal') }}</p>
                <x-campo-texto nombre="demo_normal" :etiqueta="__('guia.campos.etiqueta')" :placeholder="__('guia.campos.marcador')" />
            </div>
            <div class="space-y-2">
                <p class="text-sm text-texto-suave">{{ __('guia.campos.foco') }}</p>
                <x-campo-texto nombre="demo_foco" :etiqueta="__('guia.campos.etiqueta')" :placeholder="__('guia.campos.marcador')" class="ring-2 ring-acento" />
            </div>
            <div class="space-y-2">
                <p class="text-sm text-texto-suave">{{ __('guia.campos.error') }}</p>
                <x-campo-texto nombre="demo_error" :etiqueta="__('guia.campos.etiqueta')" :placeholder="__('guia.campos.marcador')" :error="__('guia.campos.mensaje_de_error')" />
            </div>
        </div>

        <p class="text-xs text-texto-suave">{{ __('guia.campos.ayuda') }}</p>
    </x-guia.seccion>

    {{-- Tarjeta --}}
    <x-guia.seccion id="tarjeta" :titulo="$secciones['tarjeta']" referencia="6.17, Tabla 15">
        <p class="text-sm text-texto-suave">{{ __('guia.tarjeta.descripcion') }}</p>

        <x-tarjeta class="max-w-md space-y-2">
            <h3 class="text-base font-semibold text-primario">{{ __('guia.tarjeta.encabezado') }}</h3>
            <p>
                {{ __('guia.tarjeta.texto') }}
                <a href="{{ route('panel.grupos') }}" class="text-primario underline hover:no-underline">{{ __('guia.tarjeta.enlace') }}</a>
            </p>
            <p class="text-sm text-texto-suave">{{ __('guia.tarjeta.secundario') }}</p>
        </x-tarjeta>
    </x-guia.seccion>

    {{-- Tabla de datos --}}
    <x-guia.seccion id="tabla" :titulo="$secciones['tabla']" referencia="6.17, Tabla 15">
        <p class="text-sm text-texto-suave">{{ __('guia.tabla.descripcion') }}</p>

        <x-examenes.tabla :examenes="$examenes" />
    </x-guia.seccion>

    {{-- Mensajes --}}
    <x-guia.seccion id="mensajes" :titulo="$secciones['mensajes']" referencia="6.18, Tabla 16">
        <p class="text-sm text-texto-suave">{{ __('guia.mensajes.descripcion') }}</p>

        <h3 class="text-base font-semibold text-primario">{{ __('guia.mensajes.avisos_superiores') }}</h3>
        <div id="avisos-de-ejemplo" class="space-y-3 rounded-xl border border-borde p-6">
            <x-alerta tipo="exito">{{ __('mensajes.exito.grupo_creado') }}</x-alerta>
            <x-alerta tipo="info">{{ __('mensajes.info.resultados_pendientes') }}</x-alerta>
            <p class="text-sm text-texto-suave">{{ __('guia.mensajes.nota_avisos') }}</p>
            <x-boton variante="secundario" accion="reintentar" data-reiniciar-avisos="avisos-de-ejemplo" />
        </div>

        <h3 class="text-base font-semibold text-primario">{{ __('guia.mensajes.banners') }}</h3>
        <div class="space-y-3">
            <x-alerta tipo="error">{{ __('mensajes.error.examen_no_guardado') }}</x-alerta>
            <x-alerta tipo="aviso">{{ __('mensajes.aviso.limite_grupos') }}</x-alerta>
            <p class="text-xs text-texto-suave">{{ __('guia.mensajes.nota_banners') }}</p>
        </div>
    </x-guia.seccion>

    {{-- Ventana modal y confirmación --}}
    <x-guia.seccion id="modal" :titulo="$secciones['modal']" referencia="6.17, 6.18, Tabla 16">
        <p class="text-sm text-texto-suave">{{ __('guia.modal.descripcion') }}</p>

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="space-y-2">
                <p class="text-sm text-texto-suave">{{ __('guia.modal.eliminar') }}</p>
                <x-boton variante="peligro" accion="eliminar" data-modal-abrir="confirmar-eliminar-grupo" />
            </div>
            <div class="space-y-2">
                <p class="text-sm text-texto-suave">{{ __('guia.modal.publicar') }}</p>
                <x-boton variante="secundario" accion="publicar" data-modal-abrir="confirmar-publicar-examen" />
            </div>
        </div>

        <x-confirmar
            id="confirmar-eliminar-grupo"
            :titulo="__('mensajes.confirmar.eliminar_grupo.titulo')"
            :mensaje="__('mensajes.confirmar.eliminar_grupo.mensaje')"
            accion="eliminar"
            :peligro="true"
        />
        <x-confirmar
            id="confirmar-publicar-examen"
            :titulo="__('mensajes.confirmar.publicar_examen.titulo')"
            :mensaje="__('mensajes.confirmar.publicar_examen.mensaje')"
            accion="publicar"
        />
    </x-guia.seccion>

    {{-- Código QR --}}
    <x-guia.seccion id="qr" :titulo="$secciones['qr']" referencia="6.17, Tabla 15">
        <p class="text-sm text-texto-suave">{{ __('guia.qr.descripcion') }}</p>

        <x-tarjeta class="space-y-4">
            <x-qr-grupo :folio="$folio" :svg="$qrSvg" />

            <div class="flex flex-wrap justify-center gap-3">
                <x-boton variante="secundario" accion="descargar_qr" :href="$qrDescarga" download="qr-{{ $folio }}.svg" />
                <x-boton variante="secundario" accion="copiar_folio" data-copiar="{{ $folio }}" data-copiar-aviso="aviso-folio-copiado" />
            </div>
        </x-tarjeta>

        <div class="pointer-events-none fixed inset-x-0 top-20 z-50 flex justify-center px-4">
            <x-alerta id="aviso-folio-copiado" tipo="exito" hidden class="pointer-events-auto w-full max-w-xl">{{ __('mensajes.exito.folio_copiado') }}</x-alerta>
        </div>
    </x-guia.seccion>

    {{-- Acciones e íconos --}}
    <x-guia.seccion id="acciones" :titulo="$secciones['acciones']" referencia="6.18 (Tabla 17) y §6.19">
        <p class="text-sm text-texto-suave">{{ __('guia.acciones.descripcion') }}</p>

        <x-tabla :titulo="$secciones['acciones']" :columnas="array_values(__('guia.acciones.columnas'))">
            @foreach ($acciones as $accion)
                <x-tabla.fila>
                    <x-tabla.celda class="font-semibold">{{ __('guia.acciones.descripciones.'.$accion->value) }}</x-tabla.celda>
                    <x-tabla.celda>
                        <x-boton :variante="$accion->esDestructiva() ? 'peligro' : 'secundario'" :accion="$accion->value" />
                    </x-tabla.celda>
                    <x-tabla.celda>
                        <x-accion-tabla :accion="$accion->value" />
                    </x-tabla.celda>
                    <x-tabla.celda><x-codigo>{{ $accion->icono() }}</x-codigo></x-tabla.celda>
                    <x-tabla.celda>
                        @if (\Illuminate\Support\Facades\Lang::has('guia.acciones.observaciones.'.$accion->value))
                            {{ __('guia.acciones.observaciones.'.$accion->value) }}
                        @endif
                    </x-tabla.celda>
                </x-tabla.fila>
            @endforeach
        </x-tabla>

        <x-tarjeta class="space-y-4">
            <div class="space-y-1">
                <h3 class="text-base font-semibold text-primario">{{ __('guia.iconos_adicionales.encabezado') }}</h3>
                <p class="text-sm text-texto-suave">{{ __('guia.iconos_adicionales.descripcion') }}</p>
            </div>

            <ul class="grid gap-x-6 gap-y-2 md:grid-cols-2">
                @foreach (__('guia.iconos_adicionales.lista') as $nombreDelIcono => $usoDelIcono)
                    <li class="flex items-center gap-3">
                        <x-icon :name="$nombreDelIcono" />
                        <x-codigo>{{ $nombreDelIcono }}</x-codigo>
                        <span class="text-sm text-texto-suave">{{ $usoDelIcono }}</span>
                    </li>
                @endforeach
            </ul>
        </x-tarjeta>
    </x-guia.seccion>

    {{-- Logotipo --}}
    <x-guia.seccion id="logotipo" :titulo="$secciones['logotipo']" referencia="6.14">
        <p class="text-sm text-texto-suave">{{ __('guia.logotipo.descripcion') }}</p>

        <div class="flex flex-wrap items-start gap-6">
            <div class="space-y-2">
                <p class="text-sm text-texto-suave">{{ __('guia.logotipo.barra') }}</p>
                {{-- Margen libre de 20 px (p-5) alrededor del logotipo de 40 px. --}}
                <div class="w-fit rounded-lg bg-primario p-5">
                    <x-logo :alto="40" />
                </div>
            </div>
            <div class="space-y-2">
                <p class="text-sm text-texto-suave">{{ __('guia.logotipo.inicio_de_sesion') }}</p>
                {{-- Margen libre de 48 px (p-12) alrededor del logotipo de 96 px. --}}
                <div class="w-fit rounded-xl bg-primario p-12">
                    <x-logo :alto="96" />
                </div>
            </div>
        </div>

        <p class="text-xs text-texto-suave">{{ __('guia.logotipo.origen') }}</p>
    </x-guia.seccion>
</x-layouts.panel>
