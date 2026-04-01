<?php

use App\Http\Controllers\BridgeLabController;
use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BridgeLabController::class, 'index']);
Route::get('/state', [BridgeLabController::class, 'state']);

Route::prefix('bridge')->group(function (): void {
    Route::post('/heartbeat', [BridgeLabController::class, 'heartbeat']);
    Route::post('/event', [BridgeLabController::class, 'event']);
});

Route::post('/bridge/launch', [BridgeLabController::class, 'launchBridge']);
Route::post('/bridge/close', [BridgeLabController::class, 'closeBridge']);

// ── Games ────────────────────────────────────────────────────────────────────
Route::get('/games', [GameController::class, 'index']);
Route::post('/games', [GameController::class, 'store']);
Route::get('/games/{game}', [GameController::class, 'show']);
Route::put('/games/{game}', [GameController::class, 'updateGame']);
Route::delete('/games/{game}', [GameController::class, 'destroyGame']);

Route::get('/games/{game}/questions', [GameController::class, 'questionsJson']);
Route::post('/games/{game}/questions', [GameController::class, 'storeQuestion']);
Route::post('/games/{game}/questions/reorder', [GameController::class, 'reorderQuestions']);
Route::post('/questions/{question}', [GameController::class, 'updateQuestion']);
Route::delete('/questions/{question}', [GameController::class, 'destroyQuestion']);

Route::post('/questions/{question}/answers', [GameController::class, 'storeAnswer']);
Route::put('/answers/{answer}', [GameController::class, 'updateAnswer']);
Route::delete('/answers/{answer}', [GameController::class, 'destroyAnswer']);

Route::patch('/questions/{question}/move', [GameController::class, 'moveQuestion']);

Route::get('/project', [GameController::class, 'projectView']);
Route::get('/project/active', [GameController::class, 'activeQuestion']);
Route::post('/project/active', [GameController::class, 'setActiveQuestion']);
Route::post('/project/answer', [GameController::class, 'setSelectedAnswer']);
Route::post('/project/color', [GameController::class, 'setWinnerColor']);

// ── Host ─────────────────────────────────────────────────────────────────────
Route::prefix('host')->group(function (): void {
    Route::post('/reset', [BridgeLabController::class, 'reset']);
    Route::post('/open', [BridgeLabController::class, 'open']);
    Route::post('/correct', [BridgeLabController::class, 'correct']);
    Route::post('/wrong', [BridgeLabController::class, 'wrong']);
    Route::post('/teams', [BridgeLabController::class, 'saveTeams']);
    Route::post('/reset-scores', [BridgeLabController::class, 'resetScores']);
    Route::post('/buzz', [BridgeLabController::class, 'buzzChannel']);
    Route::post('/answer-light', [BridgeLabController::class, 'answerLight']);
});
