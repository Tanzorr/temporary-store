<?php

use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DocumentController::class, 'create'])->name('documents.create');

Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
Route::get('/documents/{uuid}/download', [DocumentController::class, 'download'])->name('documents.download');
Route::delete('/documents/{uuid}', [DocumentController::class, 'destroy'])->name('documents.destroy');
