<?php

namespace App\Services\Gateway;

use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class ProxyService
{
    /**
     * Forward the incoming request to an internal microservice and return its response.
     *
     * @param  int|null  $injectUserId  When set, merges user_id into query and JSON body.
     */
    public function forward(Request $request, string $baseUrl, string $path, ?int $injectUserId = null): Response
    {
        $url = rtrim($baseUrl, '/').'/'.ltrim($path, '/');

        $pending = Http::withHeaders($this->forwardHeaders($request))
            ->acceptJson()
            ->timeout(30);

        $method = strtolower($request->method());
        $query = $request->query();
        $body = $request->all();

        if ($injectUserId !== null) {
            $query['user_id'] = $injectUserId;
            $body['user_id'] = $injectUserId;
        }

        /** @var HttpResponse $upstream */
        $upstream = match ($method) {
            'get' => $pending->get($url, $query),
            'delete' => $pending->delete($url, $query),
            'post' => $pending->asJson()->post($url, $body),
            'put' => $pending->asJson()->put($url, $body),
            'patch' => $pending->asJson()->patch($url, $body),
            default => $pending->send($method, $url, [
                'query' => $query,
                'json' => $body,
            ]),
        };

        return response($upstream->body(), $upstream->status())
            ->withHeaders($this->responseHeaders($upstream));
    }

    /**
     * @return array<string, string>
     */
    private function forwardHeaders(Request $request): array
    {
        $headers = [
            'Accept' => 'application/json',
            'X-Forwarded-For' => $request->ip() ?? '',
            'X-Forwarded-Proto' => $request->getScheme(),
        ];

        if ($request->hasHeader('Content-Type')) {
            $headers['Content-Type'] = $request->header('Content-Type');
        }

        if ($request->hasHeader('Idempotency-Key')) {
            $headers['Idempotency-Key'] = (string) $request->header('Idempotency-Key');
        }

        return array_filter($headers);
    }

    /**
     * @return array<string, string>
     */
    private function responseHeaders(HttpResponse $upstream): array
    {
        $headers = [];
        $contentType = $upstream->header('Content-Type');

        if ($contentType) {
            $headers['Content-Type'] = $contentType;
        }

        return $headers;
    }
}
