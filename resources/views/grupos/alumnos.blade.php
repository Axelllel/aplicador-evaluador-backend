<x-layouts.panel titulo="Alumnos del grupo" descripcion="Administra el acceso de tus estudiantes.">
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm text-secundario">{{ $grupo['materia'] }}</p>
                <h2 class="text-2xl font-bold text-primario">Alumnos del grupo</h2>
                <p class="mt-1 text-sm text-secundario">Asigna un código personal a los alumnos ya inscritos para que puedan recuperar su perfil y resultados.</p>
            </div>
            <x-boton variante="secundario" :href="route('panel.grupos')">Volver a grupos</x-boton>
        </div>

        @if (session('codigo_alumno'))
            @php
                $codigoEmitido = session('codigo_alumno');
                $alumnoDelCodigo = $alumnos->firstWhere('id', $codigoEmitido['alumno_id']);
            @endphp
            <section class="rounded-xl border border-emerald-300 bg-emerald-50 p-5 text-emerald-950" role="status">
                <p class="font-semibold">Código generado{{ $alumnoDelCodigo ? ' para '.$alumnoDelCodigo->nombre : '' }}.</p>
                <p class="mt-1 text-sm">Entrégaselo al alumno. Si ya tenía otro código, el anterior dejó de funcionar.</p>
                <p class="mt-3 select-all font-mono text-2xl font-bold tracking-[0.25em]">{{ $codigoEmitido['codigo'] }}</p>
                <p class="mt-2 text-xs">El código no volverá a mostrarse después de salir de esta página.</p>
            </section>
        @endif

        @if ($alumnos->isEmpty())
            <x-tarjeta>
                <p class="text-sm text-secundario">Aún no hay alumnos inscritos en este grupo.</p>
            </x-tarjeta>
        @else
            <x-tarjeta class="overflow-x-auto">
                <table class="w-full min-w-[700px] text-left text-sm">
                    <thead class="border-b border-linea text-secundario">
                        <tr>
                            <th class="px-3 py-3 font-semibold">Alumno</th>
                            <th class="px-3 py-3 font-semibold">Registro</th>
                            <th class="px-3 py-3 font-semibold">Exámenes entregados</th>
                            <th class="px-3 py-3 font-semibold">Acceso</th>
                            <th class="px-3 py-3 text-right font-semibold">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-linea">
                        @foreach ($alumnos as $alumno)
                            <tr>
                                <td class="px-3 py-4">
                                    <span class="block font-semibold text-primario">{{ $alumno->nombre }}</span>
                                    <span class="text-xs text-secundario">Registro #{{ $alumno->id }}</span>
                                </td>
                                <td class="px-3 py-4 text-secundario">{{ \Illuminate\Support\Carbon::parse($alumno->fecha_union)->format('d/m/Y') }}</td>
                                <td class="px-3 py-4 text-secundario">{{ $alumno->intentos_en_grupo }}</td>
                                <td class="px-3 py-4">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $alumno->tiene_codigo ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900' }}">
                                        {{ $alumno->tiene_codigo ? 'Código asignado' : 'Pendiente' }}
                                    </span>
                                </td>
                                <td class="px-3 py-4 text-right">
                                    <form method="POST" action="{{ route('panel.grupos.alumnos.codigo', [$grupo['id'], $alumno->id]) }}">
                                        @csrf
                                        <button class="min-h-11 rounded-lg border border-primario px-3 font-semibold text-primario hover:bg-superficie" type="submit">
                                            {{ $alumno->tiene_codigo ? 'Renovar PIN' : 'Asignar PIN' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-tarjeta>
        @endif
    </div>
</x-layouts.panel>
