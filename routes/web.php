<?php

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Admin\PageController;
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
    Route::get('/pages', [PageController::class, 'index'])->name('admin.pages.index');
    Route::get('/pages/create', [PageController::class, 'create'])->name('admin.pages.create');
    Route::post('/pages', [PageController::class, 'store'])->name('admin.pages.store');
    Route::get('/pages/{page}/revisions/{revision}/edit', [PageController::class, 'edit'])->name('admin.pages.edit');
    Route::put('/pages/{page}/revisions/{revision}', [PageController::class, 'update'])->name('admin.pages.update');
    Route::post('/pages/{page}/revisions/{revision}/publish', [PageController::class, 'publish'])->name('admin.pages.publish');
    Route::post('/pages/{page}/revisions', [PageController::class, 'newRevision'])->name('admin.pages.revisions.store');
});
