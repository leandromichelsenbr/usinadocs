<?php

use App\Http\Controllers\PublishedPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::get('/p/{slug}', [PublishedPageController::class, 'show'])->name('pages.show');
