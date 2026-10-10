<?php

namespace App\Services;

use App\Models\Account;
use App\Support\CacheKey;
use Illuminate\Support\Facades\Cache;

class JiraService
{
    /** @var string[] */
    private const DEFAULT_FIELDS = ['summary', 'status', 'assignee', 'reporter', 'priority', 'issuetype', 'project', 'created', 'updated', 'description', 'comment', 'labels', 'fixVersions', 'components', 'attachment', 'parent', 'subtasks'];

    public function __construct(private Account $account) {}

    private function client(): JiraClient
    {
        return new JiraClient(
            $this->account->jira_base_url,
            $this->account->jira_email,
            $this->account->jira_api_token,
        );
    }

    /** @param array<string, mixed> $params */
    public function projects(array $params = []): array
    {
        return Cache::remember(
            CacheKey::hashed($params, 'jira', $this->account->id, 'projects'),
            now()->addMinutes(15),
            fn () => $this->client()->get('/project/search', $params),
        );
    }

    public function project(string $key): array
    {
        return Cache::remember(
            CacheKey::for('jira', $this->account->id, 'project', $key),
            now()->addMinutes(15),
            fn () => $this->client()->get("/project/{$key}"),
        );
    }

    public function projectComponents(string $key): array
    {
        return Cache::remember(
            CacheKey::for('jira', $this->account->id, 'project', $key, 'components'),
            now()->addMinutes(15),
            fn () => $this->client()->get("/project/{$key}/components"),
        );
    }

    public function projectVersions(string $key): array
    {
        return Cache::remember(
            CacheKey::for('jira', $this->account->id, 'project', $key, 'versions'),
            now()->addMinutes(15),
            fn () => $this->client()->get("/project/{$key}/versions"),
        );
    }

    /** @param array<string, mixed> $params */
    public function searchIssues(string $jql, array $params = []): array
    {
        return Cache::remember(
            CacheKey::hashed(array_merge(['jql' => $jql], $params), 'jira', $this->account->id, 'search'),
            now()->addMinutes(5),
            fn () => $this->client()->post('/search/jql', array_merge(['jql' => $jql, 'fields' => self::DEFAULT_FIELDS], $params)),
        );
    }

    /** @param array<string, mixed> $params */
    public function issue(string $key, array $params = []): array
    {
        return Cache::remember(
            CacheKey::for('jira', $this->account->id, 'issue', $key),
            now()->addMinutes(5),
            fn () => $this->client()->get("/issue/{$key}", array_merge(['fields' => implode(',', self::DEFAULT_FIELDS)], $params)),
        );
    }

    /** @param array<string, mixed> $params */
    public function issueComments(string $key, array $params = []): array
    {
        return Cache::remember(
            CacheKey::for('jira', $this->account->id, 'issue', $key, 'comments'),
            now()->addMinutes(5),
            fn () => $this->client()->get("/issue/{$key}/comment", $params),
        );
    }

    public function issueWorklogs(string $key): array
    {
        return Cache::remember(
            CacheKey::for('jira', $this->account->id, 'issue', $key, 'worklogs'),
            now()->addMinutes(5),
            fn () => $this->client()->get("/issue/{$key}/worklog"),
        );
    }

    /** @param array<string, mixed> $params */
    public function issueChangelog(string $key, array $params = []): array
    {
        return Cache::remember(
            CacheKey::for('jira', $this->account->id, 'issue', $key, 'changelog'),
            now()->addMinutes(5),
            fn () => $this->client()->get("/issue/{$key}/changelog", $params),
        );
    }

    public function issueTransitions(string $key): array
    {
        return Cache::remember(
            CacheKey::for('jira', $this->account->id, 'issue', $key, 'transitions'),
            now()->addMinutes(5),
            fn () => $this->client()->get("/issue/{$key}/transitions"),
        );
    }

