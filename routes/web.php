<?php

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\PublishedPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::get('/p/{slug}', [PublishedPageController::class, 'show'])->name('pages.show');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->name('login.store');
});

Route::post('/logout', [SessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'administrator'])->prefix('admin')->group(function (): void {
    Route::get('/', fn () => view('admin.dashboard'))->name('admin.dashboard');
});
