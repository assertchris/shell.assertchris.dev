<?php

use App\Http\Responders\Api\CreateCalendarEntryResponder;
use App\Http\Responders\Api\ListAccountsResponder;
use App\Http\Responders\Api\ListAttachmentsResponder;
use App\Http\Responders\Api\ListCalendarEntriesResponder;
use App\Http\Responders\Api\ListFoldersResponder;
use App\Http\Responders\Api\ListMessagesResponder;
use App\Http\Responders\Api\ListSlackChannelsResponder;
use App\Http\Responders\Api\ListSlackMessagesResponder;
use App\Http\Responders\Api\ListSlackUsersResponder;
use App\Http\Responders\Api\OpenSlackConversationResponder;
use App\Http\Responders\Api\SendSlackMessageResponder;
use App\Http\Responders\Api\ShowCalendarEntryResponder;
use App\Http\Responders\Api\ShowMessageResponder;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'account.owner'])->group(function () {
    Route::get('/status', fn () => response()->json(['status' => 'ok']));

    Route::get('/accounts', ListAccountsResponder::class);

    Route::middleware('service.connected:mail')->group(function () {
        Route::get('/{account}/mailbox', ListFoldersResponder::class);
        Route::get('/{account}/mailbox/{folder}/messages', ListMessagesResponder::class);
        Route::get('/{account}/mailbox/{folder}/messages/{uid}', ShowMessageResponder::class);
        Route::get('/{account}/mailbox/{folder}/messages/{uid}/attachments', ListAttachmentsResponder::class);
    });

    Route::middleware('service.connected:calendar')->group(function () {
        Route::get('/{account}/calendar', ListCalendarEntriesResponder::class)
            ->middleware('permission:google_calendar_read');
        Route::get('/{account}/calendar/{eventId}', ShowCalendarEntryResponder::class)
            ->middleware('permission:google_calendar_read');
        Route::post('/{account}/calendar', CreateCalendarEntryResponder::class)
            ->middleware('permission:google_calendar_write');
    });

    Route::middleware('service.connected:slack')->group(function () {
        Route::get('/slack/{account}/channels', ListSlackChannelsResponder::class)
            ->middleware('permission:slack_read');
        Route::get('/slack/{account}/channels/{channel}/messages', ListSlackMessagesResponder::class)
            ->middleware('permission:slack_read');
        Route::post('/slack/{account}/channels/{channel}/messages', SendSlackMessageResponder::class)
            ->middleware('permission:slack_write');
        Route::get('/slack/{account}/users', ListSlackUsersResponder::class)
            ->middleware('permission:slack_read');
        Route::post('/slack/{account}/conversations/open', OpenSlackConversationResponder::class)
            ->middleware('permission:slack_write');
    });
});
