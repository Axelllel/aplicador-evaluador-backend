<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CredencialAlumnoService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentDashboardController extends Controller
{
    public function show(Request $request, CredencialAlumnoService $credenciales): JsonResponse
    {
        $datos = $request->validate([
            'folio_codigo' => ['required', 'string', 'regex:/\A[A-Za-z0-9]{4,20}\z/'],
            'codigo_acceso' => ['required', 'string', 'regex:/\A[A-Za-z]{4}[0-9]{2}\z/'],
        ]);

        try {
            $fila = DB::selectOne(
                'SELECT public.fn_panel_alumno_movil(CAST(? AS text), CAST(? AS text)) AS data',
                [mb_strtoupper($datos['folio_codigo']), $credenciales->hashCodigo($datos['codigo_acceso'])],
                false,
            );
        } catch (QueryException $exception) {
            $estado = match ($exception->errorInfo[0] ?? null) {
                'P1004' => 401,
                'P1002' => 404,
                default => 503,
            };
            if ($estado === 503) {
                report($exception);
            }

            return response()->json(['message' => match ($estado) {
                401 => 'El código personal no es válido.',
                404 => 'El grupo no está disponible.',
                default => 'No se pudieron cargar tus datos. Inténtalo de nuevo.',
            }], $estado);
        }

        return response()->json(['data' => json_decode($fila->data, true, 512, JSON_THROW_ON_ERROR)]);
    }
}