    public function issueAttachments(string $key): array
    {
        return Cache::remember(
            CacheKey::for('jira', $this->account->id, 'issue', $key, 'attachments'),
            now()->addMinutes(5),
            function () use ($key) {
                $result = $this->client()->get("/issue/{$key}", ['fields' => 'attachment']);

                return $result['fields']['attachment'] ?? [];
            },
        );
    }

    /** @param array<string, mixed> $params */
    public function myAssignedIssues(array $params = []): array
    {
        return Cache::remember(
            CacheKey::hashed($params, 'jira', $this->account->id, 'user', 'assigned'),
            now()->addMinutes(5),
            fn () => $this->searchIssues('assignee = currentUser() ORDER BY updated DESC', $params),
        );
    }

    /** @param array<string, mixed> $params */
    public function myReportedIssues(array $params = []): array
    {
        return Cache::remember(
            CacheKey::hashed($params, 'jira', $this->account->id, 'user', 'reported'),
            now()->addMinutes(5),
            fn () => $this->searchIssues('reporter = currentUser() ORDER BY updated DESC', $params),
        );
    }

    /** @param array<string, mixed> $params */
    public function myWatchedIssues(array $params = []): array
    {
        return Cache::remember(
            CacheKey::hashed($params, 'jira', $this->account->id, 'user', 'watching'),
            now()->addMinutes(5),
            fn () => $this->searchIssues('watcher = currentUser() ORDER BY updated DESC', $params),
        );
    }

    /** @param array<string, mixed> $params */
    public function issuesMentioningMe(array $params = []): array
    {
        return Cache::remember(
            CacheKey::hashed($params, 'jira', $this->account->id, 'user', 'mentions'),
            now()->addMinutes(5),
            fn () => $this->searchIssues('text ~ currentUser() ORDER BY updated DESC', $params),
        );
    }

    /** @param array<string, mixed> $params */
    public function issuesWithRecentActivity(array $params = []): array
    {
        return Cache::remember(
            CacheKey::hashed($params, 'jira', $this->account->id, 'user', 'activity'),
            now()->addMinutes(5),
            fn () => $this->searchIssues('assignee = currentUser() AND updated >= -7d ORDER BY updated DESC', $params),
        );
    }

    /** @param array<string, mixed> $params */
    public function boards(array $params = []): array
    {
        return Cache::remember(
            CacheKey::hashed($params, 'jira', $this->account->id, 'boards'),
            now()->addMinutes(15),
            fn () => $this->client()->agileGet('/board', $params),
        );
    }

    public function board(int $boardId): array
    {
        return Cache::remember(
            CacheKey::for('jira', $this->account->id, 'board', $boardId),
            now()->addMinutes(15),
            fn () => $this->client()->agileGet("/board/{$boardId}"),
        );
    }

    /** @param array<string, mixed> $params */
    public function boardSprints(int $boardId, array $params = []): array
    {
        return Cache::remember(
            CacheKey::hashed($params, 'jira', $this->account->id, 'board', $boardId, 'sprints'),
            now()->addMinutes(15),
            fn () => $this->client()->agileGet("/board/{$boardId}/sprint", $params),
        );
    }

    public function sprint(int $sprintId): array
    {
        return Cache::remember(
            CacheKey::for('jira', $this->account->id, 'sprint', $sprintId),
            now()->addMinutes(10),
            fn () => $this->client()->agileGet("/sprint/{$sprintId}"),
        );
    }

    /** @param array<string, mixed> $params */
    public function sprintIssues(int $sprintId, array $params = []): array
    {
        return Cache::remember(
            CacheKey::hashed($params, 'jira', $this->account->id, 'sprint', $sprintId, 'issues'),
            now()->addMinutes(5),
            fn () => $this->client()->agileGet("/sprint/{$sprintId}/issue", $params),
        );
    }

