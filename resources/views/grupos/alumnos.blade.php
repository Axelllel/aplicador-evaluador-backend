<x-layouts.panel titulo="Alumnos del grupo" descripcion="Consulta los alumnos inscritos.">
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm text-secundario">{{ $grupo['materia'] }}</p>
                <h2 class="text-2xl font-bold text-primario">Alumnos del grupo</h2>
            </div>
            <x-boton variante="secundario" :href="route('panel.grupos')">Volver a grupos</x-boton>
        </div>

        @if ($alumnos->isEmpty())
            <x-tarjeta>
                <p class="text-sm text-secundario">Aún no hay alumnos inscritos en este grupo.</p>
            </x-tarjeta>
        @else
            <x-tarjeta class="overflow-x-auto">
                <table class="w-full min-w-[420px] text-left text-sm">
                    <thead class="border-b border-linea text-secundario">
                        <tr>
                            <th class="px-3 py-3 font-semibold">Alumno</th>
                            <th class="px-3 py-3 font-semibold">Registro</th>
                            <th class="px-3 py-3 font-semibold">Exámenes entregados</th>
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
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-tarjeta>
        @endif
    </div>
</x-layouts.panel>
