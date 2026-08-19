<?php

namespace App\Services;

use App\Exceptions\GitHub\GitHubAuthException;
use App\Exceptions\GitHub\GitHubRateLimitException;
use App\Exceptions\GitHub\GitHubScopeException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GitHubClient
{
    public function __construct(private string $pat) {}

    private function http(): PendingRequest
    {
        return Http::withHeaders(['Authorization' => "token {$this->pat}"])
            ->baseUrl('https://api.github.com');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<mixed>
     */
    public function get(string $path, array $query = []): array
    {
        $response = $this->http()->get($path, $query);

        return match ($response->status()) {
            200 => $response->json() ?? [],
            404 => [],
            401 => throw new GitHubAuthException,
            403 => throw new GitHubScopeException,
            429 => throw new GitHubRateLimitException((int) ($response->header('Retry-After') ?: 60)),
            default => throw new RuntimeException("GitHub API error: {$response->status()}"),
        };
    }
}
