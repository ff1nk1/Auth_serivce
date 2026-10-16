<?php

namespace App\Http\Controllers;

use App\Services\Chat\RecommendationChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ConversationController extends Controller
{
    public function __construct(
        private readonly RecommendationChatService $chat,
    ) {}

    public function show(Request $request, string $id): JsonResponse
    {
        $userId = $this->requireUserId($request);
        $conversation = $this->chat->getConversation($userId, $id);

        $messages = $conversation->messages()
            ->orderBy('created_at')
            ->get(['id', 'role', 'content', 'created_at']);

        return response()->json([
            'conversation_id' => $conversation->id,
            'user_id' => $conversation->user_id,
            'last_activity_at' => $conversation->last_activity_at?->toIso8601String(),
            'expires_at' => $conversation->expires_at?->toIso8601String(),
            'messages' => $messages,
        ]);
    }

    public function destroy(Request $request, string $id): Response
    {
        $userId = $this->requireUserId($request);
        $this->chat->deleteConversation($userId, $id);

        return response()->noContent();
    }

    private function requireUserId(Request $request): string
    {
        $userId = $request->input('user_id') ?? $request->query('user_id');
        if ($userId === null || $userId === '') {
            abort(422, 'user_id is required');
        }

        return (string) $userId;
    }
}
