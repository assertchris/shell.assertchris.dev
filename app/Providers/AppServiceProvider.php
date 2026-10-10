<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Observers\AccountObserver;
use App\Observers\PersonalAccessTokenObserver;
use App\Observers\UserObserver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Slack\Provider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        PersonalAccessToken::observe(PersonalAccessTokenObserver::class);
        User::observe(UserObserver::class);
        Account::observe(AccountObserver::class);

        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('slack', Provider::class);
        });
    }
}
