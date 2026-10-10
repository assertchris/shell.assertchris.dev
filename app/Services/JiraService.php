<?php

namespace App\Services;

use AC\LaravelTaggedCache\TaggedCache;
use App\Models\Account;

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
        return TaggedCache::for(
            tags: ['jira', "jira:{$this->account->id}"],
            context: array_merge(['type' => 'projects'], $params),
        )->remember(now()->addMinutes(15), fn () => $this->client()->get('/project/search', $params));
    }

    public function project(string $key): array
    {
        return TaggedCache::for(
            tags: ['jira', "jira:{$this->account->id}"],
            context: ['type' => 'project', 'key' => $key],
        )->remember(now()->addMinutes(15), fn () => $this->client()->get("/project/{$key}"));
    }

    public function projectComponents(string $key): array
    {
        return TaggedCache::for(
            tags: ['jira', "jira:{$this->account->id}"],
            context: ['type' => 'project-components', 'key' => $key],
        )->remember(now()->addMinutes(15), fn () => $this->client()->get("/project/{$key}/components"));
    }

    public function projectVersions(string $key): array
    {
        return TaggedCache::for(
            tags: ['jira', "jira:{$this->account->id}"],
            context: ['type' => 'project-versions', 'key' => $key],
        )->remember(now()->addMinutes(15), fn () => $this->client()->get("/project/{$key}/versions"));
    }

    /** @param array<string, mixed> $params */
    public function searchIssues(string $jql, array $params = []): array
    {
        return TaggedCache::for(
            tags: ['jira', "jira:{$this->account->id}"],
            context: array_merge(['type' => 'search', 'jql' => $jql], $params),
        )->remember(now()->addMinutes(5), fn () => $this->client()->post('/search/jql', array_merge(['jql' => $jql, 'fields' => self::DEFAULT_FIELDS], $params)));
    }

    /** @param array<string, mixed> $params */
    public function issue(string $key, array $params = []): array
    {
        return TaggedCache::for(
            tags: ['jira', "jira:{$this->account->id}", "jira:{$this->account->id}:issue:{$key}"],
            context: ['type' => 'issue', 'key' => $key],
        )->remember(now()->addMinutes(5), fn () => $this->client()->get("/issue/{$key}", array_merge(['fields' => implode(',', self::DEFAULT_FIELDS)], $params)));
    }

    /** @param array<string, mixed> $params */
    public function issueComments(string $key, array $params = []): array
    {
        return TaggedCache::for(
            tags: ['jira', "jira:{$this->account->id}", "jira:{$this->account->id}:issue:{$key}"],
            context: ['type' => 'comments', 'key' => $key],
        )->remember(now()->addMinutes(5), fn () => $this->client()->get("/issue/{$key}/comment", $params));
    }

    public function issueWorklogs(string $key): array
    {
        return TaggedCache::for(
            tags: ['jira', "jira:{$this->account->id}", "jira:{$this->account->id}:issue:{$key}"],
            context: ['type' => 'worklogs', 'key' => $key],
        )->remember(now()->addMinutes(5), fn () => $this->client()->get("/issue/{$key}/worklog"));
    }

    /** @param array<string, mixed> $params */
    public function issueChangelog(string $key, array $params = []): array
    {
        return TaggedCache::for(
            tags: ['jira', "jira:{$this->account->id}", "jira:{$this->account->id}:issue:{$key}"],
            context: ['type' => 'changelog', 'key' => $key],
        )->remember(now()->addMinutes(5), fn () => $this->client()->get("/issue/{$key}/changelog", $params));
    }

    public function issueTransitions(string $key): array
    {
        return TaggedCache::for(
            tags: ['jira', "jira:{$this->account->id}", "jira:{$this->account->id}:issue:{$key}"],
            context: ['type' => 'transitions', 'key' => $key],
        )->remember(now()->addMinutes(5), fn () => $this->client()->get("/issue/{$key}/transitions"));
    }

    public function issueAttachments(string $key): array
    {
        return TaggedCache::for(
            tags: ['jira', "jira:{$this->account->id}", "jira:{$this->account->id}:issue:{$key}"],
            context: ['type' => 'attachments', 'key' => $key],
        )->remember(now()->addMinutes(5), function () use ($key) {
            $result = $this->client()->get("/issue/{$key}", ['fields' => 'attachment']);

            return $result['fields']['attachment'] ?? [];
        });
    }

    /** @param array<string, mixed> $params */
    public function myAssignedIssues(array $params = []): array
    {
        return $this->searchIssues('assignee = currentUser() ORDER BY updated DESC', $params);
    }

    /** @param array<string, mixed> $params */
    public function myReportedIssues(array $params = []): array
    {
        return $this->searchIssues('reporter = currentUser() ORDER BY updated DESC', $params);
    }

    /** @param array<string, mixed> $params */
    public function myWatchedIssues(array $params = []): array
    {
        return $this->searchIssues('watcher = currentUser() ORDER BY updated DESC', $params);
    }

    /** @param array<string, mixed> $params */
    public function issuesMentioningMe(array $params = []): array
    {
        return $this->searchIssues('text ~ currentUser() ORDER BY updated DESC', $params);
    }

    /** @param array<string, mixed> $params */
    public function issuesWithRecentActivity(array $params = []): array
    {
        return $this->searchIssues('assignee = currentUser() AND updated >= -7d ORDER BY updated DESC', $params);
    }

    /** @param array<string, mixed> $params */
    public function boards(array $params = []): array
    {
        return TaggedCache::for(
            tags: ['jira', "jira:{$this->account->id}"],
            context: array_merge(['type' => 'boards'], $params),
        )->remember(now()->addMinutes(15), fn () => $this->client()->agileGet('/board', $params));
    }

    public function board(int $boardId): array
    {
        return TaggedCache::for(
            tags: ['jira', "jira:{$this->account->id}"],
            context: ['type' => 'board', 'boardId' => $boardId],
        )->remember(now()->addMinutes(15), fn () => $this->client()->agileGet("/board/{$boardId}"));
    }

    /** @param array<string, mixed> $params */
    public function boardSprints(int $boardId, array $params = []): array
    {
        return TaggedCache::for(
            tags: ['jira', "jira:{$this->account->id}"],
            context: array_merge(['type' => 'board-sprints', 'boardId' => $boardId], $params),
        )->remember(now()->addMinutes(15), fn () => $this->client()->agileGet("/board/{$boardId}/sprint", $params));
    }

    public function sprint(int $sprintId): array
    {
        return TaggedCache::for(
            tags: ['jira', "jira:{$this->account->id}", "jira:{$this->account->id}:sprint:{$sprintId}"],
            context: ['type' => 'sprint', 'sprintId' => $sprintId],
        )->remember(now()->addMinutes(10), fn () => $this->client()->agileGet("/sprint/{$sprintId}"));
    }

    /** @param array<string, mixed> $params */
    public function sprintIssues(int $sprintId, array $params = []): array
    {
        return TaggedCache::for(
            tags: ['jira', "jira:{$this->account->id}", "jira:{$this->account->id}:sprint:{$sprintId}"],
            context: array_merge(['type' => 'sprint-issues', 'sprintId' => $sprintId], $params),
        )->remember(now()->addMinutes(5), fn () => $this->client()->agileGet("/sprint/{$sprintId}/issue", $params));
    }

    /** @param string[] $issueKeys */
    public function moveIssuesToSprint(int $sprintId, array $issueKeys): void
    {
        $this->client()->agilePost("/sprint/{$sprintId}/issue", ['issues' => $issueKeys]);
    }

    public function myself(): array
    {
        return TaggedCache::for(
            tags: ['jira', "jira:{$this->account->id}"],
            context: ['account' => $this->account->id],
        )->remember(now()->addMinutes(30), fn () => $this->client()->get('/myself'));
    }

    public function user(string $accountId): array
    {
        return TaggedCache::for(
            tags: ['jira', "jira:{$this->account->id}"],
            context: ['type' => 'user-profile', 'userId' => $accountId],
        )->remember(now()->addMinutes(15), fn () => $this->client()->get('/user', ['accountId' => $accountId]));
    }

    public function assignableUsers(string $projectKey): array
    {
        return TaggedCache::for(
            tags: ['jira', "jira:{$this->account->id}"],
            context: ['type' => 'assignable', 'projectKey' => $projectKey],
        )->remember(now()->addMinutes(15), fn () => $this->client()->get('/user/assignable/search', ['project' => $projectKey]));
    }

    public function filters(): array
    {
        return TaggedCache::for(
            tags: ['jira', "jira:{$this->account->id}"],
            context: ['account' => $this->account->id, 'type' => 'filters'],
        )->remember(now()->addMinutes(15), fn () => $this->client()->get('/filter/favourite'));
    }

    public function filter(int $id): array
    {
        return TaggedCache::for(
            tags: ['jira', "jira:{$this->account->id}"],
            context: ['type' => 'filter', 'id' => $id],
        )->remember(now()->addMinutes(15), fn () => $this->client()->get("/filter/{$id}"));
    }

    public function dashboards(): array
    {
        return TaggedCache::for(
            tags: ['jira', "jira:{$this->account->id}"],
            context: ['account' => $this->account->id, 'type' => 'dashboards'],
        )->remember(now()->addMinutes(15), fn () => $this->client()->get('/dashboard'));
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
