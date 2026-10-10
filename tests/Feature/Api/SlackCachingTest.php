<?php

use App\Models\Account;
use App\Models\User;
use App\Services\SlackService;
use App\Support\CacheKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('second identical listChannels call is served from cache', function () {
    Http::fake([
        'slack.com/api/conversations.list*' => Http::response([
            'ok' => true,
            'channels' => [['id' => 'C001', 'name' => 'general', 'is_private' => false, 'num_members' => 5]],
            'response_metadata' => ['next_cursor' => ''],
        ]),
    ]);

    $account = Account::factory()->withSlack()->for(User::factory()->create())->create();
    $service = app(SlackService::class);

    $service->listChannels($account);
    $service->listChannels($account);

    Http::assertSentCount(1);
});

test('sendMessage busts the default listMessages cache key for the channel', function () {
    Http::fake([
        'slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'ts' => '99999']),
    ]);

    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->for($user)->create();
    $token = $user->createToken('test');
    $channelId = 'C001';

    $bustKey = CacheKey::hashed([], 'slack', $account->id, 'messages', $channelId);
    Cache::put($bustKey, ['messages' => [], 'next_cursor' => null], now()->addMinutes(3));

    expect(Cache::has($bustKey))->toBeTrue();

    $this->postJson("/api/slack/{$account->id}/channels/{$channelId}/messages", ['text' => 'hi'], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])->assertSuccessful();

    expect(Cache::missing($bustKey))->toBeTrue();
});
