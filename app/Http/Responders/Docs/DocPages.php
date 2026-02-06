<?php

namespace App\Http\Responders\Docs;

class DocPages
{
    /**
     * @return array<string, array{title: string, file: string}>
     */
    public static function all(): array
    {
        return [
            'authentication' => ['title' => 'Authentication', 'file' => 'authentication.md'],
            'mailboxes' => ['title' => 'Mailboxes', 'file' => 'mailboxes.md'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function titles(): array
    {
        return array_map(fn (array $page): string => $page['title'], static::all());
    }
}
