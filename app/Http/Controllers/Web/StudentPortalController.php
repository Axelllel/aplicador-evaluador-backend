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
        $attempt = $request->session()->get('bbb.attempt');

        return $attempt ? $attempt + ['grupo_id' => $this->student($request)['grupo_id'] ?? null] : null;
    }

    private function groups(Request $request, array $student): array
    {
        return [$student['grupo_id'] => $student] + $request->session()->get('bbb.groups', []);
    }

    private function currentGroup(Request $request, array $student): array
    {
        return $this->groups($request, $student)[$request->session()->get('bbb.selected_group')] ?? $student;
    }

    private function groupForRequest(Request $request, array $student): ?array
    {
        $data = $request->validate(['grupo' => ['sometimes', 'nullable', 'integer', 'min:1']]);
        if (!isset($data['grupo'])) {
            return null;
        }

        return $this->groups($request, $student)[(int) $data['grupo']] ?? abort(404);
    }

    private function completedAttempts(Request $request, ?int $attemptId = null): array
    {
        $student = $this->student($request);
        if (!$student) {
            return [];
        }

        try {
            $rows = DB::select('SELECT * FROM public.fn_entregas_portal_alumno(CAST(? AS bigint), CAST(? AS bigint))', [
                $student['alumno_id'], $attemptId,
            ]);
            $completed = [];
            foreach ($rows as $row) {
                $completed[(int) $row->intento_id] = [
                    'intento_id' => (int) $row->intento_id,
                    'examen_id' => (int) $row->examen_id,
                    'grupo_id' => (int) $row->grupo_id,
                    'nombre' => $row->nombre,
                    'materia' => $row->materia,
                    'resultado_visible' => $row->resultado_visible,
                    'fecha_inicio' => $row->fecha_inicio,
                    'fecha_entrega' => $row->fecha_entrega,
                    'token_sesion' => $row->token_sesion,
                    'finished' => true,
                ];
            }

            return $completed;
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) !== '42883') {
                report($exception);
            }
        }

        // Compatibilidad durante el despliegue de la función de historial.
        $completed = $request->session()->get('bbb.completed_attempts', []);
        $current = $this->attempt($request);
        if ($current && $current['finished']) {
            $completed[$current['intento_id']] = $current;
        }

        return array_map(
            fn (array $attempt) => $attempt + ['grupo_id' => $student['grupo_id']],
            array_reverse($completed, true),
        );
    }

    private function resultCards(array $completed, array $groups): array
    {
        return array_map(function (array $attempt) use ($groups): array {
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
                'grupo_id' => $attempt['grupo_id'],
                'materia' => $attempt['materia'] ?? $groups[$attempt['grupo_id']]['materia'] ?? null,
                'nombre' => $attempt['nombre'] ?? $result['nombre'] ?? 'Examen #'.$attempt['examen_id'],
                'fecha_inicio' => $attempt['fecha_inicio'],
                'fecha_entrega' => $attempt['fecha_entrega'] ?? $result['fecha_entrega'] ?? null,
                'calificacion' => $result['calificacion'] ?? null,
                'escala' => $result['escala'] ?? null,
                'estado' => $result['estado'] ?? null,
                'estado_filtro' => match (true) {
                    ($result['estado'] ?? null) === 'final' => 'revision_completada',
                    ($attempt['resultado_visible'] ?? null) === 'oculto' => 'sin_publicar',
                    !isset($attempt['resultado_visible']) && !isset($result['estado']) => 'sin_publicar',
                    default => 'en_revision',
                },
            ];
        }, $completed);
    }

    public function entry(Request $request): View
    {
        return view('student.entry', ['enrolledStudent' => $this->student($request)]);
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
        $student = $this->student($request);
        if ($student) {
            return $this->joinAdditionalGroup($request, $student);
        }

        // Reutiliza exactamente la funcion / validacion de la API ya probada.
        $response = app(StudentJoinController::class)->store($request);
        if ($response->getStatusCode() !== 201) {
            return $response;
        }
        $data = $response->getData(true)['data'];
        $request->session()->regenerate();
        $request->session()->forget(['bbb.attempt', 'bbb.completed_attempts', 'bbb.groups', 'bbb.selected_group']);
        $request->session()->put('bbb.student', [
            'alumno_id' => (int) $data['alumno_id'],
            'grupo_id' => (int) $data['grupo_id'],
            'materia' => (string) $data['materia'],
            'nombre' => (string) $request->input('nombre'),
            'folio_codigo' => mb_strtoupper((string) $request->input('folio_codigo')),
        ]);
        $payload = $response->getData(true);
        $payload['data']['home_url'] = route('student.home');

        return $response->setData($payload);
    }

    private function joinAdditionalGroup(Request $request, array $student): JsonResponse
    {
        $data = $request->validate(['folio_codigo' => ['required', 'string', 'regex:/\A[A-Za-z0-9]{4,20}\z/']]);
        $folio = mb_strtoupper($data['folio_codigo']);

        try {
            $group = DB::selectOne('SELECT * FROM public.fn_unirse_otro_grupo(CAST(? AS text), CAST(? AS bigint))', [
                $folio, $student['alumno_id'],
            ], false);
        } catch (QueryException $exception) {
            $status = match ($exception->errorInfo[0] ?? null) {
                'P1001' => 402,
                'P1002' => 404,
                'P1003' => 422,
                default => 503,
            };
            if ($status === 503) {
                report($exception);
            }

            return response()->json(['message' => 'No se pudo agregar el grupo.'], $status);
        }

        $profile = array_replace($student, [
            'grupo_id' => (int) $group->grupo_id,
            'materia' => $group->materia,
            'folio_codigo' => $folio,
        ]);
        if ($profile['grupo_id'] === $student['grupo_id']) {
            $request->session()->put('bbb.student', $profile);
        } else {
            $request->session()->put('bbb.groups.'.$profile['grupo_id'], $profile);
        }
        $request->session()->put('bbb.selected_group', $profile['grupo_id']);

        return response()->json(['data' => [
            'alumno_id' => $student['alumno_id'],
            'grupo_id' => $profile['grupo_id'],
            'materia' => $profile['materia'],
            'estado' => 'inscrito',
            'home_url' => route('student.home', ['grupo' => $profile['grupo_id']]),
        ]], 201);
    }

    public function joinGroup(Request $request): JsonResponse
    {
        $student = $this->student($request);
        if (!$student) {
            return response()->json(['message' => 'Tu sesión del alumno expiró. Vuelve a ingresar al portal.'], 401);
        }

        return $this->joinAdditionalGroup($request, $student);
    }

    public function home(Request $request): View|RedirectResponse
    {
        $student = $this->student($request);
        if (!$student) {
            return redirect()->route('student.entry');
        }
        $groups = $this->groups($request, $student);
        $student = $this->groupForRequest($request, $student) ?? $this->currentGroup($request, $student);
        $request->session()->put('bbb.selected_group', $student['grupo_id']);

        try {
            $exams = $this->examsForStudent($student);
        } catch (QueryException $exception) {
            report($exception);
            $exams = null;
        }

        $attempt = $this->attempt($request);
        $completed = array_filter($this->completedAttempts($request), fn ($item) => $item['grupo_id'] === $student['grupo_id']);
        $excluded = array_column($completed, 'examen_id');
        if ($attempt) {
            $excluded[] = $attempt['examen_id'];
        }
        $activeAttempt = $attempt && !$attempt['finished'] ? $attempt : null;
        $activeInGroup = $activeAttempt && $activeAttempt['grupo_id'] === $student['grupo_id'] ? $activeAttempt : null;
        $pendingExams = $exams === null ? null : array_values(array_filter(
            $exams,
            fn ($exam) => !in_array((int) $exam->examen_id, $excluded, true),
        ));
        $completedCount = count($completed);
        $recentResults = $this->resultCards(array_slice($completed, 0, 3), $groups);

        return view('student.home', compact('student', 'groups', 'activeAttempt', 'activeInGroup', 'pendingExams', 'completedCount', 'recentResults'));
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
        $student = $this->groupForRequest($request, $student) ?? $this->currentGroup($request, $student);
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
            'grupo_id' => $student['grupo_id'],
            'nombre' => $result->nombre,
            'fecha_inicio' => (string) $result->fecha_inicio,
            'finished' => false,
        ]);

        return response()->json(['data' => ['intento_id' => (int) $result->intento_id, 'url' => route('student.exam')]], 201);
    }

    public function exam(Request $request): View|RedirectResponse
    {
        $student = $this->student($request);
        if (!$student) return redirect()->route('student.entry');
        $attempt = $this->attempt($request);
        if (!$attempt) return redirect()->route('student.home');
        if ($attempt['finished']) return redirect()->route('student.grades');
        $student = $this->groups($request, $student)[$attempt['grupo_id']] ?? abort(404);

        return view('student.exam', compact('student', 'attempt'));
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

        $groups = $this->groups($request, $student);
        $student = $this->groupForRequest($request, $student) ?? $this->currentGroup($request, $student);
        $request->session()->put('bbb.selected_group', $student['grupo_id']);
        $data = $request->validate(['estado' => ['sometimes', 'nullable', 'in:en_revision,revision_completada,sin_publicar']]);
        $status = $data['estado'] ?? null;
        if ($request->has('intento')) {
            $attempt = $this->selectedResult($request);
            $resultGroup = $groups[$attempt['grupo_id']] ?? [
                'grupo_id' => $attempt['grupo_id'],
                'materia' => $attempt['materia'] ?? $student['materia'],
            ];

            return view('student.grades', compact('student', 'groups', 'attempt', 'status', 'resultGroup'));
        }

        $completed = array_filter($this->completedAttempts($request), fn ($item) => $item['grupo_id'] === $student['grupo_id']);
        $results = $this->resultCards($completed, $groups);

        return view('student.grade-list', compact('student', 'groups', 'status', 'results'));
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
        if (!$request->has('intento')) {
            $completed = $this->completedAttempts($request);
            return reset($completed) ?: null;
        }

        $data = $request->validate(['intento' => ['required', 'integer', 'min:1']]);

        return $this->completedAttempts($request, (int) $data['intento'])[(int) $data['intento']] ?? abort(404);
    }

    public function leave(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('student.entry');
    }
}
