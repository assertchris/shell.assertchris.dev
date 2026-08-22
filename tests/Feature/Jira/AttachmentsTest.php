<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('get issue attachments returns 200 with attachment fields', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([
        'id' => '10001',
        'key' => 'PROJ-1',
        'fields' => [
            'attachment' => [
                [
                    'id' => '10001',
                    'filename' => 'screenshot.png',
                    'mimeType' => 'image/png',
                    'size' => 12345,
                    'content' => 'https://test.atlassian.net/secure/attachment/10001/screenshot.png',
                    'thumbnail' => null,
                ],
            ],
        ],
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/issues/PROJ-1/attachments", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'filename', 'mime_type', 'size', 'content']]]);
});

test('get issue attachments returns empty collection when none exist', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([
        'id' => '10001',
        'key' => 'PROJ-1',
        'fields' => [
            'attachment' => [],
        ],
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $response = $this->getJson("/api/{$account->id}/jira/issues/PROJ-1/attachments", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ]);

    $response->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.total_count', 0);
});
