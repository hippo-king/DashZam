<?php

use App\Http\Controllers\ApiSettingsController;
use App\Http\Controllers\PublicEventsController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/events', [PublicEventsController::class, 'index'])->name('events.index');
Route::get('/driver/timeline', [PublicEventsController::class, 'timeline'])->name('events.timeline');

// Public trigger to fetch API payloads (runs the scheduled fetch command).
use App\Http\Controllers\PublicFetchController;
Route::post('/events/fetch', [PublicFetchController::class, 'fetch'])->name('events.fetch');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/api-settings', [ApiSettingsController::class, 'edit'])->name('api-settings.edit');
    Route::patch('/api-settings', [ApiSettingsController::class, 'update'])->name('api-settings.update');
    Route::post('/api-settings/fetch', [ApiSettingsController::class, 'fetch'])->name('api-settings.fetch');
});

require __DIR__ . '/auth.php';
