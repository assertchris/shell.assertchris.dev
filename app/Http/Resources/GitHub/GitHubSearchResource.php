<?php

namespace App\Http\Resources\GitHub;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GitHubSearchResource
{
    public function __construct(
        private readonly array $data,
        private readonly AnonymousResourceCollection $items,
    ) {}

    public function respond(): AnonymousResourceCollection
    {
        return $this->items->additional([
            'meta' => ['total_count' => $this->data['total_count'] ?? 0],
        ]);
    }
}
