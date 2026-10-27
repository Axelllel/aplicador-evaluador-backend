<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentJoinController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // Unirse al grupo NO equivale a autenticarse ni iniciar examen.
        $datos = $request->validate([
            'folio_codigo' => ['required', 'string', 'regex:/\A[A-Za-z0-9]{4,20}\z/'],
            'nombre' => ['required', 'string', 'min:2', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:20'],
        ]);

        try {
            // El acceso anonimo no tiene app.docente_id: esta funcion
            // controlada es la UNICA via para insertar alumno/alumno_grupo.
            $registro = DB::selectOne(
                'SELECT alumno_id, grupo_id, materia FROM public.fn_unirse_grupo(CAST(? AS text), CAST(? AS text), CAST(? AS text))',
                [
                    $datos['folio_codigo'],
                    $datos['nombre'],
                    $datos['telefono'] ?? null,
                ]
            );
        } catch (QueryException $e) {
            $sqlstate = $e->errorInfo[0] ?? null;

            if ($sqlstate === 'P1002') {
                return response()->json([
                    'message' => 'Folio invalido o grupo no disponible.',
                ], 404);
            }

            if ($sqlstate === 'P1001') {
                return response()->json([
                    'status' => 'error',
                    'code' => 402,
                    'message' => 'Limite de alumnos alcanzado para este grupo.',
                ], 402);
            }

            if ($sqlstate === 'P1003') {
                return response()->json([
                    'message' => 'Datos de inscripcion invalidos.',
                ], 422);
            }

            Log::error('Error al registrar alumno en un grupo', [
                'sqlstate' => $sqlstate,
            ]);

            return response()->json([
                'message' => 'No se pudo completar la inscripcion.',
            ], 500);
        }

        return response()->json([
            'data' => [
                'alumno_id' => (int) $registro->alumno_id,
                'grupo_id' => (int) $registro->grupo_id,
                'materia' => $registro->materia,
                'estado' => 'inscrito',
            ],
            'message' => 'Inscripcion registrada. No se ha autorizado ningun examen.',
        ], 201);
    }
}
