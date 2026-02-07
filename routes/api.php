<?php

use App\Http\Responders\Api\ListDashboardMessagesResponder;
use App\Http\Responders\Api\ListFoldersResponder;
use App\Http\Responders\Api\ListMailboxesResponder;
use App\Http\Responders\Api\ListMessagesResponder;
use App\Http\Responders\Api\ShowMessageResponder;
use App\Http\Responders\Api\StoreDashboardMessageResponder;
use App\Http\Responders\Api\UpdateDashboardMessageResponder;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/status', fn () => response()->json(['status' => 'ok']));

    Route::get('/mailboxes', ListMailboxesResponder::class);
    Route::get('/mailboxes/{mailbox}/folders', ListFoldersResponder::class);
    Route::get('/mailboxes/{mailbox}/folders/{folder}/messages', ListMessagesResponder::class);
    Route::get('/mailboxes/{mailbox}/folders/{folder}/messages/{uid}', ShowMessageResponder::class);

    Route::get('/dashboard/messages', ListDashboardMessagesResponder::class);
    Route::post('/dashboard/messages', StoreDashboardMessageResponder::class);
    Route::patch('/dashboard/messages/{message}', UpdateDashboardMessageResponder::class);
});
