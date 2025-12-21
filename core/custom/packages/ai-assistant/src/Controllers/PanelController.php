<?php

namespace EvolutionCMS\AiAssistant\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class PanelController extends Controller
{
    /**
     * Render the AI Assistant panel
     */
    public function index(): Response
    {
        return response()->view('ai-assistant::panel', [
            'config' => [
                'baseUrl' => url('/ai-assistant'),
                'apiUrl' => url('/ai-assistant/api'),
                'position' => config('ai-assistant.ui.position', 'right'),
                'panelWidth' => config('ai-assistant.ui.panel_width', '400px'),
                'defaultOpen' => config('ai-assistant.ui.default_open', false),
                'isConfigured' => app(\EvolutionCMS\AiAssistant\Services\AiService::class)->isConfigured(),
            ],
            'user' => [
                'id' => evo()->getLoginUserID(),
                'name' => $_SESSION['mgrShortname'] ?? 'User',
            ],
            'translations' => [
                'title' => __('ai-assistant::messages.panel.title'),
                'placeholder' => __('ai-assistant::messages.panel.placeholder'),
                'send' => __('ai-assistant::messages.panel.send'),
                'clear' => __('ai-assistant::messages.panel.clear'),
                'thinking' => __('ai-assistant::messages.panel.thinking'),
                'error' => __('ai-assistant::messages.panel.error'),
                'not_configured' => __('ai-assistant::messages.panel.not_configured'),
                'suggestions' => __('ai-assistant::messages.panel.suggestions'),
                'checkpoints' => __('ai-assistant::messages.panel.checkpoints'),
                'rollback' => __('ai-assistant::messages.panel.rollback'),
                'confirm_rollback' => __('ai-assistant::messages.panel.confirm_rollback'),
            ],
        ]);
    }
}
