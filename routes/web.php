<?php

use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DocumentController::class, 'create'])->name('documents.create');

Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
