<?php

namespace App\Services;

use AC\LaravelTaggedCache\TaggedCache;
use App\Models\Account;

class LaravelCloudService
{
    public function __construct(private Account $account) {}

    private function client(): LaravelCloudClient
    {
        return new LaravelCloudClient($this->account->laravel_cloud_api_token);
    }

    /** @return string[] */
    private function tags(): array
    {
        return ['laravel-cloud', "laravel-cloud:{$this->account->id}"];
    }

    // -------------------------------------------------------------------------
    // Applications (slow: 5 min)
    // -------------------------------------------------------------------------

    /** @return array<mixed> */
    public function getApplications(): array
    {
        return TaggedCache::for(
            tags: $this->tags(),
            context: ['type' => 'applications'],
        )->remember(config('cache.ttl.laravel_cloud.slow'), fn () => $this->client()->get('/applications'));
    }

    /** @return array<mixed> */
    public function getApplication(string $id): array
    {
        return TaggedCache::for(
            tags: $this->tags(),
            context: ['type' => 'application', 'id' => $id],
        )->remember(config('cache.ttl.laravel_cloud.slow'), fn () => $this->client()->get("/applications/{$id}"));
    }

    // -------------------------------------------------------------------------
    // Environments (slow: 5 min)
    // -------------------------------------------------------------------------

    /** @return array<mixed> */
    public function getEnvironments(string $appId): array
    {
        return TaggedCache::for(
            tags: $this->tags(),
            context: ['type' => 'environments', 'appId' => $appId],
        )->remember(config('cache.ttl.laravel_cloud.slow'), fn () => $this->client()->get("/applications/{$appId}/environments"));
    }

    /** @return array<mixed> */
    public function getEnvironment(string $id): array
    {
        return TaggedCache::for(
            tags: $this->tags(),
            context: ['type' => 'environment', 'id' => $id],
        )->remember(config('cache.ttl.laravel_cloud.slow'), fn () => $this->client()->get("/environments/{$id}"));
    }

    // -------------------------------------------------------------------------
    // Environment logs (no cache)
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $query
     * @return array<mixed>
     */
    public function getEnvironmentLogs(string $id, array $query = []): array
    {
        return $this->client()->get("/environments/{$id}/logs", $query);
    }

    // -------------------------------------------------------------------------
    // Environment metrics (fast: 60 s)
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $query
     * @return array<mixed>
     */
    public function getEnvironmentMetrics(string $id, array $query = []): array
    {
        return TaggedCache::for(
            tags: $this->tags(),
            context: array_merge($query, ['type' => 'environment-metrics', 'id' => $id]),
        )->remember(config('cache.ttl.laravel_cloud.fast'), fn () => $this->client()->get("/environments/{$id}/metrics", $query));
    }

    // -------------------------------------------------------------------------
    // Deployments (fast: 60 s)
    // -------------------------------------------------------------------------

    /** @return array<mixed> */
    public function getDeployments(string $envId): array
    {
        return TaggedCache::for(
            tags: $this->tags(),
            context: ['type' => 'deployments', 'envId' => $envId],
        )->remember(config('cache.ttl.laravel_cloud.fast'), fn () => $this->client()->get("/environments/{$envId}/deployments"));
    }

    /** @return array<mixed> */
    public function getDeployment(string $id): array
    {
        return TaggedCache::for(
            tags: $this->tags(),
            context: ['type' => 'deployment', 'id' => $id],
        )->remember(config('cache.ttl.laravel_cloud.fast'), fn () => $this->client()->get("/deployments/{$id}"));
    }

    // -------------------------------------------------------------------------
    // Deployment logs (no cache)
    // -------------------------------------------------------------------------

    /** @return array<mixed> */
    public function getDeploymentLogs(string $id): array
    {
        return $this->client()->get("/deployments/{$id}/logs");
    }

    // -------------------------------------------------------------------------
    // Instances (fast: 60 s)
    // -------------------------------------------------------------------------

    /** @return array<mixed> */
    public function getInstances(string $envId): array
    {
        return TaggedCache::for(
            tags: $this->tags(),
            context: ['type' => 'instances', 'envId' => $envId],
        )->remember(config('cache.ttl.laravel_cloud.fast'), fn () => $this->client()->get("/environments/{$envId}/instances"));
    }

    /** @return array<mixed> */
    public function getInstance(string $id): array
    {
        return TaggedCache::for(
            tags: $this->tags(),
            context: ['type' => 'instance', 'id' => $id],
        )->remember(config('cache.ttl.laravel_cloud.fast'), fn () => $this->client()->get("/instances/{$id}"));
    }

    // -------------------------------------------------------------------------
    // Failed jobs (fast: 60 s)
    // -------------------------------------------------------------------------

    /** @return array<mixed> */
    public function getFailedJobs(string $instanceId): array
    {
        return TaggedCache::for(
            tags: $this->tags(),
            context: ['type' => 'failed-jobs', 'instanceId' => $instanceId],
        )->remember(config('cache.ttl.laravel_cloud.fast'), fn () => $this->client()->get("/instances/{$instanceId}/failed-jobs"));
    }

    // -------------------------------------------------------------------------
    // Commands (fast: 60 s for list; no cache for single / polling)
    // -------------------------------------------------------------------------

    /** @return array<mixed> */
    public function getCommands(string $envId): array
    {
        return TaggedCache::for(
            tags: $this->tags(),
            context: ['type' => 'commands', 'envId' => $envId],
        )->remember(config('cache.ttl.laravel_cloud.fast'), fn () => $this->client()->get("/environments/{$envId}/commands"));
    }

    /** @return array<mixed> */
    public function getCommand(string $id): array
    {
        return $this->client()->get("/commands/{$id}");
    }

