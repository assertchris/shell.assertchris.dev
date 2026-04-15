<?php

use App\Http\Responders\Auth\CallbackGoogleOAuthResponder;
use App\Http\Responders\Auth\RedirectGoogleOAuthResponder;
use App\Http\Responders\Docs\ListDocPagesResponder;
use App\Http\Responders\Docs\ShowDocPageResponder;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['status' => 'ok']);
});

Route::get('/docs', ListDocPagesResponder::class)->name('docs.index');
Route::get('/docs/{slug}', ShowDocPageResponder::class)->name('docs.show');

Route::middleware('auth')->group(function () {
    Route::get('/auth/google/{account}/redirect', RedirectGoogleOAuthResponder::class)->name('google.redirect');
    Route::get('/auth/google/callback', CallbackGoogleOAuthResponder::class)->name('google.callback');
});
