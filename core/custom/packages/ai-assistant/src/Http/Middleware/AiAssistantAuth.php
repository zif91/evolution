<?php

namespace EvolutionCMS\AiAssistant\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AiAssistantAuth
{
    /**
     * Handle an incoming request.
     * Ensure user is logged in to the manager and has appropriate permissions.
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if user is logged in to the manager
        if (!isset($_SESSION['mgrValidated']) || $_SESSION['mgrValidated'] !== 1) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized. Please log in to the manager.',
            ], 401);
        }

        // Check for minimum permissions
        $hasPermission = evo()->hasPermission('view_document');

        if (!$hasPermission) {
            return response()->json([
                'success' => false,
                'error' => 'Insufficient permissions.',
            ], 403);
        }

        return $next($request);
    }
}
