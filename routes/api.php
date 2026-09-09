<?php

use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Api\Admin\RoomController as AdminRoomController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\RoomController;
use Illuminate\Support\Facades\Route;

Route::get('/rooms', [RoomController::class, 'index']);
Route::get('/rooms/available', [RoomController::class, 'available']);

Route::post('/bookings', [BookingController::class, 'store']);
Route::get('/bookings/reference/{reference}', [BookingController::class, 'showByReference']);
Route::post('/bookings/reference/{reference}/simulate-payment', [BookingController::class, 'simulatePayment']);
Route::post('/bookings/reference/{reference}/cancel', [BookingController::class, 'cancel']);

Route::post('/admin/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::get('/bookings', [AdminBookingController::class, 'index']);
    Route::patch('/bookings/{id}/cancel', [AdminBookingController::class, 'cancel']);

    Route::post('/rooms', [AdminRoomController::class, 'store']);
    Route::put('/rooms/{id}', [AdminRoomController::class, 'update']);
    Route::delete('/rooms/{id}', [AdminRoomController::class, 'destroy']);
});
