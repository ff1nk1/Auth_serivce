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
     */
    public function forward(Request $request, string $baseUrl, string $path): Response
    {
        $url = rtrim($baseUrl, '/').'/'.ltrim($path, '/');

        $pending = Http::withHeaders($this->forwardHeaders($request))
            ->acceptJson()
            ->timeout(30);

        $method = strtolower($request->method());

        /** @var HttpResponse $upstream */
        $upstream = match ($method) {
            'get' => $pending->get($url, $request->query()),
            'delete' => $pending->delete($url, $request->query()),
            'post' => $pending->asJson()->post($url, $request->all()),
            'put' => $pending->asJson()->put($url, $request->all()),
            'patch' => $pending->asJson()->patch($url, $request->all()),
            default => $pending->send($method, $url, [
                'query' => $request->query(),
                'json' => $request->all(),
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
