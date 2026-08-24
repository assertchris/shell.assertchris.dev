<?php

namespace App\Services;

use App\Exceptions\Jira\JiraAuthException;
use App\Exceptions\Jira\JiraClientException;
use App\Exceptions\Jira\JiraForbiddenException;
use App\Exceptions\Jira\JiraRateLimitException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class JiraClient
{
    public function __construct(
        private string $baseUrl,
        private string $email,
        private string $apiToken,
    ) {}

    private function http(): PendingRequest
    {
        return Http::withBasicAuth($this->email, $this->apiToken)
            ->baseUrl(rtrim($this->baseUrl, '/').'/rest/api/3');
    }

    private function agileHttp(): PendingRequest
    {
        return Http::withBasicAuth($this->email, $this->apiToken)
            ->baseUrl(rtrim($this->baseUrl, '/').'/rest/agile/1.0');
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
     * @param  array<string, mixed>  $query
     * @return array<mixed>
     */
    public function agileGet(string $path, array $query = []): array
    {
        return $this->handleResponse($this->agileHttp()->get($path, $query));
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
     * @param  array<string, mixed>  $body
     * @return array<mixed>
     */
    public function agilePost(string $path, array $body = []): array
    {
        return $this->handleResponse($this->agileHttp()->post($path, $body));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<mixed>
     */
    public function putJson(string $path, array $body = []): array
    {
        return $this->handleResponse($this->http()->put($path, $body));
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function put(string $path, array $body = []): void
    {
        $this->handleResponse($this->http()->put($path, $body));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function delete(string $path, array $query = []): void
    {
        $this->handleResponse($this->http()->delete($path, $query));
    }

    public function postRaw(string $path, string $body): void
    {
        $response = Http::withBasicAuth($this->email, $this->apiToken)
            ->baseUrl(rtrim($this->baseUrl, '/').'/rest/api/3')
            ->withBody($body, 'application/json')
            ->post($path);

        $this->handleResponse($response);
    }

    /**
     * @return array<mixed>
     */
    private function handleResponse(Response $response): array
    {
        return match ($response->status()) {
            200, 201 => $response->json() ?? [],
            204 => [],
            404 => [],
            400 => throw new JiraClientException(400, 'Jira API error: '.$response->body()),
            401 => throw new JiraAuthException,
            403 => throw new JiraForbiddenException,
            429 => throw new JiraRateLimitException((int) ($response->header('Retry-After') ?: 60)),
            default => throw new JiraClientException($response->status(), "Jira API error: {$response->status()}"),
        };
    }
}
