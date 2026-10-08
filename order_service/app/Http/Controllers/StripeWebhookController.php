<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        Log::info('Stripe webhook stub received', [
            'payload' => $request->all(),
        ]);

        return response()->json(['received' => true]);
    }
}
