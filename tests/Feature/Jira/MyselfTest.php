<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('get myself returns 200 with user resource fields', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([
        'accountId' => 'acc-123',
        'displayName' => 'Alice Smith',
        'emailAddress' => 'alice@example.com',
        'active' => true,
        'avatarUrls' => ['48x48' => 'https://example.com/avatar.png'],
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/myself", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonPath('data.account_id', 'acc-123')
        ->assertJsonPath('data.display_name', 'Alice Smith')
        ->assertJsonPath('data.email_address', 'alice@example.com')
        ->assertJsonPath('data.active', true)
        ->assertJsonStructure(['data' => ['account_id', 'display_name', 'email_address', 'active', 'avatar_urls']]);
});
