<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    /**
     * List stores for storefront or admin selects.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->input('per_page', 50)));

        return response()->json(
            Store::query()->orderBy('name')->paginate($perPage)
        );
    }

    public function show(Store $store): JsonResponse
    {
        return response()->json(['store' => $store]);
    }
}
