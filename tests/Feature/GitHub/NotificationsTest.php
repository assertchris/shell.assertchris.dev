<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeNotificationData(array $overrides = []): array
{
    return array_merge([
        'id' => '1',
        'reason' => 'mention',
        'unread' => true,
        'subject' => [
            'title' => 'Bug report',
            'type' => 'Issue',
            'url' => 'https://api.github.com/repos/owner/repo/issues/1',
        ],
        'repository' => [
            'full_name' => 'owner/repo',
        ],
        'updated_at' => '2024-01-01T00:00:00Z',
    ], $overrides);
}

test('list notifications returns notification collection', function () {
    Http::fake(['https://api.github.com/notifications*' => Http::response([makeNotificationData()], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $response = $this->getJson("/api/{$account->id}/github/notifications", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => [['id', 'reason', 'unread', 'subject', 'repository']], 'meta' => ['total_count']]);
});

test('list notifications forwards all param', function () {
    Http::fake(['https://api.github.com/notifications*' => Http::response([], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/github/notifications?all=1", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])->assertOk();

    Http::assertSent(function ($request) {
        parse_str(parse_url($request->url(), PHP_URL_QUERY), $params);

        return ($params['all'] ?? null) === '1';
    });
});

test('get single notification thread returns notification data', function () {
    Http::fake(['https://api.github.com/notifications/threads/42*' => Http::response(makeNotificationData(['id' => '42']), 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $response = $this->getJson("/api/{$account->id}/github/notifications/42", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.id', '42')
        ->assertJsonPath('data.reason', 'mention');
});

test('get notification returns 404 when not found', function () {
    Http::fake(['https://api.github.com/notifications/threads/999*' => Http::response([], 404)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/github/notifications/999", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])->assertNotFound();
});
