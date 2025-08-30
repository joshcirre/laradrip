<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('dashboard');
})->name('home');

Route::get('/welcome', function () {
    return view('welcome');
})->name('welcome');