    /** @param string[] $issueKeys */
    public function moveIssuesToSprint(int $sprintId, array $issueKeys): void
    {
        $this->client()->agilePost("/sprint/{$sprintId}/issue", ['issues' => $issueKeys]);
    }

    public function myself(): array
    {
        return Cache::remember(
            CacheKey::for('jira', $this->account->id, 'myself'),
            now()->addMinutes(30),
            fn () => $this->client()->get('/myself'),
        );
    }

    public function user(string $accountId): array
    {
        return Cache::remember(
            CacheKey::for('jira', $this->account->id, 'user-profile', $accountId),
            now()->addMinutes(15),
            fn () => $this->client()->get('/user', ['accountId' => $accountId]),
        );
    }

    public function assignableUsers(string $projectKey): array
    {
        return Cache::remember(
            CacheKey::for('jira', $this->account->id, 'assignable', $projectKey),
            now()->addMinutes(15),
            fn () => $this->client()->get('/user/assignable/search', ['project' => $projectKey]),
        );
    }

    public function filters(): array
    {
        return Cache::remember(
            CacheKey::for('jira', $this->account->id, 'filters'),
            now()->addMinutes(15),
            fn () => $this->client()->get('/filter/favourite'),
        );
    }

    public function filter(int $id): array
    {
        return Cache::remember(
            CacheKey::for('jira', $this->account->id, 'filter', $id),
            now()->addMinutes(15),
            fn () => $this->client()->get("/filter/{$id}"),
        );
    }

    public function dashboards(): array
    {
        return Cache::remember(
            CacheKey::for('jira', $this->account->id, 'dashboards'),
            now()->addMinutes(15),
            fn () => $this->client()->get('/dashboard'),
        );
    }

    /** @param array<string, mixed> $fields */
    public function createIssue(array $fields): array
    {
        return $this->client()->post('/issue', ['fields' => $fields]);
    }

    /** @param array<string, mixed> $fields */
    public function updateIssue(string $key, array $fields): void
    {
        $this->client()->put("/issue/{$key}", ['fields' => $fields]);
    }

    public function deleteIssue(string $key): void
    {
        $this->client()->delete("/issue/{$key}");
    }

    public function transitionIssue(string $key, string $transitionId): void
    {
        $this->client()->post("/issue/{$key}/transitions", ['transition' => ['id' => $transitionId]]);
    }

    public function assignIssue(string $key, ?string $accountId): void
    {
        $this->client()->put("/issue/{$key}/assignee", ['accountId' => $accountId]);
    }

    /** @param array<string, mixed> $adf */
    public function addComment(string $key, array $adf): array
    {
        return $this->client()->post("/issue/{$key}/comment", ['body' => $adf]);
    }

    /** @param array<string, mixed> $adf */
    public function updateComment(string $key, string $commentId, array $adf): array
    {
        return $this->client()->putJson("/issue/{$key}/comment/{$commentId}", ['body' => $adf]);
    }

    public function deleteComment(string $key, string $commentId): void
    {
        $this->client()->delete("/issue/{$key}/comment/{$commentId}");
    }

    /** @param array<string, mixed> $data */
    public function addWorklog(string $key, array $data): array
    {
        return $this->client()->post("/issue/{$key}/worklog", $data);
    }

    /** @param array<string, mixed> $data */
    public function updateWorklog(string $key, string $worklogId, array $data): array
    {
        return $this->client()->putJson("/issue/{$key}/worklog/{$worklogId}", $data);
    }

    public function deleteWorklog(string $key, string $worklogId): void
    {
        $this->client()->delete("/issue/{$key}/worklog/{$worklogId}");
    }

    public function addWatcher(string $key, string $accountId): void
    {
        $this->client()->postRaw("/issue/{$key}/watchers", json_encode($accountId));
    }

    public function removeWatcher(string $key, string $accountId): void
    {
        $this->client()->delete("/issue/{$key}/watchers", ['accountId' => $accountId]);
    }
}
