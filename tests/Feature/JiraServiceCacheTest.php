<?php

use AC\LaravelTaggedCache\TaggedCache;
use App\Models\Account;
use App\Services\JiraService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('cache.default', 'array');
});

test('JiraService::myself uses tagged cache and skips closure on second call', function () {
    $account = Account::factory()->create();

    TaggedCache::for(
        tags: ['jira', "jira:{$account->id}"],
        context: ['type' => 'myself'],
    )->put(['accountId' => 'abc123'], now()->addMinutes(30));

    $service = new JiraService($account);

    $result = $service->myself();

    expect($result)->toBe(['accountId' => 'abc123']);
});

test('JiraService account tag flush clears myself cache', function () {
    $account = Account::factory()->create();

    TaggedCache::for(
        tags: ['jira', "jira:{$account->id}"],
        context: ['type' => 'myself'],
    )->put(['accountId' => 'abc123'], now()->addMinutes(30));

    expect(
        TaggedCache::for(tags: ['jira', "jira:{$account->id}"], context: ['type' => 'myself'])->get()
    )->not->toBeNull();

    TaggedCache::for(tags: ["jira:{$account->id}"])->flush();

    expect(
        TaggedCache::for(tags: ['jira', "jira:{$account->id}"], context: ['type' => 'myself'])->get()
    )->toBeNull();
});

test('JiraService::issue uses issue-scoped tag', function () {
    $account = Account::factory()->create();

    TaggedCache::for(
        tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:issue:PROJ-1"],
        context: ['type' => 'issue', 'key' => 'PROJ-1'],
    )->put(['key' => 'PROJ-1', 'fields' => []], now()->addMinutes(5));

    $service = new JiraService($account);

    $result = $service->issue('PROJ-1');

    expect($result)->toBe(['key' => 'PROJ-1', 'fields' => []]);
});

test('JiraService issue-scoped tag flush clears issue cache', function () {
    $account = Account::factory()->create();

    TaggedCache::for(
        tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:issue:PROJ-1"],
        context: ['type' => 'issue', 'key' => 'PROJ-1'],
    )->put(['key' => 'PROJ-1'], now()->addMinutes(5));

    expect(
        TaggedCache::for(
            tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:issue:PROJ-1"],
            context: ['type' => 'issue', 'key' => 'PROJ-1'],
        )->get()
    )->not->toBeNull();

    TaggedCache::for(tags: ["jira:{$account->id}:issue:PROJ-1"])->flush();

    expect(
        TaggedCache::for(
            tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:issue:PROJ-1"],
            context: ['type' => 'issue', 'key' => 'PROJ-1'],
        )->get()
    )->toBeNull();
});

test('JiraService::sprintIssues uses sprint-scoped tag', function () {
    $account = Account::factory()->create();

    TaggedCache::for(
        tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:sprint:42", "jira:{$account->id}:sprint:42:issues"],
        context: ['type' => 'sprint-issues', 'sprintId' => 42],
    )->put(['issues' => []], now()->addMinutes(5));

    $service = new JiraService($account);

    $result = $service->sprintIssues(42);

    expect($result)->toBe(['issues' => []]);
});

test('JiraService sprint-issues tag flush clears sprint issues cache without clearing sprint metadata', function () {
    $account = Account::factory()->create();

    TaggedCache::for(
        tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:sprint:42", "jira:{$account->id}:sprint:42:issues"],
        context: ['type' => 'sprint-issues', 'sprintId' => 42],
    )->put(['issues' => []], now()->addMinutes(5));

    TaggedCache::for(
        tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:sprint:42"],
        context: ['type' => 'sprint', 'sprintId' => 42],
    )->put(['id' => 42, 'name' => 'Sprint 1'], now()->addMinutes(10));

    expect(
        TaggedCache::for(
            tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:sprint:42", "jira:{$account->id}:sprint:42:issues"],
            context: ['type' => 'sprint-issues', 'sprintId' => 42],
        )->get()
    )->not->toBeNull();

    TaggedCache::for(tags: ["jira:{$account->id}:sprint:42:issues"])->flush();

    expect(
        TaggedCache::for(
            tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:sprint:42", "jira:{$account->id}:sprint:42:issues"],
            context: ['type' => 'sprint-issues', 'sprintId' => 42],
        )->get()
    )->toBeNull();

    expect(
        TaggedCache::for(
            tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:sprint:42"],
            context: ['type' => 'sprint', 'sprintId' => 42],
        )->get()
    )->not->toBeNull();
});
