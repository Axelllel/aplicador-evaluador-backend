<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CredencialAlumnoService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentJoinController extends Controller
{
    public function store(Request $request, CredencialAlumnoService $credenciales): JsonResponse
    {
        // Sin PIN se trata como alta nueva y se devuelve el PIN una sola vez.
        // Con PIN, se recupera el alumno ya existente y se vincula al folio.
        $datos = $request->validate([
            'folio_codigo' => ['required', 'string', 'regex:/\A[A-Za-z0-9]{4,20}\z/'],
            'nombre' => ['required_without:codigo_acceso', 'nullable', 'string', 'min:2', 'max:150'],
            'codigo_acceso' => ['sometimes', 'nullable', 'string', 'regex:/\A[A-Za-z]{4}[0-9]{2}\z/'],
        ]);

        $folio = mb_strtoupper(trim($datos['folio_codigo']));
        $codigoIngresado = isset($datos['codigo_acceso'])
            ? mb_strtoupper(trim($datos['codigo_acceso']))
            : null;
        $esReingreso = $codigoIngresado !== null && $codigoIngresado !== '';

        if ($esReingreso) {
            try {
                $registro = DB::selectOne(
                    'SELECT alumno_id, grupo_id, materia, nombre FROM public.fn_ingresar_alumno_con_pin(CAST(? AS text), CAST(? AS text))',
                    [$folio, $credenciales->hashCodigo($codigoIngresado)]
                );
            } catch (QueryException $e) {
                return $this->errorDeInscripcion($e);
            }

            return response()->json([
                'data' => [
                    'alumno_id' => (int) $registro->alumno_id,
                    'grupo_id' => (int) $registro->grupo_id,
                    'materia' => $registro->materia,
                    'nombre' => $registro->nombre,
                    'estado' => 'inscrito',
                    'modo' => 'reingreso',
                ],
                'message' => 'Ingresaste con el mismo perfil de alumno.',
            ], 200);
        }

        $nombre = preg_replace('/\s+/u', ' ', trim((string) ($datos['nombre'] ?? '')));
        // La restriccion UNIQUE vive en Supabase. Si se produce una colision
        // aleatoria, Laravel genera otro PIN sin crear filas parciales.
        for ($intento = 0; $intento < 5; $intento++) {
            $codigo = $credenciales->generarCodigo();
            try {
                $registro = DB::selectOne(
                    'SELECT alumno_id, grupo_id, materia, nombre FROM public.fn_crear_alumno_con_pin(CAST(? AS text), CAST(? AS text), CAST(? AS text))',
                    [$folio, $nombre, $credenciales->hashCodigo($codigo)]
                );
                break;
            } catch (QueryException $e) {
                if (($e->errorInfo[0] ?? null) === '23505' && $intento < 4) {
                    continue;
                }
                return $this->errorDeInscripcion($e);
            }
        }

        if (!isset($registro)) {
            return response()->json(['message' => 'No se pudo generar un código de acceso. Inténtalo nuevamente.'], 503);
        }

        return response()->json([
            'data' => [
                'alumno_id' => (int) $registro->alumno_id,
                'grupo_id' => (int) $registro->grupo_id,
                'materia' => $registro->materia,
                'nombre' => $registro->nombre,
                'estado' => 'inscrito',
                'modo' => 'nuevo',
                // Nunca se persiste ni se vuelve a consultar el texto del PIN.
                'codigo_acceso' => $codigo,
            ],
            'message' => 'Perfil creado. Guarda tu código de acceso; solo se muestra ahora.',
        ], 201);
    }

    private function errorDeInscripcion(QueryException $e): JsonResponse
    {
        $sqlstate = $e->errorInfo[0] ?? null;
        $status = match ($sqlstate) {
            'P1002' => 404,
            'P1001' => 402,
            'P1003' => 422,
            'P1004' => 401,
            default => 500,
        };

        if ($status === 500) {
            Log::error('Error al ingresar alumno con PIN', ['sqlstate' => $sqlstate]);
            return response()->json(['message' => 'No se pudo completar el ingreso. Inténtalo más tarde.'], 500);
        }

        $message = match ($sqlstate) {
            'P1002' => 'Folio inválido o grupo no disponible.',
            'P1001' => 'El grupo alcanzó su límite de alumnos.',
            'P1003' => 'Revisa el folio, tu nombre o el formato del código.',
            'P1004' => 'No reconocimos la combinación de folio y código. Revisa el código que guardaste al registrarte.',
        };

        return response()->json(['message' => $message], $status);
    }
}
