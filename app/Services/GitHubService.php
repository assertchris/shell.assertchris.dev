<?php

namespace App\Services;

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
        return $this->client()->get('/user/repos', $params);
    }

    public function repo(string $owner, string $repo): array
    {
        return $this->client()->get("/repos/{$owner}/{$repo}");
    }

    /** @param array<string, mixed> $params */
    public function pulls(string $owner, string $repo, array $params = []): array
    {
        return $this->client()->get("/repos/{$owner}/{$repo}/pulls", $params);
    }

    public function pull(string $owner, string $repo, int $number): array
    {
        return $this->client()->get("/repos/{$owner}/{$repo}/pulls/{$number}");
    }

    public function pullFiles(string $owner, string $repo, int $number): array
    {
        return $this->client()->get("/repos/{$owner}/{$repo}/pulls/{$number}/files");
    }

    public function pullCommits(string $owner, string $repo, int $number): array
    {
        return $this->client()->get("/repos/{$owner}/{$repo}/pulls/{$number}/commits");
    }

    public function pullReviews(string $owner, string $repo, int $number): array
    {
        return $this->client()->get("/repos/{$owner}/{$repo}/pulls/{$number}/reviews");
    }

    public function pullReviewComments(string $owner, string $repo, int $number, int $reviewId): array
    {
        return $this->client()->get("/repos/{$owner}/{$repo}/pulls/{$number}/reviews/{$reviewId}/comments");
    }

    public function pullRequestedReviewers(string $owner, string $repo, int $number): array
    {
        return $this->client()->get("/repos/{$owner}/{$repo}/pulls/{$number}/requested_reviewers");
    }

    /** @param array<string, mixed> $params */
    public function searchPulls(string $q, array $params = []): array
    {
        return $this->client()->get('/search/issues', array_merge($params, ['q' => "{$q} is:pr"]));
    }

    /** @param array<string, mixed> $params */
    public function searchIssues(string $q, array $params = []): array
    {
        return $this->client()->get('/search/issues', array_merge($params, ['q' => "{$q} is:issue"]));
    }

    /** @param array<string, mixed> $params */
    public function issues(array $params = []): array
    {
        return $this->client()->get('/issues', $params);
    }

    /** @param array<string, mixed> $params */
    public function userIssues(array $params = []): array
    {
        return $this->client()->get('/user/issues', $params);
    }

    /** @param array<string, mixed> $params */
    public function repoIssues(string $owner, string $repo, array $params = []): array
    {
        return $this->client()->get("/repos/{$owner}/{$repo}/issues", $params);
    }

    public function issue(string $owner, string $repo, int $number): array
    {
        return $this->client()->get("/repos/{$owner}/{$repo}/issues/{$number}");
    }

    public function issueComments(string $owner, string $repo, int $number): array
    {
        return $this->client()->get("/repos/{$owner}/{$repo}/issues/{$number}/comments");
    }

    /** @param array<string, mixed> $params */
    public function notifications(array $params = []): array
    {
        return $this->client()->get('/notifications', $params);
    }

    public function notification(string $threadId): array
    {
        return $this->client()->get("/notifications/threads/{$threadId}");
    }
}
