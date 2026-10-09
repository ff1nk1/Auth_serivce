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
     * @param  array{id: int, email?: string|null, name?: string|null}|null  $injectUser
     *         When set, merges user_id (and email/name when present) into query and JSON body.
     */
    public function forward(Request $request, string $baseUrl, string $path, ?array $injectUser = null): Response
    {
        $url = rtrim($baseUrl, '/').'/'.ltrim($path, '/');

        $pending = Http::withHeaders($this->forwardHeaders($request))
            ->acceptJson()
            ->timeout(30);

        $method = strtolower($request->method());
        $query = $request->query();
        $body = $request->all();

        if ($injectUser !== null) {
            $query['user_id'] = $injectUser['id'];
            $body['user_id'] = $injectUser['id'];

            if (isset($injectUser['email']) && is_string($injectUser['email']) && $injectUser['email'] !== '') {
                $query['email'] = $injectUser['email'];
                $body['email'] = $injectUser['email'];
            }

            if (isset($injectUser['name']) && is_string($injectUser['name']) && $injectUser['name'] !== '') {
                $query['name'] = $injectUser['name'];
                $body['name'] = $injectUser['name'];
            }
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
     * Forward raw request body (e.g. Stripe webhooks) without re-encoding JSON.
     */
    public function forwardRaw(Request $request, string $baseUrl, string $path): Response
    {
        $url = rtrim($baseUrl, '/').'/'.ltrim($path, '/');

        $headers = [
            'Accept' => 'application/json',
            'X-Forwarded-For' => $request->ip() ?? '',
            'X-Forwarded-Proto' => $request->getScheme(),
        ];

        if ($request->hasHeader('Content-Type')) {
            $headers['Content-Type'] = (string) $request->header('Content-Type');
        }

        if ($request->hasHeader('Stripe-Signature')) {
            $headers['Stripe-Signature'] = (string) $request->header('Stripe-Signature');
        }

        /** @var HttpResponse $upstream */
        $upstream = Http::withHeaders(array_filter($headers))
            ->withBody($request->getContent(), (string) ($request->header('Content-Type') ?: 'application/json'))
            ->timeout(30)
            ->send($request->method(), $url);

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
