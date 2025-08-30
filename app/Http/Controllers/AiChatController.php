<?php

namespace App\Http\Controllers;

use App\Services\AiChatService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AiChatController extends Controller
{
    protected AiChatService $aiChatService;

    public function __construct(AiChatService $aiChatService)
    {
        $this->aiChatService = $aiChatService;
    }

    /**
     * Send a message to AI and get response
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'context' => 'required|string|in:dashboard,income,outcome,settings',
            'context_data' => 'nullable|array',
            'conversation_history' => 'nullable|array'
        ]);

        try {
            $response = $this->aiChatService->generateChatResponse(
                $request->message,
                $request->context,
                $request->context_data ?? [],
                $request->conversation_history ?? []
            );

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Maaf, terjadi kesalahan. Coba lagi ya!',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get conversation starters based on context
     */
    public function getStarters(Request $request): JsonResponse
    {
        $request->validate([
            'context' => 'nullable|string|in:dashboard,income,outcome,settings'
        ]);

        try {
            $context = $request->get('context', 'dashboard');
            $contextData = $request->get('context_data', []);

            $starters = $this->aiChatService->getConversationStarters($context, $contextData);

            return response()->json([
                'success' => true,
                'starters' => $starters
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'starters' => [],
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
