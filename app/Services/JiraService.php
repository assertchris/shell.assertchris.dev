<?php

namespace App\Services;

use App\Models\Account;

class JiraService
{
    private const DEFAULT_FIELDS = 'summary,status,assignee,reporter,priority,issuetype,project,created,updated,description,comment,labels,fixVersions,components,attachment,parent,subtasks';

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
        return $this->client()->get('/project/search', $params);
    }

    public function project(string $key): array
    {
        return $this->client()->get("/project/{$key}");
    }

    public function projectComponents(string $key): array
    {
        return $this->client()->get("/project/{$key}/components");
    }

    public function projectVersions(string $key): array
    {
        return $this->client()->get("/project/{$key}/versions");
    }

    /** @param array<string, mixed> $params */
    public function searchIssues(string $jql, array $params = []): array
    {
        return $this->client()->post('/search', array_merge(['jql' => $jql, 'fields' => self::DEFAULT_FIELDS], $params));
    }

    /** @param array<string, mixed> $params */
    public function issue(string $key, array $params = []): array
    {
        return $this->client()->get("/issue/{$key}", array_merge(['fields' => self::DEFAULT_FIELDS], $params));
    }

    /** @param array<string, mixed> $params */
    public function issueComments(string $key, array $params = []): array
    {
        return $this->client()->get("/issue/{$key}/comment", $params);
    }

    public function issueWorklogs(string $key): array
    {
        return $this->client()->get("/issue/{$key}/worklog");
    }

    /** @param array<string, mixed> $params */
    public function issueChangelog(string $key, array $params = []): array
    {
        return $this->client()->get("/issue/{$key}/changelog", $params);
    }

    public function issueTransitions(string $key): array
    {
        return $this->client()->get("/issue/{$key}/transitions");
    }

    public function issueAttachments(string $key): array
    {
        $result = $this->client()->get("/issue/{$key}", ['fields' => 'attachment']);

        return $result['fields']['attachment'] ?? [];
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
        return $this->client()->agileGet('/board', $params);
    }

    public function board(int $boardId): array
    {
        return $this->client()->agileGet("/board/{$boardId}");
    }

    /** @param array<string, mixed> $params */
    public function boardSprints(int $boardId, array $params = []): array
    {
        return $this->client()->agileGet("/board/{$boardId}/sprint", $params);
    }

    public function sprint(int $sprintId): array
    {
        return $this->client()->agileGet("/sprint/{$sprintId}");
    }

    /** @param array<string, mixed> $params */
    public function sprintIssues(int $sprintId, array $params = []): array
    {
        return $this->client()->agileGet("/sprint/{$sprintId}/issue", $params);
    }

    public function myself(): array
    {
        return $this->client()->get('/myself');
    }

    public function user(string $accountId): array
    {
        return $this->client()->get('/user', ['accountId' => $accountId]);
    }

    public function assignableUsers(string $projectKey): array
    {
        return $this->client()->get('/user/assignable/search', ['project' => $projectKey]);
    }

    public function filters(): array
    {
        return $this->client()->get('/filter/favourite');
    }

    public function filter(int $id): array
    {
        return $this->client()->get("/filter/{$id}");
    }

    public function dashboards(): array
    {
        return $this->client()->get('/dashboard');
    }
}
