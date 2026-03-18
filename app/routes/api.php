<?php

use App\Http\Controllers\RunController;
use Illuminate\Support\Facades\Route;

Route::post('/runs', [RunController::class, 'store']);
Route::get('/runs/{run}', [RunController::class, 'show']);
