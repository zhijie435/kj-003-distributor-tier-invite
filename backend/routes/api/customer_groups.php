<?php

use App\Http\Controllers\CustomerGroupController;
use Illuminate\Support\Facades\Route;

Route::prefix('customer-groups')->group(function () {
    Route::get('/all', [CustomerGroupController::class, 'all'])->name('api.customer-groups.all');
    Route::get('/', [CustomerGroupController::class, 'index'])->name('api.customer-groups.index');
    Route::post('/', [CustomerGroupController::class, 'store'])->name('api.customer-groups.store');
    Route::get('/{customerGroup}', [CustomerGroupController::class, 'show'])->name('api.customer-groups.show');
    Route::put('/{customerGroup}', [CustomerGroupController::class, 'update'])->name('api.customer-groups.update');
    Route::delete('/{customerGroup}', [CustomerGroupController::class, 'destroy'])->name('api.customer-groups.destroy');
    Route::patch('/{customerGroup}/toggle-active', [CustomerGroupController::class, 'toggleActive'])->name('api.customer-groups.toggle-active');
    Route::post('/{customerGroup}/attach-users', [CustomerGroupController::class, 'attachUsers'])->name('api.customer-groups.attach-users');
    Route::post('/{customerGroup}/detach-users', [CustomerGroupController::class, 'detachUsers'])->name('api.customer-groups.detach-users');
});
