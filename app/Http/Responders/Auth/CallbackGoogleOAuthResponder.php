<?php

namespace App\Http\Responders\Auth;

use App\Filament\Resources\AccountResource;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class CallbackGoogleOAuthResponder
{
    public function __invoke(Request $request): RedirectResponse
    {
        $accountId = session()->pull('google_oauth_account_id');
        $account = Account::findOrFail($accountId);

        abort_unless($account->user_id === $request->user()->id, 403);

        $socialiteUser = Socialite::driver('google')->user();

        $account->update([
            'google_calendar_access_token' => $socialiteUser->token,
            'google_calendar_refresh_token' => $socialiteUser->refreshToken,
            'google_calendar_token_expires_at' => now()->addSeconds($socialiteUser->expiresIn),
            'google_calendar_used' => true,
        ]);

        return redirect()
            ->to(AccountResource::getUrl('edit', ['record' => $account]))
            ->with('success', 'Google Calendar connected.');
    }
}
