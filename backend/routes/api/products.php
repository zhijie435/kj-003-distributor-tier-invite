<?php

use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('products')->group(function () {
    Route::get('/all', [ProductController::class, 'all'])->name('api.products.all');
    Route::get('/', [ProductController::class, 'index'])->name('api.products.index');
    Route::post('/', [ProductController::class, 'store'])->name('api.products.store');
    Route::get('/{product}', [ProductController::class, 'show'])->name('api.products.show');
    Route::put('/{product}', [ProductController::class, 'update'])->name('api.products.update');
    Route::delete('/{product}', [ProductController::class, 'destroy'])->name('api.products.destroy');
    Route::patch('/{product}/toggle-active', [ProductController::class, 'toggleActive'])->name('api.products.toggle-active');
});
