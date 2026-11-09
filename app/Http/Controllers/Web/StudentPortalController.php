<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\StudentJoinController;
use App\Http\Controllers\Api\StudentExamController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\View\View;

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

    private function completedAttempts(Request $request): array
    {
        $completed = $request->session()->get('bbb.completed_attempts', []);
        $current = $this->attempt($request);
        if ($current && $current['finished']) {
            $completed[$current['intento_id']] = $current;
        }

        return array_reverse($completed, true);
    }

    private function recentResults(array $completed): array
    {
        return array_map(function (array $attempt): array {
            $result = [];
            try {
                $function = isset($attempt['nombre'], $attempt['fecha_entrega'])
                    ? 'fn_resultado_alumno'
                    : 'fn_revision_portal_alumno';
                try {
                    $row = DB::selectOne("SELECT public.{$function}(CAST(? AS uuid)) AS data", [$attempt['token_sesion']]);
                } catch (QueryException $exception) {
                    if ($function !== 'fn_revision_portal_alumno' || ($exception->errorInfo[0] ?? null) !== '42883') {
                        throw $exception;
                    }
                    $row = DB::selectOne('SELECT public.fn_resultado_alumno(CAST(? AS uuid)) AS data', [$attempt['token_sesion']]);
                }
                $result = json_decode($row->data, true) ?? [];
            } catch (QueryException $exception) {
                if (($exception->errorInfo[0] ?? null) !== 'P0001') {
                    report($exception);
                }
            }

            return [
                'intento_id' => $attempt['intento_id'],
                'nombre' => $attempt['nombre'] ?? $result['nombre'] ?? 'Examen #'.$attempt['examen_id'],
                'fecha_inicio' => $attempt['fecha_inicio'],
                'fecha_entrega' => $attempt['fecha_entrega'] ?? $result['fecha_entrega'] ?? null,
                'calificacion' => $result['calificacion'] ?? null,
                'escala' => $result['escala'] ?? null,
                'estado' => $result['estado'] ?? null,
            ];
        }, array_slice($completed, 0, 3));
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
        $request->session()->forget(['bbb.attempt', 'bbb.completed_attempts']);
        $request->session()->put('bbb.student', [
            'alumno_id' => (int) $data['alumno_id'],
            'grupo_id' => (int) $data['grupo_id'],
            'materia' => (string) $data['materia'],
            'nombre' => (string) $request->input('nombre'),
            'folio_codigo' => mb_strtoupper((string) $request->input('folio_codigo')),
        ]);
        return $response;
    }

    public function home(Request $request): View|RedirectResponse
    {
        $student = $this->student($request);
        if (!$student) {
            return redirect()->route('student.entry');
        }

        try {
            $exams = $this->examsForStudent($student);
        } catch (QueryException $exception) {
            report($exception);
            $exams = null;
        }

        $attempt = $this->attempt($request);
        $completed = $this->completedAttempts($request);
        $excluded = array_column($completed, 'examen_id');
        if ($attempt) {
            $excluded[] = $attempt['examen_id'];
        }
        $activeAttempt = $attempt && !$attempt['finished'] ? $attempt : null;
        $pendingExams = $exams === null ? null : array_values(array_filter(
            $exams,
            fn ($exam) => !in_array((int) $exam->examen_id, $excluded, true),
        ));
        $completedCount = count($completed);
        $recentResults = $this->recentResults($completed);

        return view('student.home', compact('student', 'activeAttempt', 'pendingExams', 'completedCount', 'recentResults'));
    }

    private function examsForStudent(array $student): array
    {
        $params = [$student['alumno_id'], $student['grupo_id']];

        try {
            return DB::select('SELECT * FROM public.fn_examenes_portal_alumno_detalles(?, ?)', $params);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) !== '42883') {
                throw $exception;
            }

            // Permite desplegar la vista antes de aplicar la migración en Supabase.
            return DB::select('SELECT * FROM public.fn_examenes_portal_alumno(?, ?)', $params);
        }
    }

    public function start(Request $request): JsonResponse
    {
        $student = $this->student($request);
        if (!$student) return response()->json(['message'=>'Primero ingresa a un grupo.'], 401);
        $previous = $this->attempt($request);
        if ($previous && !$previous['finished']) {
            return response()->json(['message' => 'Entrega el examen en curso antes de iniciar otro.'], 409);
        }
        $data = $request->validate(['examen_id' => ['required', 'integer', 'min:1']]);
        $examId = (int) $data['examen_id'];

        // Los identificadores del alumno y su grupo provienen de la sesión creada al validar el folio.
        try {
            $result = DB::selectOne(
                <<<'SQL'
                WITH examen_autorizado AS MATERIALIZED (
                    SELECT examen_id, nombre
                    FROM public.fn_examenes_portal_alumno(?, ?)
                    WHERE examen_id = ? AND disponible
                )
                SELECT intento.*, e.nombre
                FROM examen_autorizado e
                CROSS JOIN LATERAL public.fn_iniciar_intento(e.examen_id, ?, 'web') intento
                SQL,
                [$student['alumno_id'], $student['grupo_id'], $examId, $student['alumno_id']],
                false,
            );
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === 'P0001') {
                return response()->json(['message' => 'El examen no está disponible o ya tienes un intento registrado.'], 403);
            }

            report($exception);

            return response()->json(['message' => 'No se pudo iniciar el examen. Inténtalo de nuevo.'], 503);
        }

        if ($result === null) {
            return response()->json(['message' => 'Este examen no está disponible para tu grupo.'], 403);
        }

        if ($previous) {
            $request->session()->put('bbb.completed_attempts.'.$previous['intento_id'], $previous);
        }
        $request->session()->put('bbb.attempt', [
            'intento_id' => (int) $result->intento_id,
            'token_sesion' => (string) $result->token_sesion,
            'examen_id' => $examId,
            'nombre' => $result->nombre,
            'fecha_inicio' => (string) $result->fecha_inicio,
            'finished' => false,
        ]);

        return response()->json(['data' => ['intento_id' => (int) $result->intento_id, 'url' => route('student.exam')]], 201);
    }

    public function exam(Request $request): View|RedirectResponse
    {
        if (!$this->student($request)) return redirect()->route('student.entry');
        $attempt = $this->attempt($request);
        if (!$attempt) return redirect()->route('student.home');
        if ($attempt['finished']) return redirect()->route('student.grades');
        return view('student.exam', ['student'=>$this->student($request),'attempt'=>$attempt]);
    }

    private function invoke(Request $request, string $method, bool $mustBeOpen = true, ?string $query = null, ?array $attempt = null): JsonResponse
    {
        $attempt ??= $this->attempt($request);
        if (!$this->student($request) || !$attempt) return response()->json(['message'=>'Sesión de examen no disponible.'],401);
        if ($mustBeOpen && $attempt['finished']) return response()->json(['message'=>'Examen ya entregado.'],403);
        $request->merge(['token_sesion'=>$attempt['token_sesion']]);
        if ($query !== null) {
            try {
                $row = DB::selectOne($query, [$attempt['token_sesion']]);

                return response()->json(['data' => json_decode($row->data, true)]);
            } catch (QueryException $exception) {
                $state = $exception->errorInfo[0] ?? null;
                if ($state === 'P0001') {
                    return response()->json(['message' => 'El examen o el intento ya no está disponible.'], 403);
                }
                if ($state !== '42883') {
                    report($exception);

                    return response()->json(['message' => 'No se pudo consultar el examen. Inténtalo de nuevo.'], 503);
                }
            }
        }
        return app(StudentExamController::class)->{$method}($request);
    }

    public function questions(Request $request): JsonResponse
    {
        $response = $this->invoke($request, 'questions', query: 'SELECT public.fn_preguntas_portal_alumno(CAST(? AS uuid)) AS data');
        if ($response->getStatusCode() !== 200) {
            return $response;
        }

        $data = $response->getData(true)['data'];
        foreach ($data['preguntas'] as &$question) {
            $options = $question['opciones'];
            if ($question['tipo'] !== 'relacion_columnas' || !is_array($options)
                || !array_is_list($options) || !isset($options[0]['izquierda'], $options[0]['derecha'])) {
                continue;
            }

            $choices = array_values(array_unique(array_column($options, 'derecha')));
            sort($choices, SORT_NATURAL | SORT_FLAG_CASE);
            $question['opciones'] = ['izquierda' => array_column($options, 'izquierda'), 'derecha' => $choices];
        }
        unset($question);

        return response()->json(['data' => $data]);
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
            $attempt['finished'] = true;
            $attempt['fecha_entrega'] = $response->getData(true)['data']['fecha_entrega'];
            $request->session()->put('bbb.completed_attempts.'.$attempt['intento_id'], $attempt);
            $request->session()->put('bbb.attempt', $attempt);
        }
        return $response;
    }

    public function grades(Request $request): View|RedirectResponse
    {
        $student = $this->student($request);
        if (!$student) return redirect()->route('student.entry');

        $attempt = $this->selectedResult($request) ?? $this->attempt($request);

        return view('student.grades', compact('student', 'attempt'));
    }

    public function result(Request $request): JsonResponse
    {
        if (!$this->student($request)) {
            return response()->json(['message' => 'Sesión del alumno no disponible.'], 401);
        }
        $attempt = $this->selectedResult($request);
        if (!$attempt || !$attempt['finished']) return response()->json(['message'=>'El examen aún no ha sido entregado.'],403);
        // La funcion de la API oculta notas por decisión del docente.
        return $this->invoke($request, 'result', false, 'SELECT public.fn_revision_portal_alumno(CAST(? AS uuid)) AS data', $attempt);
    }

    private function selectedResult(Request $request): ?array
    {
        $completed = $this->completedAttempts($request);
        if (!$request->has('intento')) {
            return reset($completed) ?: null;
        }

        $data = $request->validate(['intento' => ['required', 'integer', 'min:1']]);

        return $completed[(int) $data['intento']] ?? abort(404);
    }

    public function leave(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('student.entry');
    }
}
