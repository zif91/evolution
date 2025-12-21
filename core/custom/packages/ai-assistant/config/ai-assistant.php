<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your AI provider settings. Supported providers: openai, anthropic
    |
    */
    'provider' => env('AI_ASSISTANT_PROVIDER', 'openai'),

    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY', ''),
            'model' => env('OPENAI_MODEL', 'gpt-4'),
            'max_tokens' => env('OPENAI_MAX_TOKENS', 4096),
            'temperature' => env('OPENAI_TEMPERATURE', 0.7),
            'endpoint' => env('OPENAI_ENDPOINT', 'https://api.openai.com/v1/chat/completions'),
        ],
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY', ''),
            'model' => env('ANTHROPIC_MODEL', 'claude-3-sonnet-20240229'),
            'max_tokens' => env('ANTHROPIC_MAX_TOKENS', 4096),
            'endpoint' => env('ANTHROPIC_ENDPOINT', 'https://api.anthropic.com/v1/messages'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Checkpoint Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how checkpoints are stored and managed
    |
    */
    'checkpoints' => [
        'max_per_resource' => env('AI_CHECKPOINTS_MAX', 10),
        'auto_cleanup_days' => env('AI_CHECKPOINTS_CLEANUP_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the AI Assistant UI panel
    |
    */
    'ui' => [
        'position' => env('AI_ASSISTANT_POSITION', 'right'), // right, left
        'default_open' => env('AI_ASSISTANT_DEFAULT_OPEN', false),
        'panel_width' => env('AI_ASSISTANT_PANEL_WIDTH', '400px'),
    ],

    /*
    |--------------------------------------------------------------------------
    | System Prompt
    |--------------------------------------------------------------------------
    |
    | The system prompt that instructs the AI how to behave
    |
    */
    'system_prompt' => <<<'PROMPT'
You are an AI assistant for Evolution CMS. You help users manage their website content efficiently.

Your capabilities include:
1. Searching for resources (pages, documents)
2. Editing resource content and TV (template variables)
3. Creating and managing TV fields
4. Editing templates (both Blade views and inline templates in database)
5. Publishing and unpublishing resources
6. SEO optimization for pages

Important rules:
- Always create a checkpoint before making any changes
- Never access the database directly - use only the CMS API
- Provide clear explanations of what you're doing
- Ask for confirmation before making destructive changes
- Respond in the same language the user writes to you

When users ask you to do something, use the available tools to accomplish the task.
PROMPT,

    /*
    |--------------------------------------------------------------------------
    | Available Actions
    |--------------------------------------------------------------------------
    |
    | Define which actions the AI assistant can perform
    |
    */
    'actions' => [
        'search_resources' => true,
        'edit_resources' => true,
        'create_resources' => true,
        'delete_resources' => false, // Disabled by default for safety
        'publish_resources' => true,
        'unpublish_resources' => true,
        'manage_tv' => true,
        'edit_templates' => true,
        'seo_optimization' => true,
        'rollback_changes' => true,
    ],
];
