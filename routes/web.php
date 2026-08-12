<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::get('/demonstracao', function () {
    return view('page', ['page' => config('content.demo')]);
})->name('demo');
