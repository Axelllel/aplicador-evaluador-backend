<?php

/**
 * Archivo: GroqClient.php
 * Módulo: app/Services
 * Descripción: Cliente HTTP del proveedor de IA (Groq) usado por la asistencia de IA.
 * Autor: Axel Rebollo Zeferino
 * Creado: 20/11/2026
 * Modificado: 23/11/2026 · Leonardo Ballestero López · Cabecera de archivo
 */

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\IaException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use JsonException;

class GroqClient
{
    public function available(): bool
    {
        return config('ai.enabled') && is_string(config('ai.key')) && trim(config('ai.key')) !== '';
    }

    public function body(string $system, array $data, array $schema, int $maxTokens): array
    {
        if (! $this->available()) {
            throw new IaException('La IA aún no está configurada. Agrega GROQ_API_KEY en el servidor.');
        }
        $body = [
            'model' => config('ai.model'),
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)],
            ],
            'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => 'bigbitbyte', 'strict' => true, 'schema' => $schema]],
            'max_completion_tokens' => $maxTokens,
            'reasoning_effort' => 'low',
        ];
        if (strlen(json_encode($body, JSON_THROW_ON_ERROR)) > (int) config('ai.max_input_bytes')) {
            throw new IaException('El contenido es demasiado largo para esta consulta de IA. Reduce el contexto o revisa la respuesta manualmente.', 422);
        }

        return $body;
    }

    /** Una sola petición: no reintenta ni cambia a otro proveedor o modelo. */
    public function complete(array $body): array
    {
        try {
            $response = Http::withToken(config('ai.key'))->acceptJson()->connectTimeout(5)
                ->timeout((int) config('ai.timeout'))->post('https://api.groq.com/openai/v1/chat/completions', $body);
        } catch (ConnectionException $exception) {
            throw new IaException('La IA tardó demasiado o no está disponible. No se guardó ninguna calificación.');
        }
        if ($response->status() === 429) {
            throw new IaException('Se alcanzó la cuota disponible de Groq. Espera un momento y vuelve a intentar; no se usará un servicio de pago alternativo.', 429);
        }
        if (! $response->successful()) {
            throw new IaException(in_array($response->status(), [401, 403], true)
                ? 'La clave de Groq no tiene acceso. Revisa su configuración en el servidor.'
                : 'Groq no pudo completar la consulta. Intenta más tarde.');
        }
        $choice = $response->json('choices.0');
        $tokens = $response->json('usage.total_tokens');
        if (! is_array($choice) || ($choice['finish_reason'] ?? null) !== 'stop' || filled($choice['message']['refusal'] ?? null)) {
            throw new IaException('La IA no devolvió una respuesta completa y utilizable. Revisa el contenido o inténtalo de nuevo.', 502);
        }
        try {
            $data = json_decode($choice['message']['content'] ?? '', true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new IaException('La IA devolvió un formato inválido. No se guardó el resultado.', 502);
        }
        if (! is_array($data)) {
            throw new IaException('La IA devolvió un resultado inválido.', 502);
        }

        return ['data' => $data, 'tokens' => is_int($tokens) && $tokens >= 0 && $tokens <= 1000000 ? $tokens : null];
    }
}
