<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class VerifyStudentSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $student = $request->session()->get('bbb.student');
        if (! $student) {
            return $next($request);
        }
        try {
            $row = DB::selectOne('SELECT public.fn_version_credencial_alumno(?::bigint) AS version', [$student['alumno_id']], false);
        } catch (QueryException $exception) {
            return $request->expectsJson()
                ? response()->json(['message' => 'No se pudo verificar tu sesión. Intenta más tarde.'], 503)
                : response('No se pudo verificar tu sesión. Intenta más tarde.', 503);
        }
        if (! isset($student['credencial_version'], $row->version) || (int) $row->version !== (int) $student['credencial_version']) {
            $request->session()->forget('bbb');
            $request->session()->regenerate();

            return $request->expectsJson()
                ? response()->json(['message' => 'Tu sesión expiró. Ingresa nuevamente con tu código.', 'code' => 'SESSION_REVOKED'], 401)
                : redirect()->route('student.entry')->with('status', 'Ingresa nuevamente con tu código.');
        }

        return $next($request);
    }
}
