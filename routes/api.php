<?php

use App\Http\Controllers\Api\ReservationController;
use Illuminate\Support\Facades\Route;

Route::post('/reservations', [ReservationController::class, 'store']);
Route::post('/reservations/{reservation}/confirm', [ReservationController::class, 'confirm']);
Route::post('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel']);
Route::put('/reservations/{reservation}', [ReservationController::class, 'update']);
