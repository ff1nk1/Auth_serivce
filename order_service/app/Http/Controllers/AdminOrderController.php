<?php

namespace App\Http\Controllers;

use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function __construct(
        private OrderService $orders
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->orders->listAll((int) ($validated['per_page'] ?? 15));

        return response()->json($paginator);
    }

    public function show(int $id): JsonResponse
    {
        $order = $this->orders->findAny($id);

        return response()->json($this->orders->serializeOrder($order));
    }
}
