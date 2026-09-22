<?php

namespace App\Services\Meta;

use App\Exceptions\MetaGraphException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class MetaGraphClient
{
    public function __construct(private readonly HttpFactory $http)
    {
    }

    public function get(string $path, ?string $accessToken = null, array $query = []): array
    {
        return $this->send('GET', $path, $accessToken, $query);
    }

    public function post(string $path, string $accessToken, array $payload = []): array
    {
        return $this->send('POST', $path, $accessToken, [], $payload);
    }

    public function delete(string $path, string $accessToken, array $query = []): array
    {
        return $this->send('DELETE', $path, $accessToken, $query);
    }

    public function collect(string $path, string $accessToken, array $query = [], int $maxPages = 20): array
    {
        $response = $this->get($path, $accessToken, $query);
        $items = $response['data'] ?? [];
        $nextUrl = $response['paging']['next'] ?? null;
        $fetchedPages = 1;

        while ($nextUrl !== null && $fetchedPages < $maxPages) {
            $response = $this->decode(fn () => $this->baseRequest()->get($nextUrl));
            $items = array_merge($items, $response['data'] ?? []);
            $nextUrl = $response['paging']['next'] ?? null;
            $fetchedPages++;
        }

        return $items;
    }

    private function send(string $method, string $path, ?string $accessToken, array $query, array $payload = []): array
    {
        $request = $this->baseRequest()
            ->baseUrl($this->baseUrl())
            ->withQueryParameters(array_merge($query, $this->authenticationParameters($accessToken)));

        $url = ltrim($path, '/');

        return $this->decode(fn () => match ($method) {
            'POST' => $request->post($url, $payload),
            'DELETE' => $request->delete($url),
            default => $request->get($url),
        });
    }

    private function decode(callable $sendRequest): array
    {
        try {
            $response = $sendRequest();
        } catch (ConnectionException $exception) {
            throw MetaGraphException::fromThrowable($exception);
        }

        if ($response->failed()) {
            throw MetaGraphException::fromResponse($response);
        }

        return $this->body($response);
    }

    private function body(Response $response): array
    {
        $decoded = $response->json();

        if (is_array($decoded)) {
            return $decoded;
        }

        return ['success' => $response->successful()];
    }

    private function baseRequest(): PendingRequest
    {
        return $this->http
            ->acceptJson()
            ->timeout((int) config('meta.graph_timeout', 15));
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('meta.graph_base_url'), '/').'/'.config('meta.graph_version');
    }

    private function authenticationParameters(?string $accessToken): array
    {
        if ($accessToken === null) {
            return [];
        }

        $parameters = ['access_token' => $accessToken];
        $appSecret = (string) config('services.facebook.client_secret');

        if ($appSecret !== '') {
            $parameters['appsecret_proof'] = hash_hmac('sha256', $accessToken, $appSecret);
        }

        return $parameters;
    }
}
