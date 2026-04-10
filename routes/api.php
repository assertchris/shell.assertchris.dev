<?php

use App\Http\Responders\Api\ListAccountsResponder;
use App\Http\Responders\Api\ListFoldersResponder;
use App\Http\Responders\Api\ListMessagesResponder;
use App\Http\Responders\Api\ShowMessageResponder;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/status', fn () => response()->json(['status' => 'ok']));

    Route::get('/accounts', ListAccountsResponder::class);

    Route::get('/{account}/mailbox', ListFoldersResponder::class);
    Route::get('/{account}/mailbox/{folder}/messages', ListMessagesResponder::class);
    Route::get('/{account}/mailbox/{folder}/messages/{uid}', ShowMessageResponder::class);
});
