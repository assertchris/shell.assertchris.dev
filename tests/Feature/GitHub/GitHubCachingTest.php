<?php

use App\Models\Account;
use App\Models\User;
use App\Services\GitHubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('second identical repos call is served from cache', function () {
    Http::fake(['https://api.github.com/*' => Http::response([[
        'id' => 1,
        'name' => 'my-repo',
        'full_name' => 'owner/my-repo',
        'description' => 'A test repo',
        'private' => false,
        'html_url' => 'https://github.com/owner/my-repo',
        'default_branch' => 'main',
        'updated_at' => '2024-01-01T00:00:00Z',
    ]], 200)]);

    $account = Account::factory()->withGitHub()->for(User::factory()->create())->create();
    $service = new GitHubService($account);

    $service->repos();
    $service->repos();

    Http::assertSentCount(1);
});

test('different account hits github api separately', function () {
    Http::fake(['https://api.github.com/*' => Http::response([[
        'id' => 1,
        'name' => 'my-repo',
        'full_name' => 'owner/my-repo',
        'description' => 'A test repo',
        'private' => false,
        'html_url' => 'https://github.com/owner/my-repo',
        'default_branch' => 'main',
        'updated_at' => '2024-01-01T00:00:00Z',
    ]], 200)]);

    $accountA = Account::factory()->withGitHub()->for(User::factory()->create())->create();
    $accountB = Account::factory()->withGitHub()->for(User::factory()->create())->create();

    (new GitHubService($accountA))->repos();
    (new GitHubService($accountB))->repos();

    Http::assertSentCount(2);
});
