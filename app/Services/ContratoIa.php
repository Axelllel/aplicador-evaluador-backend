<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\IaException;
use Illuminate\Support\Facades\Validator;

class ContratoIa
{
    public static function object(array $properties): array
    {
        return ['type' => 'object', 'properties' => $properties, 'required' => array_keys($properties), 'additionalProperties' => false];
    }

    public function questionsSchema(): array
    {
        return self::object(['preguntas' => ['type' => 'array', 'items' => self::object([
            'enunciado' => ['type' => 'string'], 'respuesta_modelo' => ['type' => 'string'],
            'opciones' => ['type' => 'array', 'items' => ['type' => 'string']],
            'pares' => ['type' => 'array', 'items' => self::object(['izquierda' => ['type' => 'string'], 'derecha' => ['type' => 'string']])],
        ])]]);
    }

    public function gradeSchema(): array
    {
        return self::object(['puntaje' => ['type' => ['number', 'null']],
            'justificacion' => ['type' => 'string'], 'requiere_revision' => ['type' => 'boolean'],
            'observaciones' => ['type' => 'array', 'items' => ['type' => 'string']]]);
    }

    public function questions(array $data, string $type, int $count): array
    {
        $validator = Validator::make($data, [
            'preguntas' => ['required', 'array', 'list', 'size:'.$count],
            'preguntas.*.enunciado' => ['required', 'string', 'min:3', 'max:4000'],
            'preguntas.*.respuesta_modelo' => ['required', 'string', 'max:6000'],
            'preguntas.*.opciones' => ['present', 'array', 'list', 'max:10'],
            'preguntas.*.opciones.*' => ['required', 'string', 'max:1000', 'not_regex:/[\r\n]/'],
            'preguntas.*.pares' => ['present', 'array', 'list', 'max:10'],
            'preguntas.*.pares.*.izquierda' => ['required', 'string', 'max:1000'],
            'preguntas.*.pares.*.derecha' => ['required', 'string', 'max:1000'],
        ]);
        if ($validator->fails()) {
            throw new IaException('Las preguntas generadas no cumplen el formato requerido. No se agregaron al banco.', 502);
        }
        $questions = [];
        $seen = [];
        foreach ($validator->validated()['preguntas'] as $question) {
            $prompt = trim($question['enunciado']);
            $model = trim($question['respuesta_modelo']);
            $options = array_map('trim', $question['opciones']);
            $pairs = $question['pares'];
            if (isset($seen[mb_strtolower($prompt)]) || ($type === 'opcion_multiple' &&
                (count($options) < 2 || count(array_unique($options)) !== count($options) || ! in_array($model, $options, true))) ||
                ($type === 'relacion_columnas' && count($pairs) < 2) ||
                ($type !== 'opcion_multiple' && $options !== []) || ($type !== 'relacion_columnas' && $pairs !== [])) {
                throw new IaException('La IA generó preguntas repetidas o alternativas incompatibles. No se agregaron al banco.', 502);
            }
            $seen[mb_strtolower($prompt)] = true;
            if ($type === 'relacion_columnas') {
                $model = implode("\n", array_map(fn (array $pair): string => trim($pair['izquierda']).' = '.trim($pair['derecha']), $pairs));
            }
            $questions[] = ['tipo' => $type, 'enunciado' => $prompt, 'respuesta_modelo' => $model,
                'opciones' => match ($type) {
                    'opcion_multiple' => $options, 'relacion_columnas' => $pairs, default => null
                }];
        }

        return ['preguntas' => $questions];
    }

    public function grade(array $data): array
    {
        $validator = Validator::make($data, ['puntaje' => ['present', 'nullable', 'numeric', 'between:0,100'],
            'justificacion' => ['required', 'string', 'max:2000'], 'requiere_revision' => ['required', 'boolean'],
            'observaciones' => ['present', 'array', 'list', 'max:8'], 'observaciones.*' => ['required', 'string', 'max:500']]);
        if ($validator->fails() || (isset($data['puntaje']) && ! is_int($data['puntaje']) && ! is_float($data['puntaje'])) || ! is_bool($data['requiere_revision'] ?? null) ||
            (($data['puntaje'] ?? null) === null && ! ($data['requiere_revision'] ?? false))) {
            throw new IaException('La propuesta de calificación no es válida. Revisa la respuesta manualmente.', 502);
        }
        $grade = $validator->validated();
        $grade['puntaje'] = $grade['puntaje'] === null ? null : round((float) $grade['puntaje'], 2);

        return $grade;
    }
}