    // -------------------------------------------------------------------------
    // Databases (slow: 5 min)
    // -------------------------------------------------------------------------

    /** @return array<mixed> */
    public function getDatabases(): array
    {
        return TaggedCache::for(
            tags: $this->tags(),
            context: ['type' => 'databases'],
        )->remember(config('cache.ttl.laravel_cloud.slow'), fn () => $this->client()->get('/databases/clusters'));
    }

    /** @return array<mixed> */
    public function getDatabase(string $id): array
    {
        return TaggedCache::for(
            tags: $this->tags(),
            context: ['type' => 'database', 'id' => $id],
        )->remember(config('cache.ttl.laravel_cloud.slow'), fn () => $this->client()->get("/databases/clusters/{$id}"));
    }

    /** @param array<string, mixed> $query
     * @return array<mixed>
     */
    public function getDatabaseMetrics(string $id, array $query = []): array
    {
        return TaggedCache::for(
            tags: $this->tags(),
            context: array_merge($query, ['type' => 'database-metrics', 'id' => $id]),
        )->remember(config('cache.ttl.laravel_cloud.fast'), fn () => $this->client()->get("/databases/clusters/{$id}/metrics", $query));
    }

    // -------------------------------------------------------------------------
    // Organization (slow: 5 min)
    // -------------------------------------------------------------------------

    /** @return array<mixed> */
    public function getOrganization(): array
    {
        return TaggedCache::for(
            tags: $this->tags(),
            context: ['type' => 'organization'],
        )->remember(config('cache.ttl.laravel_cloud.slow'), fn () => $this->client()->get('/meta/organization'));
    }

    // -------------------------------------------------------------------------
    // Regions (slow: 5 min)
    // -------------------------------------------------------------------------

    /** @return array<mixed> */
    public function getRegions(): array
    {
        return TaggedCache::for(
            tags: $this->tags(),
            context: ['type' => 'regions'],
        )->remember(config('cache.ttl.laravel_cloud.slow'), fn () => $this->client()->get('/meta/regions'));
    }

    // -------------------------------------------------------------------------
    // Usage (slow: 5 min)
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $query
     * @return array<mixed>
     */
    public function getUsage(array $query = []): array
    {
        return TaggedCache::for(
            tags: $this->tags(),
            context: array_merge($query, ['type' => 'usage']),
        )->remember(config('cache.ttl.laravel_cloud.slow'), fn () => $this->client()->get('/usage', $query));
    }

    // -------------------------------------------------------------------------
    // Caches (slow: 5 min)
    // -------------------------------------------------------------------------

    /** @return array<mixed> */
    public function getCaches(): array
    {
        return TaggedCache::for(
            tags: $this->tags(),
            context: ['type' => 'caches'],
        )->remember(config('cache.ttl.laravel_cloud.slow'), fn () => $this->client()->get('/caches'));
    }

    /** @return array<mixed> */
    public function getCache(string $id): array
    {
        return TaggedCache::for(
            tags: $this->tags(),
            context: ['type' => 'cache', 'id' => $id],
        )->remember(config('cache.ttl.laravel_cloud.slow'), fn () => $this->client()->get("/caches/{$id}"));
    }

    /** @param array<string, mixed> $query
     * @return array<mixed>
     */
    public function getCacheMetrics(string $id, array $query = []): array
    {
        return TaggedCache::for(
            tags: $this->tags(),
            context: array_merge($query, ['type' => 'cache-metrics', 'id' => $id]),
        )->remember(config('cache.ttl.laravel_cloud.fast'), fn () => $this->client()->get("/caches/{$id}/metrics", $query));
    }

    // -------------------------------------------------------------------------
    // Write operations (no cache; flush account tag after success)
    // -------------------------------------------------------------------------

    private function flushAccountCache(): void
    {
        TaggedCache::for(tags: ["laravel-cloud:{$this->account->id}"])->flush();
    }

    /** @param array<string, mixed> $body
     * @return array<mixed>
     */
    public function runCommand(string $envId, array $body): array
    {
        $result = $this->client()->post("/environments/{$envId}/commands", $body);
        $this->flushAccountCache();

        return $result;
    }

    /** @param array<string, mixed> $body
     * @return array<mixed>
     */
    public function purgeEdgeCache(string $envId, array $body): array
    {
        $result = $this->client()->post("/environments/{$envId}/purge-cache", $body);
        $this->flushAccountCache();

        return $result;
    }

    /** @return array<mixed> */
    public function startEnvironment(string $envId): array
    {
        $result = $this->client()->post("/environments/{$envId}/start");
        $this->flushAccountCache();

        return $result;
    }

    /** @return array<mixed> */
    public function stopEnvironment(string $envId): array
    {
        $result = $this->client()->post("/environments/{$envId}/stop");
        $this->flushAccountCache();

        return $result;
    }

    /** @return array<mixed> */
    public function retryFailedJob(string $instanceId, string $jobId): array
    {
        $result = $this->client()->post("/instances/{$instanceId}/failed-jobs/{$jobId}/retry");
        $this->flushAccountCache();

        return $result;
    }

    /** @return array<mixed> */
    public function pauseQueue(string $instanceId): array
    {
        $result = $this->client()->post("/instances/{$instanceId}/pause");
        $this->flushAccountCache();

        return $result;
    }

    /** @return array<mixed> */
    public function resumeQueue(string $instanceId): array
    {
        $result = $this->client()->post("/instances/{$instanceId}/resume");
        $this->flushAccountCache();

        return $result;
    }

    /** @return array<mixed> */
    public function purgeQueue(string $instanceId): array
    {
        $result = $this->client()->post("/instances/{$instanceId}/purge");
        $this->flushAccountCache();

        return $result;
    }
}
