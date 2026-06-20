<?php

use App\Http\Controllers\ProductCustomerGroupPriceController;
use Illuminate\Support\Facades\Route;

Route::prefix('product-customer-group-prices')->group(function () {
    Route::get('/', [ProductCustomerGroupPriceController::class, 'index'])->name('api.product-customer-group-prices.index');
    Route::post('/', [ProductCustomerGroupPriceController::class, 'store'])->name('api.product-customer-group-prices.store');
    Route::get('/{productCustomerGroupPrice}', [ProductCustomerGroupPriceController::class, 'show'])->name('api.product-customer-group-prices.show');
    Route::put('/{productCustomerGroupPrice}', [ProductCustomerGroupPriceController::class, 'update'])->name('api.product-customer-group-prices.update');
    Route::delete('/{productCustomerGroupPrice}', [ProductCustomerGroupPriceController::class, 'destroy'])->name('api.product-customer-group-prices.destroy');
});

Route::prefix('products/{product}/prices')->group(function () {
    Route::get('/', [ProductCustomerGroupPriceController::class, 'getByProduct'])->name('api.products.prices.index');
    Route::post('/batch', [ProductCustomerGroupPriceController::class, 'batchUpdateByProduct'])->name('api.products.prices.batch');
});
