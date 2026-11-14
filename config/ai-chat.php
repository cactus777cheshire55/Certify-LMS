<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | AI Chat Feature Switch & Gemini Configuration
    |--------------------------------------------------------------------------
    */
    'enabled' => (bool) env('AI_CHAT_ENABLED', true),

    'gemini' => [
        'api_key'  => env('GEMINI_API_KEY'),
        'model'    => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
        'timeout'  => (int) env('GEMINI_TIMEOUT', 60),
        'base_url' => env('GEMINI_BASE_URL', base64_decode('aHR0cHM6Ly9nZW5lcmF0aXZlbGFuZ3VhZ2UuZ29vZ2xlYXBpcy5jb20vdjFiZXRhL21vZGVscw==')),
    ],

    /*
    | 1人あたりの1日あたりのAIメッセージ送信上限数
    */
    'daily_message_limit' => (int) env('AI_CHAT_DAILY_LIMIT', 100),
];