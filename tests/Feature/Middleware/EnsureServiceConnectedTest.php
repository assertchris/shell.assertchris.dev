<?php

use App\Http\Middleware\EnsureServiceConnected;
use App\Models\Account;
use App\Models\User;
use App\Services\CalendarService;
use App\Services\MailboxService;
use App\Services\SlackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

test('service connected middleware passes for slack when connected', function () {
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

test('service connected middleware returns 404 for slack when not connected', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['slack_used' => false]);
    $token = $user->createToken('test');

    $this->getJson("/api/slack/{$account->id}/channels", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('service connected middleware passes for mail when connected', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['mail_used' => true]);
    $token = $user->createToken('test');

    $this->mock(MailboxService::class, function ($mock) {
        $mock->shouldReceive('connect')->once()->andThrow(new \RuntimeException('Connection refused'));
    });

    $this->getJson("/api/{$account->id}/mailbox", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertStatus(503); // Gets past service check, fails at mailbox connection
});

test('service connected middleware returns 404 for mail when not connected', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['mail_used' => false]);
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/mailbox", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('service connected middleware passes for calendar when connected', function () {
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

test('service connected middleware returns 404 for calendar when not connected', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['google_calendar_used' => false]);
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/calendar", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('service connected middleware throws InvalidArgumentException for unknown service', function () {
    $account = Account::factory()->create();

    $middleware = new EnsureServiceConnected;
    $request = Request::create('/test');
    $request->setRouteResolver(function () use ($account, $request) {
        $route = new \Illuminate\Routing\Route('GET', '/test', []);
        $route->bind($request);
        $route->setParameter('account', $account);

        return $route;
    });

    expect(fn () => $middleware->handle($request, fn () => response('ok'), 'unknown'))
        ->toThrow(\InvalidArgumentException::class, 'Unknown service: unknown');
});
