<?php

use App\Http\Controllers\BridgeLabController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BridgeLabController::class, 'index']);
Route::get('/state', [BridgeLabController::class, 'state']);

Route::prefix('bridge')->group(function (): void {
    Route::post('/heartbeat', [BridgeLabController::class, 'heartbeat']);
    Route::post('/event', [BridgeLabController::class, 'event']);
});

Route::post('/bridge/launch', [BridgeLabController::class, 'launchBridge']);
Route::post('/bridge/close', [BridgeLabController::class, 'closeBridge']);

Route::prefix('host')->group(function (): void {
    Route::post('/reset', [BridgeLabController::class, 'reset']);
    Route::post('/open', [BridgeLabController::class, 'open']);
    Route::post('/correct', [BridgeLabController::class, 'correct']);
    Route::post('/wrong', [BridgeLabController::class, 'wrong']);
    Route::post('/teams', [BridgeLabController::class, 'saveTeams']);
    Route::post('/reset-scores', [BridgeLabController::class, 'resetScores']);
    Route::post('/buzz', [BridgeLabController::class, 'buzzChannel']);
});
