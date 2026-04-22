<?php

namespace App\Http\Responders\Auth;

use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class RedirectSlackOAuthResponder
{
    public function __invoke(Request $request, Account $account): RedirectResponse
    {
        abort_unless($account->user_id === $request->user()->id, 403);

        session(['slack_oauth_account_id' => $account->id]);

        return Socialite::driver('slack')
            ->setUserScopes(['channels:read', 'channels:history', 'groups:read', 'groups:history', 'groups:write', 'mpim:read', 'mpim:history', 'mpim:write', 'chat:write', 'im:read', 'im:history', 'im:write', 'users:read'])
            ->redirect();
    }
}
