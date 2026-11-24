<?php

declare(strict_types=1);

return [
    'automatic_grading' => env('AI_AUTOMATIC_GRADING', true),
    'enabled' => env('AI_ENABLED', true),
    'key' => env('GROQ_API_KEY'),
    'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
    'timeout' => 35,
    'max_input_bytes' => 18000,
    'teacher_daily_requests' => (int) env('AI_DAILY_REQUEST_LIMIT', 20),
    'global_daily_requests' => (int) env('AI_GLOBAL_DAILY_REQUEST_LIMIT', 100),
    'global_daily_tokens' => (int) env('AI_GLOBAL_DAILY_TOKEN_LIMIT', 150000),
    'global_minute_requests' => 6,
];
