<?php

namespace App\Http\Controllers;

use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orders
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->orders->listForUser(
            (int) $validated['user_id'],
            (int) ($validated['per_page'] ?? 15)
        );

        return response()->json($paginator);
    }

    public function store(Request $request): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');
        if (! is_string($idempotencyKey) || $idempotencyKey === '') {
            return response()->json([
                'message' => 'Idempotency-Key header is required.',
            ], 422);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $result = $this->orders->create(
            (int) $validated['user_id'],
            $validated['items'],
            $idempotencyKey
        );

        return response()->json($result['body'], $result['status']);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
        ]);

        $order = $this->orders->findForUser($id, (int) $validated['user_id']);

        return response()->json($this->orders->serializeOrder($order));
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
        ]);

        $order = $this->orders->cancelForUser($id, (int) $validated['user_id']);

        return response()->json($this->orders->serializeOrder($order));
    }
}
