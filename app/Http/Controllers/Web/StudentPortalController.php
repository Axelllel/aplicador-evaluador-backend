<?php

/**
 * Archivo: StudentPortalController.php
 * Módulo: app/Http/Controllers/Web
 * Descripción: Portal web del alumno: valida el folio, registra al alumno en el grupo, inicia el examen con el
 *              pase cifrado del docente y reenvía preguntas, respuestas y entrega a la API del alumno.
 * Autor: Axel (Axelllel)
 * Creado: 06/11/2026
 * Modificado: 09/11/2026 · Leonardo Ballestero López · Prólogo del manual y strict_types
 */

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\StudentJoinController;
use App\Http\Controllers\Api\StudentExamController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\View\View;
use Throwable;

/** Portal Blade: no usa credenciales de docente ni SQL directo desde el navegador. */
class StudentPortalController extends Controller
{
    private function student(Request $request): ?array
    {
        return $request->session()->get('bbb.student');
    }

    private function attempt(Request $request): ?array
    {
        return $request->session()->get('bbb.attempt');
    }

    public function entry(): View
    {
        return view('student.entry');
    }

    /** Comprueba un folio contra Supabase, sin crear alumnos ni mostrar datos privados. */
    public function validateFolio(Request $request): JsonResponse
    {
        $data = $request->validate([
            'folio_codigo' => ['required', 'string', 'regex:/\A[A-Za-z0-9]{4,20}\z/'],
        ]);
        try {
            $row = DB::selectOne(
                'SELECT public.fn_validar_folio_grupo(CAST(? AS text)) AS valido',
                [$data['folio_codigo']]
            );
        } catch (QueryException $e) {
            return response()->json(['message' => 'No fue posible verificar el grupo. Inténtalo más tarde.'], 503);
        }
        if (!$row || !$row->valido) {
            return response()->json(['message' => 'No encontramos un grupo activo con ese folio. Verifica el código o consulta a tu docente.'], 404);
        }
        return response()->json(['data' => ['valido' => true]], 200);
    }

    public function join(Request $request): JsonResponse
    {
        // Reutiliza exactamente la funcion / validacion de la API ya probada.
        $response = app(StudentJoinController::class)->store($request);
        if ($response->getStatusCode() !== 201) {
            return $response;
        }
        $data = $response->getData(true)['data'];
        $request->session()->regenerate();
        $request->session()->forget('bbb.attempt');
        $request->session()->put('bbb.student', [
            'alumno_id' => (int) $data['alumno_id'],
            'grupo_id' => (int) $data['grupo_id'],
            'materia' => (string) $data['materia'],
            'nombre' => (string) $request->input('nombre'),
        ]);
        return $response;
    }

    public function home(Request $request): View|RedirectResponse
    {
        if (!$this->student($request)) return redirect()->route('student.entry');
        return view('student.home', ['student' => $this->student($request), 'attempt' => $this->attempt($request)]);
    }

    public function start(Request $request): JsonResponse
    {
        $student = $this->student($request);
        if (!$student) return response()->json(['message'=>'Primero ingresa a un grupo.'], 401);
        if ($this->attempt($request)) return response()->json(['message'=>'Ya tienes un examen iniciado o entregado en esta sesión.'], 409);
        $data = $request->validate(['pase'=>['required','string','max:4096']]);
        try {
            $grant = json_decode(Crypt::decryptString($data['pase']), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            return response()->json(['message'=>'Pase inválido.'], 403);
        }
        // El pase debe estar emitido exactamente para el alumno de ESTA sesión.
        if (!is_array($grant) || ($grant['student'] ?? null) !== $student['alumno_id']) {
            return response()->json(['message'=>'El pase no corresponde a este alumno.'], 403);
        }
        $request->merge(['plataforma'=>'web']);
        $response = app(StudentExamController::class)->start($request);
        if ($response->getStatusCode() !== 201) return $response;
        $result = $response->getData(true)['data'];
        $request->session()->put('bbb.attempt', [
            'intento_id'=>(int) $result['intento_id'],
            'token_sesion'=>(string) $result['token_sesion'],
            'examen_id'=>(int) $grant['exam'],
            'fecha_inicio'=>(string) $result['fecha_inicio'],
            'finished'=>false,
        ]);
        return response()->json(['data'=>['intento_id'=>(int)$result['intento_id'],'url'=>route('student.exam')]], 201);
    }

    public function exam(Request $request): View|RedirectResponse
    {
        if (!$this->student($request)) return redirect()->route('student.entry');
        $attempt = $this->attempt($request);
        if (!$attempt) return redirect()->route('student.home');
        if ($attempt['finished']) return redirect()->route('student.grades');
        return view('student.exam', ['student'=>$this->student($request),'attempt'=>$attempt]);
    }

    private function invoke(Request $request, string $method, bool $mustBeOpen = true): JsonResponse
    {
        $attempt = $this->attempt($request);
        if (!$this->student($request) || !$attempt) return response()->json(['message'=>'Sesión de examen no disponible.'],401);
        if ($mustBeOpen && $attempt['finished']) return response()->json(['message'=>'Examen ya entregado.'],403);
        $request->merge(['token_sesion'=>$attempt['token_sesion']]);
        return app(StudentExamController::class)->{$method}($request);
    }

    public function questions(Request $request): JsonResponse
    {
        return $this->invoke($request, 'questions');
    }

    public function answer(Request $request): JsonResponse
    {
        return $this->invoke($request, 'answer');
    }

    public function finish(Request $request): JsonResponse
    {
        $response = $this->invoke($request, 'finish', false);
        if ($response->getStatusCode() === 200) {
            $attempt = $this->attempt($request);
            $attempt['finished']=true;
            $request->session()->put('bbb.attempt', $attempt);
        }
        return $response;
    }

    public function grades(Request $request): View|RedirectResponse
    {
        if (!$this->student($request)) return redirect()->route('student.entry');
        return view('student.grades', ['student'=>$this->student($request), 'attempt'=>$this->attempt($request)]);
    }

    public function result(Request $request): JsonResponse
    {
        $attempt=$this->attempt($request);
        if (!$attempt || !$attempt['finished']) return response()->json(['message'=>'El examen aún no ha sido entregado.'],403);
        // La funcion de la API oculta notas por decisión del docente.
        return $this->invoke($request, 'result', false);
    }

    public function leave(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('student.entry');
    }
}
