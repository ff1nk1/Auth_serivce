<?php

namespace App\Services\Gateway;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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
     * Forward raw HTML / Livewire / asset requests (Filament panel) with identity headers.
     *
     * @param  array{id: int, role: string, name?: string, email?: string}  $identity
     */
    public function forwardHtml(Request $request, string $baseUrl, string $path, array $identity): Response
    {
        $url = rtrim($baseUrl, '/').'/'.ltrim($path, '/');

        if ($query = $request->getQueryString()) {
            $url .= '?'.$query;
        }

        $publicHost = $request->getHost();
        $publicPort = $request->getPort();
        $hostHeader = $publicHost;
        if ($publicPort && ! in_array((int) $publicPort, [80, 443], true)) {
            $hostHeader .= ':'.$publicPort;
        }

        $headers = [
            'Host' => $hostHeader,
            'X-User-Id' => (string) $identity['id'],
            'X-User-Role' => (string) $identity['role'],
            'X-Forwarded-For' => $request->ip() ?? '',
            'X-Forwarded-Proto' => $request->getScheme(),
            'X-Forwarded-Host' => $publicHost,
            'X-Forwarded-Port' => (string) $publicPort,
        ];

        if (! empty($identity['name'])) {
            $headers['X-User-Name'] = (string) $identity['name'];
        }

        if (! empty($identity['email'])) {
            $headers['X-User-Email'] = (string) $identity['email'];
        }

        foreach (['Accept', 'Accept-Language', 'Content-Type', 'X-Livewire', 'X-CSRF-TOKEN', 'X-XSRF-TOKEN', 'Referer', 'Origin'] as $name) {
            if ($request->headers->has($name)) {
                $headers[$name] = (string) $request->header($name);
            }
        }

        if ($request->headers->has('Cookie')) {
            $headers['Cookie'] = (string) $request->header('Cookie');
        }

        $method = strtoupper($request->method());
        $files = $request->allFiles();

        // multipart/form-data leaves php://input empty after PHP parses $_FILES —
        // rebuild the body from uploaded files instead of getContent().
        if ($files !== [] && ! in_array($method, ['GET', 'HEAD'], true)) {
            unset($headers['Content-Type']);

            $pending = Http::withHeaders(array_filter($headers, fn ($value) => $value !== null && $value !== ''))
                ->withOptions(['allow_redirects' => false])
                ->timeout(60);

            $this->attachUploadedFiles($pending, $files);

            /** @var HttpResponse $upstream */
            $upstream = match ($method) {
                'POST' => $pending->post($url, $request->request->all()),
                'PUT' => $pending->put($url, $request->request->all()),
                'PATCH' => $pending->patch($url, $request->request->all()),
                default => $pending->send($method, $url, [
                    'multipart' => $this->multipartFields($request->request->all()),
                ]),
            };

            return $this->htmlResponse($upstream);
        }

        $pending = Http::withHeaders(array_filter($headers, fn ($value) => $value !== null && $value !== ''))
            ->withOptions(['allow_redirects' => false])
            ->timeout(60);

        $content = $request->getContent();
        $contentType = (string) ($request->header('Content-Type') ?: 'application/octet-stream');

        /** @var HttpResponse $upstream */
        if ($content !== '' && ! in_array($method, ['GET', 'HEAD'], true)) {
            $upstream = $pending->withBody($content, $contentType)->send($method, $url);
        } else {
            $upstream = $pending->send($method, $url);
        }

        return $this->htmlResponse($upstream);
    }

    /**
     * @param  array<string, mixed>  $files
     */
    private function attachUploadedFiles(PendingRequest $pending, array $files, string $prefix = ''): void
    {
        foreach ($files as $key => $file) {
            $name = $prefix === '' ? (string) $key : "{$prefix}[{$key}]";

            if (is_array($file)) {
                $this->attachUploadedFiles($pending, $file, $name);

                continue;
            }

            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $pending->attach(
                $name,
                file_get_contents($file->getRealPath()) ?: '',
                $file->getClientOriginalName(),
                ['Content-Type' => $file->getMimeType() ?: 'application/octet-stream'],
            );
        }
    }

    /**
     * Flatten request fields into Guzzle multipart name/contents pairs.
     *
     * @param  array<string, mixed>  $fields
     * @return list<array{name: string, contents: string}>
     */
    private function multipartFields(array $fields, string $prefix = ''): array
    {
        $parts = [];

        foreach ($fields as $key => $value) {
            $name = $prefix === '' ? (string) $key : "{$prefix}[{$key}]";

            if (is_array($value)) {
                $parts = array_merge($parts, $this->multipartFields($value, $name));

                continue;
            }

            $parts[] = [
                'name' => $name,
                'contents' => is_scalar($value) || $value === null ? (string) $value : '',
            ];
        }

        return $parts;
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

    private function htmlResponse(HttpResponse $upstream): Response
    {
        $response = response($upstream->body(), $upstream->status());

        $psr = $upstream->toPsrResponse();

        foreach (['Content-Type', 'Content-Disposition', 'Cache-Control', 'Pragma', 'Expires', 'Location'] as $name) {
            foreach ($psr->getHeader($name) as $value) {
                $response->headers->set($name, $value, false);
            }
        }

        foreach ($psr->getHeader('Set-Cookie') as $cookie) {
            $response->headers->set('Set-Cookie', $cookie, false);
        }

        return $response;
    }
}
