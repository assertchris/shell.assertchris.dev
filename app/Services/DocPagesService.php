<?php

namespace App\Services;

class DocPagesService
{
    /**
     * @return array<string, array{title: string, file: string}>
     */
    public function all(): array
    {
        return [
            'authentication' => ['title' => 'Authentication', 'file' => 'authentication.md'],
            'mailboxes' => ['title' => 'Mailboxes', 'file' => 'mailboxes.md'],
            'calendars' => ['title' => 'Calendars', 'file' => 'calendars.md'],
            'slack' => ['title' => 'Slack', 'file' => 'slack.md'],
            'github' => ['title' => 'GitHub', 'file' => 'github.md'],
            'jira' => ['title' => 'Jira', 'file' => 'jira.md'],
            'laravel-cloud' => ['title' => 'Laravel Cloud', 'file' => 'laravel-cloud.md'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function titles(): array
    {
        return array_map(fn (array $page): string => $page['title'], $this->all());
    }
}
