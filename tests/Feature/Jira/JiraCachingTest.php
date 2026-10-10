<?php

use App\Models\Account;
use App\Models\User;
use App\Services\JiraService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('second identical projects call is served from cache', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(['values' => [], 'isLast' => true], 200)]);

    $account = Account::factory()->withJira()->for(User::factory()->create())->create();
    $service = new JiraService($account);

    $service->projects();
    $service->projects();

    Http::assertSentCount(1);
});

test('different account hits jira api separately', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(['values' => [], 'isLast' => true], 200)]);

    $accountA = Account::factory()->withJira()->for(User::factory()->create())->create();
    $accountB = Account::factory()->withJira()->for(User::factory()->create())->create();

    (new JiraService($accountA))->projects();
    (new JiraService($accountB))->projects();

    Http::assertSentCount(2);
});
