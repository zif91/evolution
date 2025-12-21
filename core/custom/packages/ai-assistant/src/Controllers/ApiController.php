<?php

namespace EvolutionCMS\AiAssistant\Controllers;

use EvolutionCMS\AiAssistant\Models\Checkpoint;
use EvolutionCMS\AiAssistant\Services\AiService;
use EvolutionCMS\AiAssistant\Services\CheckpointService;
use EvolutionCMS\AiAssistant\Services\ResourceService;
use EvolutionCMS\AiAssistant\Services\SeoService;
use EvolutionCMS\AiAssistant\Services\TemplateService;
use EvolutionCMS\AiAssistant\Services\TvService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ApiController extends Controller
{
    public function __construct(
        protected AiService $aiService,
        protected ResourceService $resourceService,
        protected TvService $tvService,
        protected TemplateService $templateService,
        protected SeoService $seoService,
        protected CheckpointService $checkpointService
    ) {}

    /**
     * Chat with AI
     */
    public function chat(Request $request): JsonResponse
    {
        $message = $request->input('message', '');
        $context = $request->input('context', []);

        if (empty($message)) {
            return response()->json([
                'success' => false,
                'error' => 'Message is required',
            ], 400);
        }

        // Get conversation history from session
        $history = session('ai_assistant_history', []);
        $this->aiService->setHistory($history);

        // Get AI response
        $response = $this->aiService->chat($message, $context);

        // Save updated history
        session(['ai_assistant_history' => $this->aiService->getHistory()]);

        // Execute any actions returned by AI
        $executedActions = [];
        if (!empty($response['actions'])) {
            foreach ($response['actions'] as $action) {
                $executedActions[] = $this->executeAction($action);
            }
        }

        return response()->json([
            'success' => $response['success'] ?? true,
            'message' => $response['content'] ?? '',
            'actions' => $executedActions,
        ]);
    }

    /**
     * Execute a specific action
     */
    public function execute(Request $request): JsonResponse
    {
        $action = $request->input('action', '');
        $params = $request->input('params', []);

        if (empty($action)) {
            return response()->json([
                'success' => false,
                'error' => 'Action is required',
            ], 400);
        }

        $result = $this->executeAction([
            'name' => $action,
            'arguments' => $params,
        ]);

        return response()->json($result);
    }

    /**
     * Execute an action from AI response
     */
    protected function executeAction(array $action): array
    {
        $name = $action['name'] ?? '';
        $args = $action['arguments'] ?? [];

        try {
            $result = match ($name) {
                'search_resources' => [
                    'action' => $name,
                    'success' => true,
                    'data' => $this->resourceService->search($args)->toArray(),
                ],
                'get_resource' => [
                    'action' => $name,
                    'success' => true,
                    'data' => $this->resourceService->get($args['id'] ?? 0),
                ],
                'update_resource' => [
                    'action' => $name,
                    'success' => true,
                    'data' => $this->resourceService->update($args['id'] ?? 0, $args)?->toArray(),
                ],
                'update_tv' => [
                    'action' => $name,
                    'success' => $this->resourceService->updateTv(
                        $args['resource_id'] ?? 0,
                        $args['tv_name'] ?? '',
                        $args['value'] ?? ''
                    ),
                ],
                'publish_resource' => [
                    'action' => $name,
                    'success' => true,
                    'data' => $this->resourceService->publish($args['id'] ?? 0)?->toArray(),
                ],
                'unpublish_resource' => [
                    'action' => $name,
                    'success' => true,
                    'data' => $this->resourceService->unpublish($args['id'] ?? 0)?->toArray(),
                ],
                'create_tv' => [
                    'action' => $name,
                    'success' => true,
                    'data' => $this->tvService->create($args)?->toArray(),
                ],
                'optimize_seo' => [
                    'action' => $name,
                    'success' => true,
                    'data' => $this->seoService->generateSuggestions(
                        $args['resource_id'] ?? 0,
                        $args['focus_keyword'] ?? null
                    ),
                ],
                'rollback_checkpoint' => [
                    'action' => $name,
                    'success' => $this->rollbackCheckpointById($args['checkpoint_id'] ?? 0),
                ],
                'list_checkpoints' => [
                    'action' => $name,
                    'success' => true,
                    'data' => $this->getCheckpointsList($args),
                ],
                default => [
                    'action' => $name,
                    'success' => false,
                    'error' => "Unknown action: {$name}",
                ],
            };

            return $result;
        } catch (\Exception $e) {
            return [
                'action' => $name,
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // ========== Resources ==========

    /**
     * Search resources
     */
    public function searchResources(Request $request): JsonResponse
    {
        $criteria = $request->only(['keyword', 'id', 'parent', 'template', 'published', 'deleted', 'limit', 'orderBy', 'orderDir']);

        return response()->json([
            'success' => true,
            'data' => $this->resourceService->search($criteria)->toArray(),
        ]);
    }

    /**
     * Get resource tree
     */
    public function getResourceTree(Request $request): JsonResponse
    {
        $parentId = (int) $request->input('parent', 0);
        $depth = (int) $request->input('depth', 2);

        return response()->json([
            'success' => true,
            'data' => $this->resourceService->getTree($parentId, $depth),
        ]);
    }

    /**
     * Get single resource
     */
    public function getResource(int $id): JsonResponse
    {
        $resource = $this->resourceService->get($id);

        if (!$resource) {
            return response()->json([
                'success' => false,
                'error' => 'Resource not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $resource,
        ]);
    }

    /**
     * Update resource
     */
    public function updateResource(Request $request, int $id): JsonResponse
    {
        if (!evo()->hasPermission('save_document')) {
            return response()->json([
                'success' => false,
                'error' => 'Permission denied',
            ], 403);
        }

        $resource = $this->resourceService->update($id, $request->all());

        if (!$resource) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to update resource',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $resource->toArray(),
        ]);
    }

    /**
     * Publish resource
     */
    public function publishResource(int $id): JsonResponse
    {
        if (!evo()->hasPermission('publish_document')) {
            return response()->json([
                'success' => false,
                'error' => 'Permission denied',
            ], 403);
        }

        $resource = $this->resourceService->publish($id);

        if (!$resource) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to publish resource',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $resource->toArray(),
        ]);
    }

    /**
     * Unpublish resource
     */
    public function unpublishResource(int $id): JsonResponse
    {
        if (!evo()->hasPermission('publish_document')) {
            return response()->json([
                'success' => false,
                'error' => 'Permission denied',
            ], 403);
        }

        $resource = $this->resourceService->unpublish($id);

        if (!$resource) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to unpublish resource',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $resource->toArray(),
        ]);
    }

    /**
     * Get resource TV values
     */
    public function getResourceTv(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->resourceService->getTvValues($id),
        ]);
    }

    /**
     * Update resource TV values
     */
    public function updateResourceTv(Request $request, int $id): JsonResponse
    {
        if (!evo()->hasPermission('save_document')) {
            return response()->json([
                'success' => false,
                'error' => 'Permission denied',
            ], 403);
        }

        $tvData = $request->input('tv', []);
        $results = $this->resourceService->updateTvs($id, $tvData);

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }

    // ========== TV ==========

    /**
     * List all TVs
     */
    public function listTv(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->tvService->getAll()->toArray(),
        ]);
    }

    /**
     * Create TV
     */
    public function createTv(Request $request): JsonResponse
    {
        if (!evo()->hasPermission('new_template') && !evo()->hasPermission('edit_template')) {
            return response()->json([
                'success' => false,
                'error' => 'Permission denied',
            ], 403);
        }

        $tv = $this->tvService->create($request->all());

        if (!$tv) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to create TV',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $tv->toArray(),
        ]);
    }

    /**
     * Get TV
     */
    public function getTv(int $id): JsonResponse
    {
        $tv = $this->tvService->get($id);

        if (!$tv) {
            return response()->json([
                'success' => false,
                'error' => 'TV not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $tv->toArray(),
        ]);
    }

    /**
     * Update TV
     */
    public function updateTv(Request $request, int $id): JsonResponse
    {
        if (!evo()->hasPermission('edit_template')) {
            return response()->json([
                'success' => false,
                'error' => 'Permission denied',
            ], 403);
        }

        $tv = $this->tvService->update($id, $request->all());

        if (!$tv) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to update TV',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $tv->toArray(),
        ]);
    }

    /**
     * Bind TV to templates
     */
    public function bindTvToTemplates(Request $request, int $id): JsonResponse
    {
        if (!evo()->hasPermission('edit_template')) {
            return response()->json([
                'success' => false,
                'error' => 'Permission denied',
            ], 403);
        }

        $templateIds = $request->input('templates', []);
        $success = $this->tvService->bindToTemplates($id, $templateIds);

        return response()->json([
            'success' => $success,
        ]);
    }

    // ========== Templates ==========

    /**
     * List templates
     */
    public function listTemplates(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->templateService->getAll()->toArray(),
        ]);
    }

    /**
     * Get template
     */
    public function getTemplate(int $id): JsonResponse
    {
        $template = $this->templateService->get($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'error' => 'Template not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $template,
        ]);
    }

    /**
     * Update template (inline content)
     */
    public function updateTemplate(Request $request, int $id): JsonResponse
    {
        if (!evo()->hasPermission('save_template')) {
            return response()->json([
                'success' => false,
                'error' => 'Permission denied',
            ], 403);
        }

        $template = $this->templateService->update($id, $request->all());

        if (!$template) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to update template',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $template->toArray(),
        ]);
    }

    /**
     * Update Blade template file
     */
    public function updateBladeTemplate(Request $request, int $id): JsonResponse
    {
        if (!evo()->hasPermission('save_template')) {
            return response()->json([
                'success' => false,
                'error' => 'Permission denied',
            ], 403);
        }

        $content = $request->input('content', '');
        $success = $this->templateService->updateBladeTemplate($id, $content);

        return response()->json([
            'success' => $success,
        ]);
    }

    // ========== SEO ==========

    /**
     * Analyze SEO
     */
    public function analyzeSeo(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->seoService->analyze($id),
        ]);
    }

    /**
     * Optimize SEO
     */
    public function optimizeSeo(Request $request, int $id): JsonResponse
    {
        if (!evo()->hasPermission('save_document')) {
            return response()->json([
                'success' => false,
                'error' => 'Permission denied',
            ], 403);
        }

        $optimizations = $request->all();

        return response()->json([
            'success' => true,
            'data' => $this->seoService->optimize($id, $optimizations),
        ]);
    }

    /**
     * Get SEO suggestions
     */
    public function getSeoSuggestions(Request $request, int $id): JsonResponse
    {
        $focusKeyword = $request->input('focus_keyword');

        return response()->json([
            'success' => true,
            'data' => $this->seoService->generateSuggestions($id, $focusKeyword),
        ]);
    }

    // ========== Checkpoints ==========

    /**
     * List checkpoints
     */
    public function listCheckpoints(Request $request): JsonResponse
    {
        $entityType = $request->input('entity_type');
        $entityId = $request->input('entity_id');

        if ($entityType && $entityId) {
            $checkpoints = $this->checkpointService->getEntityCheckpoints($entityType, $entityId);
        } else {
            $checkpoints = $this->checkpointService->getSessionCheckpoints();
        }

        return response()->json([
            'success' => true,
            'data' => $checkpoints->map(function ($cp) {
                return [
                    'id' => $cp->id,
                    'entity_type' => $cp->entity_type,
                    'entity_id' => $cp->entity_id,
                    'field_name' => $cp->field_name,
                    'description' => $cp->getDescription(),
                    'created_at' => $cp->created_at?->toDateTimeString(),
                ];
            })->toArray(),
        ]);
    }

    /**
     * Rollback checkpoint
     */
    public function rollbackCheckpoint(int $id): JsonResponse
    {
        $checkpoint = Checkpoint::find($id);

        if (!$checkpoint) {
            return response()->json([
                'success' => false,
                'error' => 'Checkpoint not found',
            ], 404);
        }

        $success = $this->checkpointService->rollback($checkpoint);

        return response()->json([
            'success' => $success,
        ]);
    }

    /**
     * Rollback all session checkpoints
     */
    public function rollbackSession(): JsonResponse
    {
        $count = $this->checkpointService->rollbackSession(session_id());

        return response()->json([
            'success' => true,
            'rolled_back' => $count,
        ]);
    }

    /**
     * Helper to rollback checkpoint by ID
     */
    protected function rollbackCheckpointById(int $id): bool
    {
        $checkpoint = Checkpoint::find($id);
        return $checkpoint ? $this->checkpointService->rollback($checkpoint) : false;
    }

    /**
     * Helper to get checkpoints list
     */
    protected function getCheckpointsList(array $filters): array
    {
        if (!empty($filters['entity_type']) && !empty($filters['entity_id'])) {
            $checkpoints = $this->checkpointService->getEntityCheckpoints(
                $filters['entity_type'],
                $filters['entity_id']
            );
        } else {
            $checkpoints = $this->checkpointService->getSessionCheckpoints();
        }

        return $checkpoints->map(function ($cp) {
            return [
                'id' => $cp->id,
                'entity_type' => $cp->entity_type,
                'entity_id' => $cp->entity_id,
                'description' => $cp->getDescription(),
                'created_at' => $cp->created_at?->toDateTimeString(),
            ];
        })->toArray();
    }

    // ========== Other ==========

    /**
     * Get conversation history
     */
    public function getHistory(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => session('ai_assistant_history', []),
        ]);
    }

    /**
     * Clear conversation history
     */
    public function clearHistory(): JsonResponse
    {
        session()->forget('ai_assistant_history');

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Get settings
     */
    public function getSettings(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'provider' => config('ai-assistant.provider'),
                'ui' => config('ai-assistant.ui'),
                'actions' => config('ai-assistant.actions'),
                'is_configured' => $this->aiService->isConfigured(),
            ],
        ]);
    }

    /**
     * Update settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        // For now, settings are managed via config file
        return response()->json([
            'success' => false,
            'error' => 'Settings can only be changed via configuration file',
        ], 400);
    }

    /**
     * Status check
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'version' => '1.0.0',
                'ai_configured' => $this->aiService->isConfigured(),
                'user' => [
                    'id' => evo()->getLoginUserID(),
                    'permissions' => [
                        'view_document' => evo()->hasPermission('view_document'),
                        'edit_document' => evo()->hasPermission('edit_document'),
                        'save_document' => evo()->hasPermission('save_document'),
                        'publish_document' => evo()->hasPermission('publish_document'),
                        'edit_template' => evo()->hasPermission('edit_template'),
                    ],
                ],
            ],
        ]);
    }
}
