<?php

use App\Http\Controllers\InvitationCodeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('invitation-codes')->group(function () {
    Route::get('/', [InvitationCodeController::class, 'index'])->name('api.invitation-codes.index');
    Route::post('/', [InvitationCodeController::class, 'store'])->name('api.invitation-codes.store');
    Route::post('/batch-generate', [InvitationCodeController::class, 'batchGenerate'])->name('api.invitation-codes.batch-generate');
    Route::post('/redeem', [InvitationCodeController::class, 'redeem'])->name('api.invitation-codes.redeem');
    Route::post('/validate', [InvitationCodeController::class, 'validate'])->name('api.invitation-codes.validate');
    Route::get('/{invitationCode}', [InvitationCodeController::class, 'show'])->name('api.invitation-codes.show');
    Route::put('/{invitationCode}', [InvitationCodeController::class, 'update'])->name('api.invitation-codes.update');
    Route::delete('/{invitationCode}', [InvitationCodeController::class, 'destroy'])->name('api.invitation-codes.destroy');
    Route::patch('/{invitationCode}/toggle-active', [InvitationCodeController::class, 'toggleActive'])->name('api.invitation-codes.toggle-active');
    Route::post('/{id}/restore', [InvitationCodeController::class, 'restore'])->name('api.invitation-codes.restore');
});

Route::middleware(['auth:sanctum'])->prefix('customer-groups/{customerGroup}/invitation-codes')->group(function () {
    Route::get('/', [InvitationCodeController::class, 'getByCustomerGroup'])->name('api.customer-groups.invitation-codes');
});
