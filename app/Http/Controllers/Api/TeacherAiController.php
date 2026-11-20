<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\IaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerarPreguntasIaRequest;
use App\Services\AsistenciaIaService;
use Illuminate\Http\JsonResponse;

class TeacherAiController extends Controller
{
    public function questions(GenerarPreguntasIaRequest $request, AsistenciaIaService $ai): JsonResponse
    {
        return $this->respond(fn (): array => $ai->questions($request->validated()));
    }

    public function grade(int $respuestaId, AsistenciaIaService $ai): JsonResponse
    {
        return $this->respond(fn (): array => $ai->grade($respuestaId));
    }

    private function respond(callable $operation): JsonResponse
    {
        try {
            return response()->json(['data' => $operation()]);
        } catch (IaException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->httpStatus);
        }
    }
}
