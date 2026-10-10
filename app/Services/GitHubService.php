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
            context: array_merge(['type' => 'repos'], $params),
        )->remember(now()->addMinutes(5), fn () => $this->client()->get('/user/repos', $params));
    }

    public function repo(string $owner, string $repo): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}"],
            context: ['type' => 'repo', 'owner' => $owner, 'repo' => $repo],
        )->remember(now()->addMinutes(5), fn () => $this->client()->get("/repos/{$owner}/{$repo}"));
    }

    /** @param array<string, mixed> $params */
    public function pulls(string $owner, string $repo, array $params = []): array
    {
        $ttl = ($params['state'] ?? 'open') === 'open'
            ? now()->addSeconds(60)
            : now()->addMinutes(5);

        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}"],
            context: array_merge(['type' => 'pulls', 'owner' => $owner, 'repo' => $repo], $params),
        )->remember($ttl, fn () => $this->client()->get("/repos/{$owner}/{$repo}/pulls", $params));
    }

    public function pull(string $owner, string $repo, int $number): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}", "github:{$this->account->id}:pr:{$owner}:{$repo}:{$number}"],
            context: ['type' => 'pull', 'owner' => $owner, 'repo' => $repo, 'number' => $number],
        )->remember(now()->addMinutes(5), fn () => $this->client()->get("/repos/{$owner}/{$repo}/pulls/{$number}"));
    }

    public function pullFiles(string $owner, string $repo, int $number): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}", "github:{$this->account->id}:pr:{$owner}:{$repo}:{$number}"],
            context: ['type' => 'pull-files', 'owner' => $owner, 'repo' => $repo, 'number' => $number],
        )->remember(now()->addMinutes(5), fn () => $this->client()->get("/repos/{$owner}/{$repo}/pulls/{$number}/files"));
    }

    public function pullCommits(string $owner, string $repo, int $number): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}", "github:{$this->account->id}:pr:{$owner}:{$repo}:{$number}"],
            context: ['type' => 'pull-commits', 'owner' => $owner, 'repo' => $repo, 'number' => $number],
        )->remember(now()->addMinutes(5), fn () => $this->client()->get("/repos/{$owner}/{$repo}/pulls/{$number}/commits"));
    }

    public function pullReviews(string $owner, string $repo, int $number): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}", "github:{$this->account->id}:pr:{$owner}:{$repo}:{$number}"],
            context: ['type' => 'pull-reviews', 'owner' => $owner, 'repo' => $repo, 'number' => $number],
        )->remember(now()->addMinutes(5), fn () => $this->client()->get("/repos/{$owner}/{$repo}/pulls/{$number}/reviews"));
    }

    public function pullReviewComments(string $owner, string $repo, int $number, int $reviewId): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}", "github:{$this->account->id}:pr:{$owner}:{$repo}:{$number}"],
            context: ['type' => 'pull-review-comments', 'owner' => $owner, 'repo' => $repo, 'number' => $number, 'reviewId' => $reviewId],
        )->remember(now()->addMinutes(5), fn () => $this->client()->get("/repos/{$owner}/{$repo}/pulls/{$number}/reviews/{$reviewId}/comments"));
    }

    public function pullRequestedReviewers(string $owner, string $repo, int $number): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}", "github:{$this->account->id}:pr:{$owner}:{$repo}:{$number}"],
            context: ['type' => 'pull-reviewers', 'owner' => $owner, 'repo' => $repo, 'number' => $number],
        )->remember(now()->addMinutes(5), fn () => $this->client()->get("/repos/{$owner}/{$repo}/pulls/{$number}/requested_reviewers"));
    }

    /** @param array<string, mixed> $params */
    public function searchPulls(string $q, array $params = []): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}"],
            context: array_merge(['type' => 'search-pulls', 'q' => $q], $params),
        )->remember(now()->addMinutes(5), fn () => $this->client()->get('/search/issues', array_merge($params, ['q' => "{$q} is:pr"])));
    }

    /** @param array<string, mixed> $params */
    public function searchIssues(string $q, array $params = []): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}"],
            context: array_merge(['type' => 'search-issues', 'q' => $q], $params),
        )->remember(now()->addMinutes(5), fn () => $this->client()->get('/search/issues', array_merge($params, ['q' => "{$q} is:issue"])));
    }

    /** @param array<string, mixed> $params */
    public function issues(array $params = []): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}"],
            context: array_merge(['type' => 'issues'], $params),
        )->remember(now()->addMinutes(5), fn () => $this->client()->get('/issues', $params));
    }

    /** @param array<string, mixed> $params */
    public function userIssues(array $params = []): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}"],
            context: array_merge(['type' => 'user-issues'], $params),
        )->remember(now()->addMinutes(5), fn () => $this->client()->get('/user/issues', $params));
    }

    /** @param array<string, mixed> $params */
    public function repoIssues(string $owner, string $repo, array $params = []): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}"],
            context: array_merge(['type' => 'repo-issues', 'owner' => $owner, 'repo' => $repo], $params),
        )->remember(now()->addMinutes(5), fn () => $this->client()->get("/repos/{$owner}/{$repo}/issues", $params));
    }

    public function issue(string $owner, string $repo, int $number): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}"],
            context: ['type' => 'issue', 'owner' => $owner, 'repo' => $repo, 'number' => $number],
        )->remember(now()->addMinutes(5), fn () => $this->client()->get("/repos/{$owner}/{$repo}/issues/{$number}"));
    }

    public function issueComments(string $owner, string $repo, int $number): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}"],
            context: ['type' => 'issue-comments', 'owner' => $owner, 'repo' => $repo, 'number' => $number],
        )->remember(now()->addMinutes(5), fn () => $this->client()->get("/repos/{$owner}/{$repo}/issues/{$number}/comments"));
    }

    /** @param array<string, mixed> $params */
    public function notifications(array $params = []): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}"],
            context: array_merge(['type' => 'notifications'], $params),
        )->remember(now()->addMinutes(5), fn () => $this->client()->get('/notifications', $params));
    }

    public function notification(string $threadId): array
    {
        return TaggedCache::for(
            tags: ['github', "github:{$this->account->id}"],
            context: ['type' => 'notification', 'threadId' => $threadId],
        )->remember(now()->addMinutes(5), fn () => $this->client()->get("/notifications/threads/{$threadId}"));
    }
}
