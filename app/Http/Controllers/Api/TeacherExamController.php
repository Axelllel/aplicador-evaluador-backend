<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ModificaExamenBorrador;
use App\Http\Controllers\Controller;
use App\Support\ReglasExamen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Examenes en borrador del docente autenticado.
 * La identidad llega del middleware VerifySupabaseToken y su transaccion RLS.
 */
class TeacherExamController extends Controller
{
    use ModificaExamenBorrador;

    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'grupo_id' => ['sometimes', 'integer', 'min:1'],
        ]);

        $docenteId = $request->user()->getAuthIdentifier();

        $consulta = DB::table('examen as e')
            ->join('grupo as g', 'g.id', '=', 'e.grupo_id')
            ->where('g.docente_id', $docenteId)
            ->select([
                'e.id', 'e.grupo_id', 'e.nombre',
                'e.fecha_apertura', 'e.fecha_cierre',
                'e.orden', 'e.escala', 'e.plataforma_permitida',
                'e.publicado', 'e.resultado_visible',
            ]);

        if (isset($datos['grupo_id'])) {
            $consulta->where('e.grupo_id', $datos['grupo_id']);
        }

        return response()->json(['data' => $consulta->orderByDesc('e.id')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'grupo_id' => ['required', 'integer', 'min:1'],
            'nombre' => ReglasExamen::nombre(),
            'fecha_apertura' => ReglasExamen::apertura('UTC'),
            'fecha_cierre' => ReglasExamen::cierre('UTC'),
            'orden' => ['required', Rule::in(['aleatorio', 'fijo'])],
            'escala' => ['required', Rule::in(['0-10', '0-100'])],
            'plataforma_permitida' => ['required', Rule::in(['web', 'movil', 'ambas'])],
            'resultado_visible' => ['sometimes', Rule::in(['final', 'aproximado', 'oculto'])],
        ]);

        $docenteId = $request->user()->getAuthIdentifier();

        return DB::transaction(function () use ($datos, $docenteId): JsonResponse {
            // Prohibir crear examenes en grupos de otro docente o inactivos.
            $grupo = DB::table('grupo')
                ->where('id', $datos['grupo_id'])
                ->where('docente_id', $docenteId)
                ->where('activo', true)
                ->first(['id']);

            if (!$grupo) {
                return response()->json(['message' => 'Grupo no disponible.'], 404);
            }

            // Un candado por docente evita duplicados por doble clic o peticiones simultaneas.
            DB::selectOne('SELECT pg_advisory_xact_lock(hashtextextended(CAST(? AS text), 0))', ['examen:'.$docenteId]);

            $nombre = trim($datos['nombre']);
            $repetido = DB::table('examen')
                ->where('grupo_id', $grupo->id)
                ->whereRaw('lower(btrim(nombre)) = ?', [mb_strtolower($nombre)])
                ->exists();

            if ($repetido) {
                throw ValidationException::withMessages(['nombre' => [__('examenes.errores.nombre_repetido')]]);
            }

            // El manual condiciona la gestion del sistema a un plan vigente.
            $docente = DB::table('docente')
                ->where('id', $docenteId)
                ->first(['paquete_id', 'estado_pago']);

            if (!$docente || $docente->estado_pago !== 'activo' || !$docente->paquete_id) {
                return response()->json([
                    'message' => 'El docente no tiene un plan activo.',
                ], 403);
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
                    'message' => 'No hay una suscripcion confirmada y vigente.',
                ], 403);
            }

            // No aceptar 'publicado', 'docente_id' ni grupo alterno del cliente.
            // Se crea como borrador; publicar requiere preguntas/apartados validos.
            $nuevo = [
                'grupo_id' => $grupo->id,
                'nombre' => $nombre,
                'fecha_apertura' => $datos['fecha_apertura'],
                'fecha_cierre' => $datos['fecha_cierre'],
                'orden' => $datos['orden'],
                'escala' => $datos['escala'],
                'plataforma_permitida' => $datos['plataforma_permitida'],
                'resultado_visible' => $datos['resultado_visible'] ?? 'oculto',
                'publicado' => false,
            ];

            $nuevoId = DB::table('examen')->insertGetId($nuevo);

            return response()->json([
                'data' => ['id' => $nuevoId] + $nuevo,
                'message' => 'Examen creado como borrador. Aun no esta publicado.',
            ], 201);
        });
    }

    public function destroy(Request $request, int $examenId): JsonResponse
    {
        $docenteId = (string) $request->user()->getAuthIdentifier();

        return $this->modificarBorrador($examenId, $docenteId, function () use ($examenId): JsonResponse {
            DB::table('examen')->where('id', $examenId)->delete();

            return response()->json(['data' => ['id' => $examenId]]);
        });
    }
}
