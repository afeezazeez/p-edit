<?php

use App\Http\Controllers\AdminCostReportController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\PixelforgeWebhookController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::redirect('dashboard', '/generations')->name('dashboard');
    Route::get('/generations', [GenerationController::class, 'index'])->name('generations.index');
    Route::get('/generations/create', [GenerationController::class, 'create'])->name('generations.create');
    Route::post('/generations/estimate', [GenerationController::class, 'estimate'])->name('generations.estimate');
    Route::post('/generations', [GenerationController::class, 'store'])->name('generations.store');
    Route::get('/admin/reports/costs', AdminCostReportController::class)
        ->middleware('can:view-cost-report')->name('admin.reports.costs');
});

Route::post('/webhooks/pixelforge', PixelforgeWebhookController::class)->name('webhooks.pixelforge');

require __DIR__.'/settings.php';
