<?php

namespace App\Services;

use AC\LaravelTaggedCache\TaggedCache;
use App\Models\Account;

class GitHubService
{
    public function __construct(private Account $account) {}

    private function client(): GitHubClient
    {
        return new GitHubClient($this->account->github_pat);
    }

    /** @param array<string, mixed> $params */
    public function repos(array $params = []): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}"],
            context: array_merge($params, ['type' => 'repos']),
        )->remember(config('cache.ttl.github.default'), fn () => $this->client()->get('/user/repos', $params));
    }

    public function repo(string $owner, string $repo): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}"],
            context: ['type' => 'repo', 'owner' => $owner, 'repo' => $repo],
        )->remember(config('cache.ttl.github.default'), fn () => $this->client()->get("/repos/{$owner}/{$repo}"));
    }

    /** @param array<string, mixed> $params */
    public function pulls(string $owner, string $repo, array $params = []): array
    {
        $ttl = ($params['state'] ?? 'open') === 'open'
            ? config('cache.ttl.github.pr_open')
            : config('cache.ttl.github.default');

        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}"],
            context: array_merge($params, ['type' => 'pulls', 'owner' => $owner, 'repo' => $repo]),
        )->remember($ttl, fn () => $this->client()->get("/repos/{$owner}/{$repo}/pulls", $params));
    }

    public function pull(string $owner, string $repo, int $number): array
    {
        $cache = TaggedCache::for(
            tags: ['github', "github:{$this->account->id}", "github:{$this->account->id}:pr:{$owner}:{$repo}:{$number}"],
            context: ['type' => 'pull', 'owner' => $owner, 'repo' => $repo, 'number' => $number],
        );

        $cached = $cache->get();
        if ($cached !== null) {
            return $cached;
        }

        $pull = $this->client()->get("/repos/{$owner}/{$repo}/pulls/{$number}");
        $ttl = ($pull['state'] ?? '') === 'open' ? config('cache.ttl.github.pr_open') : config('cache.ttl.github.default');
        $cache->put($pull, $ttl);

        return $pull;
    }

    public function pullFiles(string $owner, string $repo, int $number): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}", "github:{$this->account->id}:pr:{$owner}:{$repo}:{$number}"],
            context: ['type' => 'pull-files', 'owner' => $owner, 'repo' => $repo, 'number' => $number],
        )->remember(config('cache.ttl.github.default'), fn () => $this->client()->get("/repos/{$owner}/{$repo}/pulls/{$number}/files"));
    }

    public function pullCommits(string $owner, string $repo, int $number): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}", "github:{$this->account->id}:pr:{$owner}:{$repo}:{$number}"],
            context: ['type' => 'pull-commits', 'owner' => $owner, 'repo' => $repo, 'number' => $number],
        )->remember(config('cache.ttl.github.default'), fn () => $this->client()->get("/repos/{$owner}/{$repo}/pulls/{$number}/commits"));
    }

    public function pullReviews(string $owner, string $repo, int $number): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}", "github:{$this->account->id}:pr:{$owner}:{$repo}:{$number}"],
            context: ['type' => 'pull-reviews', 'owner' => $owner, 'repo' => $repo, 'number' => $number],
        )->remember(config('cache.ttl.github.default'), fn () => $this->client()->get("/repos/{$owner}/{$repo}/pulls/{$number}/reviews"));
    }

    public function pullReviewComments(string $owner, string $repo, int $number, int $reviewId): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}", "github:{$this->account->id}:pr:{$owner}:{$repo}:{$number}"],
            context: ['type' => 'pull-review-comments', 'owner' => $owner, 'repo' => $repo, 'number' => $number, 'reviewId' => $reviewId],
        )->remember(config('cache.ttl.github.default'), fn () => $this->client()->get("/repos/{$owner}/{$repo}/pulls/{$number}/reviews/{$reviewId}/comments"));
    }

    public function pullRequestedReviewers(string $owner, string $repo, int $number): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}", "github:{$this->account->id}:pr:{$owner}:{$repo}:{$number}"],
            context: ['type' => 'pull-reviewers', 'owner' => $owner, 'repo' => $repo, 'number' => $number],
        )->remember(config('cache.ttl.github.default'), fn () => $this->client()->get("/repos/{$owner}/{$repo}/pulls/{$number}/requested_reviewers"));
    }

    /** @param array<string, mixed> $params */
    public function searchPulls(string $q, array $params = []): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}"],
            context: array_merge($params, ['type' => 'search-pulls', 'q' => $q]),
        )->remember(config('cache.ttl.github.default'), fn () => $this->client()->get('/search/issues', array_merge($params, ['q' => "{$q} is:pr"])));
    }

    /** @param array<string, mixed> $params */
    public function searchIssues(string $q, array $params = []): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}"],
            context: array_merge($params, ['type' => 'search-issues', 'q' => $q]),
        )->remember(config('cache.ttl.github.default'), fn () => $this->client()->get('/search/issues', array_merge($params, ['q' => "{$q} is:issue"])));
    }

    /** @param array<string, mixed> $params */
    public function issues(array $params = []): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}"],
            context: array_merge($params, ['type' => 'issues']),
        )->remember(config('cache.ttl.github.default'), fn () => $this->client()->get('/issues', $params));
    }

    /** @param array<string, mixed> $params */
    public function userIssues(array $params = []): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}"],
            context: array_merge($params, ['type' => 'user-issues']),
        )->remember(config('cache.ttl.github.default'), fn () => $this->client()->get('/user/issues', $params));
    }

    /** @param array<string, mixed> $params */
    public function repoIssues(string $owner, string $repo, array $params = []): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}"],
            context: array_merge($params, ['type' => 'repo-issues', 'owner' => $owner, 'repo' => $repo]),
        )->remember(config('cache.ttl.github.default'), fn () => $this->client()->get("/repos/{$owner}/{$repo}/issues", $params));
    }

    public function issue(string $owner, string $repo, int $number): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}"],
            context: ['type' => 'issue', 'owner' => $owner, 'repo' => $repo, 'number' => $number],
        )->remember(config('cache.ttl.github.default'), fn () => $this->client()->get("/repos/{$owner}/{$repo}/issues/{$number}"));
    }

    public function issueComments(string $owner, string $repo, int $number): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}"],
            context: ['type' => 'issue-comments', 'owner' => $owner, 'repo' => $repo, 'number' => $number],
        )->remember(config('cache.ttl.github.default'), fn () => $this->client()->get("/repos/{$owner}/{$repo}/issues/{$number}/comments"));
    }

    /** @param array<string, mixed> $params */
    public function notifications(array $params = []): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}"],
            context: array_merge($params, ['type' => 'notifications']),
        )->remember(config('cache.ttl.github.default'), fn () => $this->client()->get('/notifications', $params));
    }

    public function notification(string $threadId): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}"],
            context: ['type' => 'notification', 'threadId' => $threadId],
        )->remember(config('cache.ttl.github.default'), fn () => $this->client()->get("/notifications/threads/{$threadId}"));
    }
}
