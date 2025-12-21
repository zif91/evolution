<?php

namespace EvolutionCMS\AiAssistant\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiService
{
    protected array $config;
    protected string $provider;
    protected array $conversationHistory = [];

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->provider = $config['provider'] ?? 'openai';
    }

    /**
     * Send a message to the AI and get a response
     */
    public function chat(string $message, array $context = []): array
    {
        $systemPrompt = $this->config['system_prompt'] ?? '';

        // Build messages array
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // Add context if provided
        if (!empty($context)) {
            $contextMessage = "Current context:\n" . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $messages[] = ['role' => 'system', 'content' => $contextMessage];
        }

        // Add conversation history
        foreach ($this->conversationHistory as $historyItem) {
            $messages[] = $historyItem;
        }

        // Add current user message
        $messages[] = ['role' => 'user', 'content' => $message];

        try {
            $response = match ($this->provider) {
                'openai' => $this->callOpenAI($messages),
                'anthropic' => $this->callAnthropic($messages),
                default => throw new \Exception("Unsupported AI provider: {$this->provider}"),
            };

            // Save to history
            $this->conversationHistory[] = ['role' => 'user', 'content' => $message];
            $this->conversationHistory[] = ['role' => 'assistant', 'content' => $response['content']];

            return $response;
        } catch (\Exception $e) {
            Log::error('AI Service error: ' . $e->getMessage());

            return [
                'success' => false,
                'content' => '',
                'error' => $e->getMessage(),
                'actions' => [],
            ];
        }
    }

    /**
     * Call OpenAI API
     */
    protected function callOpenAI(array $messages): array
    {
        $providerConfig = $this->config['providers']['openai'];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $providerConfig['api_key'],
            'Content-Type' => 'application/json',
        ])->timeout(60)->post($providerConfig['endpoint'], [
            'model' => $providerConfig['model'],
            'messages' => $messages,
            'max_tokens' => $providerConfig['max_tokens'],
            'temperature' => $providerConfig['temperature'],
            'functions' => $this->getToolDefinitions(),
            'function_call' => 'auto',
        ]);

        if (!$response->successful()) {
            throw new \Exception('OpenAI API error: ' . $response->body());
        }

        $data = $response->json();
        $choice = $data['choices'][0] ?? [];
        $assistantMessage = $choice['message'] ?? [];

        $content = $assistantMessage['content'] ?? '';
        $actions = [];

        // Parse function calls
        if (isset($assistantMessage['function_call'])) {
            $functionCall = $assistantMessage['function_call'];
            $actions[] = [
                'type' => 'function',
                'name' => $functionCall['name'],
                'arguments' => json_decode($functionCall['arguments'], true) ?? [],
            ];
        }

        return [
            'success' => true,
            'content' => $content,
            'actions' => $actions,
            'raw' => $data,
        ];
    }

    /**
     * Call Anthropic API
     */
    protected function callAnthropic(array $messages): array
    {
        $providerConfig = $this->config['providers']['anthropic'];

        // Convert messages format for Anthropic
        $systemContent = '';
        $anthropicMessages = [];

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemContent .= $msg['content'] . "\n\n";
            } else {
                $anthropicMessages[] = [
                    'role' => $msg['role'],
                    'content' => $msg['content'],
                ];
            }
        }

        $response = Http::withHeaders([
            'x-api-key' => $providerConfig['api_key'],
            'Content-Type' => 'application/json',
            'anthropic-version' => '2023-06-01',
        ])->timeout(60)->post($providerConfig['endpoint'], [
            'model' => $providerConfig['model'],
            'max_tokens' => $providerConfig['max_tokens'],
            'system' => trim($systemContent),
            'messages' => $anthropicMessages,
            'tools' => $this->getAnthropicToolDefinitions(),
        ]);

        if (!$response->successful()) {
            throw new \Exception('Anthropic API error: ' . $response->body());
        }

        $data = $response->json();
        $content = '';
        $actions = [];

        foreach ($data['content'] ?? [] as $block) {
            if ($block['type'] === 'text') {
                $content .= $block['text'];
            } elseif ($block['type'] === 'tool_use') {
                $actions[] = [
                    'type' => 'function',
                    'name' => $block['name'],
                    'arguments' => $block['input'] ?? [],
                    'id' => $block['id'],
                ];
            }
        }

        return [
            'success' => true,
            'content' => $content,
            'actions' => $actions,
            'raw' => $data,
        ];
    }

    /**
     * Get tool definitions for OpenAI
     */
    protected function getToolDefinitions(): array
    {
        return [
            [
                'name' => 'search_resources',
                'description' => 'Search for resources (pages) in the CMS',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'keyword' => [
                            'type' => 'string',
                            'description' => 'Search keyword',
                        ],
                        'template' => [
                            'type' => 'integer',
                            'description' => 'Filter by template ID',
                        ],
                        'published' => [
                            'type' => 'boolean',
                            'description' => 'Filter by published status',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of results',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'get_resource',
                'description' => 'Get detailed information about a resource',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'integer',
                            'description' => 'Resource ID',
                        ],
                    ],
                    'required' => ['id'],
                ],
            ],
            [
                'name' => 'update_resource',
                'description' => 'Update resource fields',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'integer',
                            'description' => 'Resource ID',
                        ],
                        'pagetitle' => ['type' => 'string'],
                        'longtitle' => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                        'content' => ['type' => 'string'],
                        'introtext' => ['type' => 'string'],
                        'alias' => ['type' => 'string'],
                        'menutitle' => ['type' => 'string'],
                    ],
                    'required' => ['id'],
                ],
            ],
            [
                'name' => 'update_tv',
                'description' => 'Update template variable value for a resource',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'resource_id' => [
                            'type' => 'integer',
                            'description' => 'Resource ID',
                        ],
                        'tv_name' => [
                            'type' => 'string',
                            'description' => 'TV name',
                        ],
                        'value' => [
                            'type' => 'string',
                            'description' => 'New value',
                        ],
                    ],
                    'required' => ['resource_id', 'tv_name', 'value'],
                ],
            ],
            [
                'name' => 'publish_resource',
                'description' => 'Publish a resource',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'integer',
                            'description' => 'Resource ID',
                        ],
                    ],
                    'required' => ['id'],
                ],
            ],
            [
                'name' => 'unpublish_resource',
                'description' => 'Unpublish a resource',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'integer',
                            'description' => 'Resource ID',
                        ],
                    ],
                    'required' => ['id'],
                ],
            ],
            [
                'name' => 'create_tv',
                'description' => 'Create a new template variable',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'TV name (no spaces)'],
                        'caption' => ['type' => 'string', 'description' => 'Display caption'],
                        'description' => ['type' => 'string'],
                        'type' => ['type' => 'string', 'description' => 'TV type (text, textarea, image, etc.)'],
                        'default_text' => ['type' => 'string', 'description' => 'Default value'],
                        'templates' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer'],
                            'description' => 'Template IDs to bind to',
                        ],
                    ],
                    'required' => ['name'],
                ],
            ],
            [
                'name' => 'optimize_seo',
                'description' => 'Generate SEO-optimized content for a resource',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'resource_id' => [
                            'type' => 'integer',
                            'description' => 'Resource ID',
                        ],
                        'focus_keyword' => [
                            'type' => 'string',
                            'description' => 'Main keyword to optimize for',
                        ],
                    ],
                    'required' => ['resource_id'],
                ],
            ],
            [
                'name' => 'rollback_checkpoint',
                'description' => 'Rollback to a previous checkpoint',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'checkpoint_id' => [
                            'type' => 'integer',
                            'description' => 'Checkpoint ID to rollback to',
                        ],
                    ],
                    'required' => ['checkpoint_id'],
                ],
            ],
            [
                'name' => 'list_checkpoints',
                'description' => 'List available checkpoints for rollback',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'entity_type' => [
                            'type' => 'string',
                            'description' => 'Filter by entity type (resource, tv, template)',
                        ],
                        'entity_id' => [
                            'type' => 'integer',
                            'description' => 'Filter by entity ID',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get tool definitions for Anthropic
     */
    protected function getAnthropicToolDefinitions(): array
    {
        $openAiTools = $this->getToolDefinitions();
        $anthropicTools = [];

        foreach ($openAiTools as $tool) {
            $anthropicTools[] = [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'input_schema' => $tool['parameters'],
            ];
        }

        return $anthropicTools;
    }

    /**
     * Clear conversation history
     */
    public function clearHistory(): void
    {
        $this->conversationHistory = [];
    }

    /**
     * Set conversation history
     */
    public function setHistory(array $history): void
    {
        $this->conversationHistory = $history;
    }

    /**
     * Get conversation history
     */
    public function getHistory(): array
    {
        return $this->conversationHistory;
    }

    /**
     * Check if AI is configured
     */
    public function isConfigured(): bool
    {
        $providerConfig = $this->config['providers'][$this->provider] ?? [];
        return !empty($providerConfig['api_key']);
    }
}
