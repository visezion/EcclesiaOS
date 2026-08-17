<?php

return [
    'default_provider' => env('AI_COPILOT_PROVIDER', 'openai'),
    'default_model' => env('AI_COPILOT_MODEL', 'gpt-4.1-mini'),
    'timeout' => (int) env('AI_COPILOT_TIMEOUT', 30),
    'max_tokens' => (int) env('AI_COPILOT_MAX_TOKENS', 1200),
    'openai_endpoint' => env('OPENAI_API_ENDPOINT', 'https://api.openai.com/v1/responses'),
    'anthropic_endpoint' => env('ANTHROPIC_API_ENDPOINT', 'https://api.anthropic.com/v1/messages'),
    'anthropic_models_endpoint' => env('ANTHROPIC_MODELS_ENDPOINT', 'https://api.anthropic.com/v1/models'),
    'providers' => [
        'openai' => ['label' => 'OpenAI API', 'models' => ['gpt-4.1-mini', 'gpt-4.1', 'gpt-5']],
        'anthropic' => ['label' => 'Claude API', 'models' => ['claude-sonnet-4-20250514', 'claude-3-7-sonnet-latest', 'claude-3-5-haiku-latest']],
    ],
];
