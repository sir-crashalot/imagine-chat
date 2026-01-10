<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\SSEController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// auth routes
Route::get('/auth/github', [AuthController::class, 'redirectToGitHub'])->name('auth.github');
Route::get('/auth/github/callback', [AuthController::class, 'handleGitHubCallback'])->name('auth.github.callback');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// protected routes - require authentication
Route::middleware(['auth'])->group(function () {
    // chat page
    Route::get('/chat', function () {
        return view('chat');
    })->name('chat');

    // message API routes (get history and send new messages)
    Route::get('/api/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::post('/api/messages', [MessageController::class, 'store'])->name('messages.store');

    // Server-Sent Events endpoint for real-time updates
    Route::get('/api/events', [SSEController::class, 'stream'])->name('sse.stream');
});

