<?php

namespace App\Http\Controllers;

use App\Services\Gateway\ProxyService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GatewayController extends Controller
{
    public function __construct(
        private ProxyService $proxy
    ) {}

    public function catalog(Request $request, string $path = ''): Response
    {
        return $this->proxy->forward(
            $request,
            (string) config('services.catalog.url'),
            '/api/catalog/'.ltrim($path, '/')
        );
    }

    public function catalogAdmin(Request $request, string $path = ''): Response
    {
        return $this->proxy->forward(
            $request,
            (string) config('services.catalog.url'),
            '/api/admin/'.ltrim($path, '/')
        );
    }

    public function uploadUrl(Request $request): Response
    {
        return $this->proxy->forward(
            $request,
            (string) config('services.catalog.url'),
            '/api/get-upload-url'
        );
    }

    public function notifications(Request $request, string $path = ''): Response
    {
        $suffix = $path === '' ? '' : '/'.ltrim($path, '/');

        return $this->proxy->forward(
            $request,
            (string) config('services.notification.url'),
            '/api/notifications'.$suffix
        );
    }

    public function orders(Request $request, string $path = ''): Response
    {
        $suffix = $path === '' ? '' : '/'.ltrim($path, '/');
        $userId = (int) $request->user()->id;

        return $this->proxy->forward(
            $request,
            (string) config('services.order.url'),
            '/api/orders'.$suffix,
            $userId
        );
    }

    public function ordersAdmin(Request $request, string $path = ''): Response
    {
        $suffix = $path === '' ? '' : '/'.ltrim($path, '/');

        return $this->proxy->forward(
            $request,
            (string) config('services.order.url'),
            '/api/admin/orders'.$suffix
        );
    }

    public function stripeWebhook(Request $request): Response
    {
        return $this->proxy->forward(
            $request,
            (string) config('services.order.url'),
            '/api/webhooks/stripe'
        );
    }
}
