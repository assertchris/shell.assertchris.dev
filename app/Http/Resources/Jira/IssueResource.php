<?php

namespace App\Http\Resources\Jira;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IssueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fields = $this->resource['fields'] ?? [];

        return [
            'id' => $this->resource['id'],
            'key' => $this->resource['key'],
            'summary' => $fields['summary'] ?? null,
            'status' => $fields['status']['name'] ?? null,
            'assignee' => [
                'account_id' => $fields['assignee']['accountId'] ?? null,
                'display_name' => $fields['assignee']['displayName'] ?? null,
            ],
            'reporter' => [
                'account_id' => $fields['reporter']['accountId'] ?? null,
                'display_name' => $fields['reporter']['displayName'] ?? null,
            ],
            'priority' => $fields['priority']['name'] ?? null,
            'issuetype' => $fields['issuetype']['name'] ?? null,
            'project' => [
                'key' => $fields['project']['key'] ?? null,
                'name' => $fields['project']['name'] ?? null,
            ],
            'description' => $fields['description'] ?? null,
            'created' => $fields['created'] ?? null,
            'updated' => $fields['updated'] ?? null,
            'labels' => $fields['labels'] ?? [],
            'fix_versions' => array_map(fn (array $v) => $v['name'], $fields['fixVersions'] ?? []),
            'components' => array_map(fn (array $c) => $c['name'], $fields['components'] ?? []),
            'parent' => isset($fields['parent']) ? ['key' => $fields['parent']['key'] ?? null, 'summary' => $fields['parent']['fields']['summary'] ?? null] : null,
            'subtasks' => array_map(fn (array $s) => ['key' => $s['key'], 'summary' => $s['fields']['summary'] ?? null], $fields['subtasks'] ?? []),
        ];
    }
}
