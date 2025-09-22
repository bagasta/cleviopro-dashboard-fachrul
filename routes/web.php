<?php

use App\Http\Controllers\AgentConnectionController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\AgentKnowledgeController;
use App\Http\Controllers\AgentTrialController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SupportChatController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/agents/{agent}/edit', [AgentController::class, 'edit'])->name('agents.edit');
    Route::put('/agents/{agent}', [AgentController::class, 'update'])->name('agents.update');
    Route::delete('/agents/{agent}', [AgentController::class, 'destroy'])->name('agents.destroy');

    Route::post('/agents/{agent}/sessions', [AgentConnectionController::class, 'store'])->name('agents.sessions.store');
    Route::delete('/agents/{agent}/sessions', [AgentConnectionController::class, 'destroy'])->name('agents.sessions.destroy');
    Route::post('/agents/{agent}/sessions/reconnect', [AgentConnectionController::class, 'reconnect'])->name('agents.sessions.reconnect');

    Route::post('/agents/{agent}/knowledge', [AgentKnowledgeController::class, 'store'])->name('agents.knowledge.store');

    Route::get('/agents/{agent}/chat', [AgentTrialController::class, 'show'])->name('agents.chat');
    Route::post('/agents/{agent}/chat', [AgentTrialController::class, 'store'])->name('agents.chat.send');

    Route::post('/support-chat', [SupportChatController::class, 'store'])->name('support.chat.send');
});

require __DIR__.'/auth.php';
