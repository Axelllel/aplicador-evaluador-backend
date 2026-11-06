{{--
    Archivo: index.blade.php
    Módulo: resources/views/estadisticas
    Descripción: Estadísticas calculadas en el panel con los resultados de la API: resumen general, promedio y
                 aprobación por examen y por grupo (gráfica y tabla de 15 filas) y, para un examen, distribución de
                 notas y preguntas con mayor error. Las gráficas usan el componente x-grafica-barras
                 (Manual COD-WEB, secciones 6.16 y 8.3; Manual de Calidad, sección 3.10).
    Autor: Leonardo Ballestero López
    Creado: 06/11/2026
    Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
--}}
@php
    $porcentaje = fn ($valor): string => $valor === null
        ? '—'
        : __('estadisticas.porcentaje', ['valor' => rtrim(rtrim(number_format((float) $valor, 2, '.', ''), '0'), '.')]);
    $cifra = fn ($valor): string => $valor === null
        ? '—'
        : rtrim(rtrim(number_format((float) $valor, 2, '.', ''), '0'), '.');
    $tipos = (array) __('preguntas.tipos');
@endphp
<x-layouts.panel :titulo="__('estadisticas.titulo')" :descripcion="__('estadisticas.descripcion')">
    @if (! $hayDatos)
        <x-tarjeta class="space-y-3">
            <p>{{ __('estadisticas.vacio') }}</p>
            <p class="text-sm text-texto-suave">{{ __('estadisticas.vacio_ayuda') }}</p>
            <x-boton variante="secundario" accion="resultados" :href="route('panel.resultados')" />
        </x-tarjeta>
    @else
        <section aria-labelledby="titulo-resumen-general" class="space-y-4">
            <h2 id="titulo-resumen-general" class="text-xl font-semibold text-primario">{{ __('estadisticas.resumen.encabezado') }}</h2>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-tarjeta class="space-y-1">
                    <p class="text-sm text-texto-suave">{{ __('estadisticas.resumen.intentos') }}</p>
                    <p class="text-2xl font-semibold text-primario">{{ $general['intentos'] }}</p>
                    <p class="text-sm text-texto-suave">{{ trans_choice('estadisticas.resumen.pendientes', $general['pendientes'], ['cantidad' => $general['pendientes']]) }}</p>
                </x-tarjeta>
                <x-tarjeta class="space-y-1">
                    <p class="text-sm text-texto-suave">{{ __('estadisticas.resumen.calificados') }}</p>
                    <p class="text-2xl font-semibold text-primario">{{ $general['calificados'] }}</p>
                </x-tarjeta>
                <x-tarjeta class="space-y-1">
                    <p class="text-sm text-texto-suave">{{ __('estadisticas.resumen.promedio') }}</p>
                    <p class="text-2xl font-semibold text-primario">{{ $porcentaje($general['promedio']) }}</p>
                </x-tarjeta>
                <x-tarjeta class="space-y-1">
                    <p class="text-sm text-texto-suave">{{ __('estadisticas.resumen.aprobacion') }}</p>
                    <p class="text-2xl font-semibold text-primario">{{ $porcentaje($general['tasa_aprobacion']) }}</p>
                </x-tarjeta>
            </div>

            <p class="text-sm text-texto-suave">{{ __('estadisticas.resumen.nota') }}</p>
        </section>

        <section aria-labelledby="titulo-por-examen" class="space-y-4">
            <h2 id="titulo-por-examen" class="text-xl font-semibold text-primario">{{ __('estadisticas.examenes.encabezado') }}</h2>

            <x-grafica-barras
                :titulo="__('estadisticas.examenes.grafica')"
                :descripcion="__('estadisticas.examenes.grafica_ayuda')"
                :categorias="$graficaPorExamen['categorias']"
                :series="$graficaPorExamen['series']"
                :eje-x="__('estadisticas.examenes.eje_x')"
                :eje-y="__('estadisticas.eje_porcentaje')"
                unidad="%"
                :maximo="100"
                :fuente="__('estadisticas.fuente')"
            />

            <div class="space-y-4">
                <x-tabla :titulo="__('estadisticas.examenes.tabla.titulo')" :columnas="array_values(__('estadisticas.examenes.tabla.columnas'))">
                    @foreach ($examenes as $fila)
                        <x-tabla.fila>
                            <x-tabla.celda class="font-semibold">{{ $fila['nombre'] }}</x-tabla.celda>
                            <x-tabla.celda>{{ $nombresDeGrupos[$fila['grupo_id']] ?? '—' }}</x-tabla.celda>
                            <x-tabla.celda>{{ __('examenes.opciones.escala.'.$fila['escala']) }}</x-tabla.celda>
                            <x-tabla.celda>{{ $fila['intentos'] }}</x-tabla.celda>
                            <x-tabla.celda>{{ $fila['calificados'] }}</x-tabla.celda>
                            <x-tabla.celda>{{ $cifra($fila['promedio']) }}</x-tabla.celda>
                            <x-tabla.celda>{{ $cifra($fila['minimo']) }}</x-tabla.celda>
                            <x-tabla.celda>{{ $cifra($fila['maximo']) }}</x-tabla.celda>
                            <x-tabla.celda>{{ $porcentaje($fila['tasa_aprobacion']) }}</x-tabla.celda>
                        </x-tabla.fila>
                    @endforeach
                </x-tabla>

                {{ $examenes->links('pagination.panel') }}
            </div>
        </section>

        <section aria-labelledby="titulo-por-grupo" class="space-y-4">
            <h2 id="titulo-por-grupo" class="text-xl font-semibold text-primario">{{ __('estadisticas.grupos.encabezado') }}</h2>

            <x-grafica-barras
                :titulo="__('estadisticas.grupos.grafica')"
                :descripcion="__('estadisticas.grupos.grafica_ayuda')"
                :categorias="$graficaPorGrupo['categorias']"
                :series="$graficaPorGrupo['series']"
                :eje-x="__('estadisticas.grupos.eje_x')"
                :eje-y="__('estadisticas.eje_porcentaje')"
                unidad="%"
                :maximo="100"
                :fuente="__('estadisticas.fuente')"
            />

            <div class="space-y-4">
                <x-tabla :titulo="__('estadisticas.grupos.tabla.titulo')" :columnas="array_values(__('estadisticas.grupos.tabla.columnas'))">
                    @foreach ($grupos as $fila)
                        <x-tabla.fila>
                            <x-tabla.celda class="font-semibold">{{ $fila['nombre'] }}</x-tabla.celda>
                            <x-tabla.celda>{{ $fila['examenes'] }}</x-tabla.celda>
                            <x-tabla.celda>{{ $fila['intentos'] }}</x-tabla.celda>
                            <x-tabla.celda>{{ $fila['calificados'] }}</x-tabla.celda>
                            <x-tabla.celda>{{ $porcentaje($fila['promedio_porcentaje']) }}</x-tabla.celda>
                            <x-tabla.celda>{{ $porcentaje($fila['minimo_porcentaje']) }}</x-tabla.celda>
                            <x-tabla.celda>{{ $porcentaje($fila['maximo_porcentaje']) }}</x-tabla.celda>
                            <x-tabla.celda>{{ $porcentaje($fila['tasa_aprobacion']) }}</x-tabla.celda>
                        </x-tabla.fila>
                    @endforeach
                </x-tabla>

                {{ $grupos->links('pagination.panel') }}
            </div>
        </section>

        <section aria-labelledby="titulo-detalle-del-examen" class="space-y-4">
            <h2 id="titulo-detalle-del-examen" class="text-xl font-semibold text-primario">{{ __('estadisticas.detalle.encabezado') }}</h2>

            <x-tarjeta>
                <form method="GET" action="{{ route('panel.estadisticas') }}" class="flex flex-col gap-4 sm:flex-row sm:items-end">
                    <div class="w-full max-w-xl">
                        <x-campo-seleccion
                            nombre="examen"
                            :etiqueta="__('estadisticas.detalle.examen')"
                            :opciones="$opciones"
                            :valor="(string) $elegido['id']"
                        />
                    </div>

                    <x-boton type="submit" variante="secundario" icono="chart-bar" ancho="movil">{{ __('estadisticas.detalle.ver') }}</x-boton>
                </form>
            </x-tarjeta>

            @if ($elegido['calificados'] === 0)
                <x-tarjeta class="space-y-2">
                    <p>{{ __('estadisticas.vacio') }}</p>
                    <p class="text-sm text-texto-suave">{{ __('estadisticas.detalle.sin_notas') }}</p>
                </x-tarjeta>
            @else
                <x-grafica-barras
                    :titulo="__('estadisticas.detalle.distribucion', ['examen' => $elegido['nombre']])"
                    :descripcion="__('estadisticas.detalle.distribucion_ayuda')"
                    :categorias="$graficaDeDistribucion['categorias']"
                    :series="$graficaDeDistribucion['series']"
                    :eje-x="__('estadisticas.detalle.eje_rangos', ['escala' => __('examenes.opciones.escala.'.$elegido['escala'])])"
                    :eje-y="__('estadisticas.eje_alumnos')"
                    :fuente="__('estadisticas.fuente')"
                />

                @if ($preguntas->isEmpty())
                    <x-tarjeta>
                        <p>{{ __('estadisticas.detalle.sin_respuestas') }}</p>
                    </x-tarjeta>
                @else
                    <x-grafica-barras
                        :titulo="__('estadisticas.detalle.errores', ['examen' => $elegido['nombre']])"
                        :descripcion="__('estadisticas.detalle.errores_ayuda')"
                        :categorias="$graficaDeErrores['categorias']"
                        :series="$graficaDeErrores['series']"
                        :eje-x="__('estadisticas.detalle.eje_preguntas')"
                        :eje-y="__('estadisticas.eje_porcentaje')"
                        unidad="%"
                        :maximo="100"
                        :fuente="__('estadisticas.fuente_respuestas')"
                    />

                    <x-tabla :titulo="__('estadisticas.detalle.tabla.titulo')" :columnas="array_values(__('estadisticas.detalle.tabla.columnas'))">
                        @foreach ($preguntas as $pregunta)
                            <x-tabla.fila>
                                <x-tabla.celda class="font-semibold">{{ $pregunta['etiqueta'] }}</x-tabla.celda>
                                <x-tabla.celda>{{ \Illuminate\Support\Str::limit($pregunta['enunciado'], 120) }}</x-tabla.celda>
                                <x-tabla.celda>{{ $tipos[$pregunta['tipo']] ?? $pregunta['tipo'] }}</x-tabla.celda>
                                <x-tabla.celda>{{ $pregunta['respuestas'] }}</x-tabla.celda>
                                <x-tabla.celda>{{ $pregunta['reprobadas'] }}</x-tabla.celda>
                                <x-tabla.celda>{{ $porcentaje($pregunta['error']) }}</x-tabla.celda>
                            </x-tabla.fila>
                        @endforeach
                    </x-tabla>
                @endif
            @endif
        </section>
    @endif
</x-layouts.panel>
