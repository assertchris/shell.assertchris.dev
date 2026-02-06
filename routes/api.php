<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/status', fn () => response()->json(['status' => 'ok']));
});
