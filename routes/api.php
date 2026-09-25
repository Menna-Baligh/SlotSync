<?php

use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Middleware\IdempotencyMiddleware;
use Illuminate\Support\Facades\Route;


Route::middleware([IdempotencyMiddleware::class])->group(function () {

    Route::prefix('reservations')->name('reservations.')->group(function () {
        Route::post('/', [ReservationController::class, 'store'])->name('store');
        Route::post('/{reservation}/confirm', [ReservationController::class, 'confirm'])->name('confirm');
        Route::post('/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('cancel');
        Route::put('/{reservation}', [ReservationController::class, 'update'])->name('update');
        Route::get('/{reservation}/history', [ReservationController::class, 'history'])->name('history');
    });

    Route::patch('/resources/{resource}/capacity', [ResourceController::class, 'updateCapacity'])->name('resources.updateCapacity');

});
