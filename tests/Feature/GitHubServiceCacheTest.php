<?php

use AC\LaravelTaggedCache\TaggedCache;
use App\Models\Account;
use App\Services\GitHubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('cache.default', 'array');
});

test('GitHubService::repos uses tagged cache and skips closure on second call', function () {
    $account = Account::factory()->create();

    TaggedCache::for(
        tags: ['github', "github:{$account->id}"],
        context: ['type' => 'repos'],
    )->put([['id' => 1, 'name' => 'my-repo']], now()->addMinutes(5));

    $service = new GitHubService($account);

    $result = $service->repos();

    expect($result)->toBe([['id' => 1, 'name' => 'my-repo']]);
});

test('GitHubService account tag flush clears repos cache', function () {
    $account = Account::factory()->create();

    TaggedCache::for(
        tags: ['github', "github:{$account->id}"],
        context: ['type' => 'repos'],
    )->put([['id' => 1]], now()->addMinutes(5));

    expect(
        TaggedCache::for(tags: ['github', "github:{$account->id}"], context: ['type' => 'repos'])->get()
    )->not->toBeNull();

    TaggedCache::for(tags: ["github:{$account->id}"])->flush();

    expect(
        TaggedCache::for(tags: ['github', "github:{$account->id}"], context: ['type' => 'repos'])->get()
    )->toBeNull();
});

test('GitHubService::pull uses PR-scoped tag', function () {
    $account = Account::factory()->create();

    $cached = ['number' => 42, 'title' => 'My PR', 'state' => 'open'];

    TaggedCache::for(
        tags: ['github', "github:{$account->id}", "github:{$account->id}:pr:acme:myrepo:42"],
        context: ['type' => 'pull', 'owner' => 'acme', 'repo' => 'myrepo', 'number' => 42],
    )->put($cached, now()->addMinutes(5));

    $service = new GitHubService($account);

    $result = $service->pull('acme', 'myrepo', 42);

    expect($result)->toBe($cached);
});

test('GitHubService PR-scoped tag flush clears pull cache', function () {
    $account = Account::factory()->create();

    TaggedCache::for(
        tags: ['github', "github:{$account->id}", "github:{$account->id}:pr:acme:myrepo:42"],
        context: ['type' => 'pull', 'owner' => 'acme', 'repo' => 'myrepo', 'number' => 42],
    )->put(['number' => 42], now()->addMinutes(5));

    expect(
        TaggedCache::for(
            tags: ['github', "github:{$account->id}", "github:{$account->id}:pr:acme:myrepo:42"],
            context: ['type' => 'pull', 'owner' => 'acme', 'repo' => 'myrepo', 'number' => 42],
        )->get()
    )->not->toBeNull();

    TaggedCache::for(tags: ["github:{$account->id}:pr:acme:myrepo:42"])->flush();

    expect(
        TaggedCache::for(
            tags: ['github', "github:{$account->id}", "github:{$account->id}:pr:acme:myrepo:42"],
            context: ['type' => 'pull', 'owner' => 'acme', 'repo' => 'myrepo', 'number' => 42],
        )->get()
    )->toBeNull();
});

test('GitHubService::pullFiles uses PR-scoped tag', function () {
    $account = Account::factory()->create();

    TaggedCache::for(
        tags: ['github', "github:{$account->id}", "github:{$account->id}:pr:acme:myrepo:42"],
        context: ['type' => 'pull-files', 'owner' => 'acme', 'repo' => 'myrepo', 'number' => 42],
    )->put([['filename' => 'app/foo.php']], now()->addMinutes(5));

    $service = new GitHubService($account);

    $result = $service->pullFiles('acme', 'myrepo', 42);

    expect($result)->toBe([['filename' => 'app/foo.php']]);
});

test('GitHubService account tag flush clears all github caches for that account', function () {
    $account = Account::factory()->create();

    TaggedCache::for(
        tags: ['github', "github:{$account->id}"],
        context: ['type' => 'repos'],
    )->put([['id' => 1]], now()->addMinutes(5));

    TaggedCache::for(
        tags: ['github', "github:{$account->id}"],
        context: ['type' => 'notifications'],
    )->put([['id' => 'n1']], now()->addMinutes(5));

    TaggedCache::for(tags: ["github:{$account->id}"])->flush();

    expect(
        TaggedCache::for(tags: ['github', "github:{$account->id}"], context: ['type' => 'repos'])->get()
    )->toBeNull();

    expect(
        TaggedCache::for(tags: ['github', "github:{$account->id}"], context: ['type' => 'notifications'])->get()
    )->toBeNull();
});
