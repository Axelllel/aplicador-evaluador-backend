<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ReglasPregunta;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Banco de preguntas del docente. Se utiliza la misma identidad RLS instalada
 * por VerifySupabaseToken en el contexto de la transaccion HTTP.
 */
class TeacherQuestionController extends Controller
{
    private const TIPOS = ['opcion_multiple', 'relacion_columnas', 'abierta', 'programacion'];

    private static ?bool $tieneLenguaje = null;

    /**
     * La columna pregunta.lenguaje la crea la migración 20261124120000. Si el código se despliega antes de aplicarla,
     * el banco sigue funcionando sin lenguaje en lugar de fallar por completo.
     */
    private function tieneLenguaje(): bool
    {
        return self::$tieneLenguaje ??= Schema::hasColumn('pregunta', 'lenguaje');
    }

    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'tipo' => ['sometimes', Rule::in(self::TIPOS)],
        ]);
        $docenteId = $request->user()->getAuthIdentifier();

        $consulta = DB::table('pregunta')
            ->where('docente_id', $docenteId)
            ->select(array_merge(['id', 'tipo', 'enunciado', 'respuesta_modelo', 'opciones'], $this->tieneLenguaje() ? ['lenguaje'] : []))
            // Una pregunta asignada a un examen ya no se puede modificar ni eliminar.
            ->selectRaw('EXISTS (SELECT 1 FROM examen_pregunta ep WHERE ep.pregunta_id = pregunta.id) AS en_uso');

        if (isset($datos['tipo'])) {
            $consulta->where('tipo', $datos['tipo']);
        }

        $preguntas = $consulta->orderByDesc('id')->get()->map(function ($pregunta) {
            $pregunta->opciones = $pregunta->opciones === null
                ? null
                : json_decode($pregunta->opciones, true);
            $pregunta->en_uso = (bool) $pregunta->en_uso;

            return $pregunta;
        });

        return response()->json(['data' => $preguntas]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);
        $docenteId = $request->user()->getAuthIdentifier();

        return DB::transaction(function () use ($datos, $docenteId): JsonResponse {
            // Serializa operaciones sujetas a cuotas del mismo docente.
            DB::selectOne(
                'SELECT pg_advisory_xact_lock(hashtextextended(CAST(? AS text), 0))',
                [$docenteId]
            );

            $docente = DB::table('docente')
                ->where('id', $docenteId)
                ->first(['paquete_id', 'estado_pago']);

            if (!$docente || $docente->estado_pago !== 'activo' || !$docente->paquete_id) {
                return response()->json([
                    'message' => 'El docente no tiene un plan activo.',
                ], 403);
            }

            $paquete = DB::table('paquete')
                ->where('id', $docente->paquete_id)
                ->first(['max_preguntas']);

            if (!$paquete) {
                return response()->json(['message' => 'Plan no disponible.'], 403);
            }

            $pagoVigente = DB::table('pago')
                ->where('docente_id', $docenteId)
                ->where('paquete_id', $docente->paquete_id)
                ->where('estado', 'confirmado')
                ->whereRaw('fecha_inicio_vigencia <= CURRENT_TIMESTAMP')
                ->whereRaw('fecha_fin_vigencia > CURRENT_TIMESTAMP')
                ->exists();

            if (!$pagoVigente) {
                return response()->json([
                    'message' => 'No hay suscripcion confirmada y vigente.',
                ], 403);
            }

            $usoActual = DB::table('pregunta')
                ->where('docente_id', $docenteId)
                ->count();

            if ($usoActual >= (int) $paquete->max_preguntas) {
                return response()->json([
                    'status' => 'error',
                    'code' => 402,
                    'message' => 'Limite de preguntas alcanzado para el plan actual.',
                    'details' => [
                        'current_usage' => $usoActual,
                        'max_allowed' => (int) $paquete->max_preguntas,
                    ],
                ], 402);
            }

            $this->rechazarRepetida($docenteId, $datos, null);

            $nuevo = $this->fila($datos) + ['docente_id' => $docenteId];

            $id = DB::table('pregunta')->insertGetId($nuevo);

            return response()->json([
                'data' => $this->respuesta($id, $datos),
                'message' => 'Pregunta agregada al banco del docente.',
            ], 201);
        });
    }

    /**
     * Modifica una pregunta propia que todavía no forma parte de ningún examen.
     */
    public function update(Request $request, int $preguntaId): JsonResponse
    {
        $datos = $this->validar($request);
        $docenteId = $request->user()->getAuthIdentifier();

        return DB::transaction(function () use ($datos, $docenteId, $preguntaId): JsonResponse {
            DB::selectOne('SELECT pg_advisory_xact_lock(hashtextextended(CAST(? AS text), 0))', [$docenteId]);

            $bloqueo = $this->bloqueoDeEdicion($preguntaId, $docenteId);

            if ($bloqueo !== null) {
                return $bloqueo;
            }

            $this->rechazarRepetida($docenteId, $datos, $preguntaId);

            DB::table('pregunta')->where('id', $preguntaId)->where('docente_id', $docenteId)->update($this->fila($datos));

            return response()->json([
                'data' => $this->respuesta($preguntaId, $datos),
                'message' => 'Pregunta actualizada.',
            ]);
        });
    }

    /**
     * Elimina una pregunta propia que todavía no forma parte de ningún examen.
     */
    public function destroy(Request $request, int $preguntaId): JsonResponse
    {
        $docenteId = $request->user()->getAuthIdentifier();

        return DB::transaction(function () use ($docenteId, $preguntaId): JsonResponse {
            DB::selectOne('SELECT pg_advisory_xact_lock(hashtextextended(CAST(? AS text), 0))', [$docenteId]);

            $bloqueo = $this->bloqueoDeEdicion($preguntaId, $docenteId);

            if ($bloqueo !== null) {
                return $bloqueo;
            }

            try {
                DB::table('pregunta')->where('id', $preguntaId)->where('docente_id', $docenteId)->delete();
            } catch (QueryException $excepcion) {
                // Respaldo de la llave foránea (ON DELETE RESTRICT) si la pregunta se asignó mientras tanto.
                if (($excepcion->errorInfo[0] ?? '') === '23503') {
                    return $this->enUso();
                }

                throw $excepcion;
            }

            return response()->json(['data' => ['id' => $preguntaId]]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        $datos = $request->validate([
            'tipo' => ['required', Rule::in(self::TIPOS)],
            'enunciado' => ReglasPregunta::enunciado(),
            'respuesta_modelo' => ['nullable', 'string', 'max:20000'],
            'opciones' => ['nullable', 'array', 'max:'.ReglasPregunta::MAXIMO_DE_OPCIONES],
            'lenguaje' => ReglasPregunta::lenguaje(),
        ]);

        $opciones = $datos['opciones'] ?? [];

        // Evita preguntas de opcion multiple sin alternativas. El formato
        // definitivo del JSON de opciones debe acordarse con ambos clientes.
        if ($datos['tipo'] === 'opcion_multiple') {
            if (count($opciones) < 2) {
                throw ValidationException::withMessages(['opciones' => ['Proporciona dos o mas alternativas.']]);
            }

            if (ReglasPregunta::hayRepetidos($opciones)) {
                throw ValidationException::withMessages(['opciones' => [__('preguntas.validacion.opciones_repetidas')]]);
            }
        }

        if ($datos['tipo'] === 'relacion_columnas' && $opciones !== []) {
            if (ReglasPregunta::hayParesRepetidos($opciones)) {
                throw ValidationException::withMessages(['opciones' => [__('preguntas.validacion.pares_repetidos')]]);
            }
        }

        // Solo las preguntas de programacion llevan lenguaje.
        if ($datos['tipo'] !== 'programacion') {
            $datos['lenguaje'] = null;
        }

        return $datos;
    }

    /**
     * Rechaza una pregunta cuyo enunciado ya existe en el banco del docente para el mismo tipo.
     *
     * @param  array<string, mixed>  $datos
     */
    private function rechazarRepetida(string $docenteId, array $datos, ?int $excepto): void
    {
        $consulta = DB::table('pregunta')
            ->where('docente_id', $docenteId)
            ->where('tipo', $datos['tipo'])
            ->whereRaw("lower(regexp_replace(btrim(enunciado), '\\s+', ' ', 'g')) = ?", [ReglasPregunta::normalizar($datos['enunciado'])]);

        if ($excepto !== null) {
            $consulta->where('id', '!=', $excepto);
        }

        if ($consulta->exists()) {
            throw ValidationException::withMessages(['enunciado' => [__('preguntas.validacion.enunciado_repetido')]]);
        }
    }

    /**
     * Respuesta de error si la pregunta no existe o ya está en un examen; null si se puede modificar.
     */
    private function bloqueoDeEdicion(int $preguntaId, string $docenteId): ?JsonResponse
    {
        $existe = DB::table('pregunta')->where('id', $preguntaId)->where('docente_id', $docenteId)->exists();

        if (!$existe) {
            return response()->json(['message' => 'Pregunta no disponible.'], 404);
        }

        $enUso = DB::table('examen_pregunta')->where('pregunta_id', $preguntaId)->exists();

        return $enUso ? $this->enUso() : null;
    }

    private function enUso(): JsonResponse
    {
        return response()->json(['message' => __('preguntas.validacion.en_uso')], 409);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function fila(array $datos): array
    {
        $fila = [
            'tipo' => $datos['tipo'],
            'enunciado' => $datos['enunciado'],
            'respuesta_modelo' => $datos['respuesta_modelo'] ?? null,
            'opciones' => isset($datos['opciones'])
                ? json_encode($datos['opciones'], JSON_THROW_ON_ERROR)
                : null,
        ];

        return $this->tieneLenguaje() ? $fila + ['lenguaje' => $datos['lenguaje'] ?? null] : $fila;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function respuesta(int $id, array $datos): array
    {
        return [
            'id' => $id,
            'tipo' => $datos['tipo'],
            'enunciado' => $datos['enunciado'],
            'respuesta_modelo' => $datos['respuesta_modelo'] ?? null,
            'opciones' => $datos['opciones'] ?? null,
            'lenguaje' => $datos['lenguaje'] ?? null,
        ];
    }
}
