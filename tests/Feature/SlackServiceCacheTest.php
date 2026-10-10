<?php

use AC\LaravelTaggedCache\TaggedCache;
use App\Models\Account;
use App\Services\SlackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('cache.default', 'array');
});

test('SlackService::listChannels uses tagged cache and skips closure on second call', function () {
    $account = Account::factory()->create();

    TaggedCache::for(
        tags: ['slack', "slack:{$account->id}"],
        context: ['type' => 'channels', 'cursor' => null],
    )->put([['id' => 'C001', 'name' => 'general', 'is_private' => false, 'num_members' => 10]], now()->addMinutes(15));

    $service = new SlackService;

    $result = $service->listChannels($account);

    expect($result)->toBe([['id' => 'C001', 'name' => 'general', 'is_private' => false, 'num_members' => 10]]);
});

test('SlackService account tag flush clears channels cache', function () {
    $account = Account::factory()->create();

    TaggedCache::for(
        tags: ['slack', "slack:{$account->id}"],
        context: ['type' => 'channels', 'cursor' => null],
    )->put([['id' => 'C001']], now()->addMinutes(15));

    expect(
        TaggedCache::for(
            tags: ['slack', "slack:{$account->id}"],
            context: ['type' => 'channels', 'cursor' => null],
        )->get()
    )->not->toBeNull();

    TaggedCache::for(tags: ["slack:{$account->id}"])->flush();

    expect(
        TaggedCache::for(
            tags: ['slack', "slack:{$account->id}"],
            context: ['type' => 'channels', 'cursor' => null],
        )->get()
    )->toBeNull();
});

test('SlackService::listMessages uses tagged cache', function () {
    $account = Account::factory()->create();

    $cached = ['messages' => [['ts' => '1234', 'user' => 'U001', 'text' => 'hi']], 'next_cursor' => null];

    TaggedCache::for(
        tags: ['slack', "slack:{$account->id}", "slack:{$account->id}:channel:C001"],
        context: ['type' => 'messages', 'channelId' => 'C001', 'limit' => 50, 'cursor' => null],
    )->put($cached, now()->addMinutes(3));

    $service = new SlackService;

    $result = $service->listMessages($account, 'C001');

    expect($result)->toBe($cached);
});

test('SlackService::listUsers uses tagged cache', function () {
    $account = Account::factory()->create();

    $cached = ['users' => [['id' => 'U001', 'name' => 'alice', 'real_name' => 'Alice', 'is_bot' => false]], 'next_cursor' => null];

    TaggedCache::for(
        tags: ['slack', "slack:{$account->id}"],
        context: ['type' => 'users', 'cursor' => null],
    )->put($cached, now()->addMinutes(30));

    $service = new SlackService;

    $result = $service->listUsers($account);

    expect($result)->toBe($cached);
});

test('SlackService account tag flush clears all slack caches', function () {
    $account = Account::factory()->create();

    TaggedCache::for(
        tags: ['slack', "slack:{$account->id}"],
        context: ['type' => 'channels', 'cursor' => null],
    )->put([['id' => 'C001']], now()->addMinutes(15));

    TaggedCache::for(
        tags: ['slack', "slack:{$account->id}"],
        context: ['type' => 'users', 'cursor' => null],
    )->put(['users' => [], 'next_cursor' => null], now()->addMinutes(30));

    TaggedCache::for(tags: ["slack:{$account->id}"])->flush();

    expect(
        TaggedCache::for(tags: ['slack', "slack:{$account->id}"], context: ['type' => 'channels', 'cursor' => null])->get()
    )->toBeNull();

    expect(
        TaggedCache::for(tags: ['slack', "slack:{$account->id}"], context: ['type' => 'users', 'cursor' => null])->get()
    )->toBeNull();
});
