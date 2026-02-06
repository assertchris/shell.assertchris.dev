<?php

use App\Http\Responders\Docs\ListDocPagesResponder;
use App\Http\Responders\Docs\ShowDocPageResponder;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['status' => 'ok']);
});

Route::get('/docs', ListDocPagesResponder::class)->name('docs.index');
Route::get('/docs/{slug}', ShowDocPageResponder::class)->name('docs.show');
