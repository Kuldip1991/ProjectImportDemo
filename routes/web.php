<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\DiscountController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/import', [ImportController::class, 'index'])->name('bulk-import');
Route::post('/import/csv', [ImportController::class, 'import'])->name('import');
Route::post('/upload/chunk', [ImportController::class, 'receiveChunk']);
Route::get('/upload/status/{uploadId}', [ImportController::class, 'status']);
Route::post('/upload/complete', [ImportController::class, 'complete']);
Route::get('/download/sample-products', [ImportController::class, 'downloadSampleExcel'])->name('download.sample.products');
Route::view('/discount-form', 'discount.apply-discount')->name('discount-form');
Route::post('/apply-discount', [DiscountController::class, 'apply']);



require __DIR__.'/auth.php';
