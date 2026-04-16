<?php

use App\Filament\Resources\AccountResource;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Two\User as SocialiteUser;
use SocialiteProviders\Slack\Provider as SlackProvider;

uses(RefreshDatabase::class);

test('redirect route returns 302 to slack', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);

    $slackProvider = Mockery::mock(SlackProvider::class);
    $slackProvider->shouldReceive('scopes')->once()->andReturnSelf();
    $slackProvider->shouldReceive('redirect')->once()->andReturn(redirect('https://slack.com/oauth/v2/authorize?fake=1'));

    $socialite = Mockery::mock(SocialiteFactory::class);
    $socialite->shouldReceive('driver')->with('slack')->andReturn($slackProvider);

    $this->app->instance(SocialiteFactory::class, $socialite);

    $this->actingAs($user)
        ->get(route('slack.redirect', $account))
        ->assertRedirect();
});

test('redirect route stores account id in session', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);

    $slackProvider = Mockery::mock(SlackProvider::class);
    $slackProvider->shouldReceive('scopes')->once()->andReturnSelf();
    $slackProvider->shouldReceive('redirect')->once()->andReturn(redirect('https://slack.com/'));

    $socialite = Mockery::mock(SocialiteFactory::class);
    $socialite->shouldReceive('driver')->with('slack')->andReturn($slackProvider);

    $this->app->instance(SocialiteFactory::class, $socialite);

    $this->actingAs($user)
        ->get(route('slack.redirect', $account));

    $this->assertEquals($account->id, session('slack_oauth_account_id'));
});

test('redirect route returns 403 for wrong user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($user)
        ->get(route('slack.redirect', $account))
        ->assertForbidden();
});

test('callback stores tokens and redirects to filament edit page', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);

    session(['slack_oauth_account_id' => $account->id]);

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->token = 'xoxp-fake-access-token';
    $socialiteUser->shouldReceive('getRaw')->andReturn([
        'team_id' => 'T0123456789',
        'team' => ['name' => 'Acme Corp'],
    ]);

    $slackProvider = Mockery::mock(SlackProvider::class);
    $slackProvider->shouldReceive('user')->once()->andReturn($socialiteUser);

    $socialite = Mockery::mock(SocialiteFactory::class);
    $socialite->shouldReceive('driver')->with('slack')->andReturn($slackProvider);

    $this->app->instance(SocialiteFactory::class, $socialite);

    $this->actingAs($user)
        ->get(route('slack.callback'))
        ->assertRedirect(AccountResource::getUrl('edit', ['record' => $account]));

    $account->refresh();

    expect($account->slack_access_token)->toBe('xoxp-fake-access-token')
        ->and($account->slack_team_id)->toBe('T0123456789')
        ->and($account->slack_team_name)->toBe('Acme Corp')
        ->and($account->slack_used)->toBeTrue();
});

test('callback returns 404 when session id is missing', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('slack.callback'))
        ->assertNotFound();
});

test('callback returns 403 for wrong user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $otherUser->id]);

    session(['slack_oauth_account_id' => $account->id]);

    $this->actingAs($user)
        ->get(route('slack.callback'))
        ->assertForbidden();
});

test('disconnect action clears tokens and disables slack', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->create(['user_id' => $user->id]);

    expect($account->isSlackConnected())->toBeTrue();

    $account->update([
        'slack_access_token' => null,
        'slack_team_id' => null,
        'slack_team_name' => null,
        'slack_used' => false,
    ]);

    $account->refresh();

    expect($account->slack_access_token)->toBeNull()
        ->and($account->slack_team_id)->toBeNull()
        ->and($account->slack_team_name)->toBeNull()
        ->and($account->slack_used)->toBeFalse()
        ->and($account->isSlackConnected())->toBeFalse();
});
