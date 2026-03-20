<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ChatController::class, 'index']);
Route::post('/runs', [ChatController::class, 'store']);
Route::get('/runs/{run}', [ChatController::class, 'show']);
Route::get('/runs/{run}/stream', [ChatController::class, 'stream']);
