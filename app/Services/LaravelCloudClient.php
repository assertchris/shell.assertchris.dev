<?php

namespace App\Services;

use App\Exceptions\LaravelCloud\LaravelCloudAuthException;
use App\Exceptions\LaravelCloud\LaravelCloudClientException;
use App\Exceptions\LaravelCloud\LaravelCloudRateLimitException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class LaravelCloudClient
{
    public function __construct(private string $apiToken) {}

    private function http(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => "Bearer {$this->apiToken}",
            'Accept' => 'application/vnd.api+json',
        ])->baseUrl('https://cloud.laravel.com/api')->timeout(30);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->handleResponse($this->http()->get($path, $query));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<mixed>
     */
    public function post(string $path, array $body = []): array
    {
        return $this->handleResponse($this->http()->post($path, $body));
    }

    /**
     * @return array<mixed>
     */
    private function handleResponse(Response $response): array
    {
        return match ($response->status()) {
            200, 201 => $response->json() ?? [],
            204, 404 => [],
            401 => throw new LaravelCloudAuthException,
            429 => throw new LaravelCloudRateLimitException((int) ($response->header('Retry-After') ?: 60)),
            default => throw new LaravelCloudClientException($response->status(), $response->body()),
        };
    }
}
