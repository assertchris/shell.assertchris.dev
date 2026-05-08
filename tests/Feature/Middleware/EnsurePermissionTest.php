<?php

use App\Http\Middleware\EnsurePermission;
use App\Models\Account;
use App\Models\User;
use App\Services\CalendarService;
use App\Services\SlackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

// google_calendar_read

test('permission middleware blocks calendar list when google_calendar_read is false', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withGoogleCalendar()->for($user)->create(['google_calendar_read' => false]);
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/calendar", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertForbidden()
        ->assertJson(['message' => 'Forbidden.']);
});

test('permission middleware allows calendar list when google_calendar_read is true', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withGoogleCalendar()->for($user)->create();
    $token = $user->createToken('test');

    $this->mock(CalendarService::class, function ($mock) {
        $mock->shouldReceive('listEntries')
            ->once()
            ->andReturn(['items' => [], 'nextPageToken' => null]);
    });

    $this->getJson("/api/{$account->id}/calendar", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk();
});

test('permission middleware blocks calendar show when google_calendar_read is false', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withGoogleCalendar()->for($user)->create(['google_calendar_read' => false]);
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/calendar/evt_123", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertForbidden()
        ->assertJson(['message' => 'Forbidden.']);
});

// slack_read

test('permission middleware blocks slack channels when slack_read is false', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->for($user)->create(['slack_read' => false]);
    $token = $user->createToken('test');

    $this->getJson("/api/slack/{$account->id}/channels", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertForbidden()
        ->assertJson(['message' => 'Forbidden.']);
});

test('permission middleware allows slack channels when slack_read is true', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->for($user)->create();
    $token = $user->createToken('test');

    $this->mock(SlackService::class, function ($mock) use ($account) {
        $mock->shouldReceive('listChannels')
            ->once()
            ->with(\Mockery::on(fn ($a) => $a->is($account)), null)
            ->andReturn([]);
    });

    $this->getJson("/api/slack/{$account->id}/channels", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk();
});

test('permission middleware blocks slack messages when slack_read is false', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->for($user)->create(['slack_read' => false]);
    $token = $user->createToken('test');

    $this->getJson("/api/slack/{$account->id}/channels/C123/messages", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertForbidden()
        ->assertJson(['message' => 'Forbidden.']);
});

test('permission middleware blocks slack users when slack_read is false', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->for($user)->create(['slack_read' => false]);
    $token = $user->createToken('test');

    $this->getJson("/api/slack/{$account->id}/users", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertForbidden()
        ->assertJson(['message' => 'Forbidden.']);
});

// slack_write

test('permission middleware blocks send slack message when slack_write is false', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->withSlackReadOnly()->for($user)->create();
    $token = $user->createToken('test');

    $this->postJson("/api/slack/{$account->id}/channels/C123/messages", ['text' => 'hello'], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertForbidden()
        ->assertJson(['message' => 'Forbidden.']);
});

test('permission middleware allows send slack message when slack_write is true', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->for($user)->create();
    $token = $user->createToken('test');

    $this->mock(SlackService::class, function ($mock) use ($account) {
        $mock->shouldReceive('sendMessage')
            ->once()
            ->with(\Mockery::on(fn ($a) => $a->is($account)), 'C123', 'hello')
            ->andReturn(['ts' => '1234567890.000001', 'ok' => true]);
    });

    $this->postJson("/api/slack/{$account->id}/channels/C123/messages", ['text' => 'hello'], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk();
});

test('permission middleware blocks open slack conversation when slack_write is false', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->withSlackReadOnly()->for($user)->create();
    $token = $user->createToken('test');

    $this->postJson("/api/slack/{$account->id}/conversations/open", ['user_id' => 'U123'], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertForbidden()
        ->assertJson(['message' => 'Forbidden.']);
});

// middleware ordering: EnsureServiceConnected before EnsurePermission

test('disconnected service returns 404 before permission check is reached', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create([
        'slack_used' => false,
        'slack_access_token' => null,
        'slack_read' => true,
    ]);
    $token = $user->createToken('test');

    $this->getJson("/api/slack/{$account->id}/channels", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('disconnected calendar returns 404 before permission check is reached', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create([
        'google_calendar_used' => false,
        'google_calendar_access_token' => null,
        'google_calendar_read' => true,
    ]);
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/calendar", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

// misconfiguration

test('permission middleware throws InvalidArgumentException for unknown permission', function () {
    $account = Account::factory()->create();

    $middleware = new EnsurePermission;
    $request = Request::create('/test');
    $request->setRouteResolver(function () use ($account, $request) {
        $route = new \Illuminate\Routing\Route('GET', '/test', []);
        $route->bind($request);
        $route->setParameter('account', $account);

        return $route;
    });

    expect(fn () => $middleware->handle($request, fn () => response('ok'), 'nonexistent_permission'))
        ->toThrow(\InvalidArgumentException::class, 'Unknown permission: nonexistent_permission');
});
