<?php

declare(strict_types=1);

use function Hyperf\Support\env;

return [
    'default' => env('AGENT_DEFAULT_DRIVER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | LLM Providers & Drivers
    |--------------------------------------------------------------------------
    |
    | Configuration for supported model providers. You can specify different
    | models, API keys, and custom endpoints (e.g. Azure, DeepSeek, or Ollama).
    |
    */
    'drivers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY', ''),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'default_model' => env('OPENAI_MODEL', 'gpt-4o'),
            'timeout' => (float) env('OPENAI_TIMEOUT', 60.0),
            'client_options' => [
                'verify' => true,
            ],
        ],
        'deepseek' => [
            'api_key' => env('DEEPSEEK_API_KEY', ''),
            'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com/v1'),
            'default_model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
            'timeout' => (float) env('DEEPSEEK_TIMEOUT', 60.0),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Concurrent Tool Execution
    |--------------------------------------------------------------------------
    |
    | Configuration for coroutine-native parallel tool calling.
    |
    */
    'concurrency' => [
        'max_tool_concurrency' => (int) env('AGENT_MAX_TOOL_CONCURRENCY', 20),
        'tool_timeout' => (float) env('AGENT_TOOL_TIMEOUT', 30.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fault Tolerance & Resilience
    |--------------------------------------------------------------------------
    |
    | Settings for retry policies and circuit breakers against rate limits (429)
    | and upstream timeouts.
    |
    */
    'resilience' => [
        'retry' => [
            'enabled' => true,
            'max_attempts' => (int) env('AGENT_RETRY_ATTEMPTS', 3),
            'initial_delay_ms' => 200,
            'multiplier' => 2.0,
        ],
        'circuit_breaker' => [
            'enabled' => true,
            'failure_threshold' => 5,
            'recovery_time' => 30,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversation Memory & Session Persistence
    |--------------------------------------------------------------------------
    |
    | Storage drivers for retaining multi-turn agent conversation histories.
    |
    */
    'memory' => [
        'driver' => env('AGENT_MEMORY_DRIVER', 'redis'),
        'redis_pool' => env('AGENT_REDIS_POOL', 'default'),
        'prefix' => 'hyperf_agents:session:',
        'ttl' => (int) env('AGENT_SESSION_TTL', 604800), // 7 days
        'max_messages_per_session' => (int) env('AGENT_MAX_MESSAGES', 50),
    ],
];
