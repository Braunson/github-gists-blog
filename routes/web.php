<?php

use App\Http\Controllers\BlogController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BlogController::class, 'index'])->name('home');
Route::get('{username}', [BlogController::class, 'show'])->name('blog.show');
Route::get('{username}/{gistId}', [BlogController::class, 'showGist'])->name('blog.gist');
