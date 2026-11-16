<?php

use App\Http\Controllers\GenerationController;
use App\Http\Controllers\PixelforgeWebhookController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::get('/generations', [GenerationController::class, 'index'])->name('generations.index');
    Route::post('/generations/estimate', [GenerationController::class, 'estimate'])->name('generations.estimate');
    Route::post('/generations', [GenerationController::class, 'store'])->name('generations.store');
});

Route::post('/webhooks/pixelforge', PixelforgeWebhookController::class)->name('webhooks.pixelforge');

require __DIR__.'/settings.php';
