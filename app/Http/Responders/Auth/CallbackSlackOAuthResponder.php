<?php

namespace App\Http\Responders\Auth;

use App\Filament\Resources\AccountResource;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class CallbackSlackOAuthResponder
{
    public function __invoke(Request $request): RedirectResponse
    {
        $accountId = session()->pull('slack_oauth_account_id');

        abort_if(is_null($accountId), 404);

        $account = Account::findOrFail($accountId);

        abort_unless($account->user_id === $request->user()->id, 403);

        $slackUser = Socialite::driver('slack')->user();
        $oauthResponse = $slackUser->accessTokenResponseBody;

        $account->update([
            'slack_access_token' => $oauthResponse['authed_user']['access_token'] ?? $oauthResponse['access_token'] ?? null,
            'slack_team_id' => $oauthResponse['team']['id'] ?? null,
            'slack_team_name' => $oauthResponse['team']['name'] ?? null,
            'slack_used' => true,
        ]);

        return redirect()
            ->to(AccountResource::getUrl('edit', ['record' => $account]))
            ->with('success', 'Slack connected.');
    }
}
