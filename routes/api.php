<?php

use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Api\Admin\RoomController as AdminRoomController;
use App\Http\Controllers\Api\Admin\RoomTypePhotoController as AdminRoomTypePhotoController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\RoomTypePhotoController;
use Illuminate\Support\Facades\Route;

Route::get('/rooms', [RoomController::class, 'index']);
Route::get('/rooms/available', [RoomController::class, 'available']);
Route::get('/room-types/photos', [RoomTypePhotoController::class, 'index']);

Route::post('/bookings', [BookingController::class, 'store']);
Route::get('/bookings/lookup', [BookingController::class, 'lookup']);
Route::get('/bookings/reference/{reference}', [BookingController::class, 'showByReference']);
Route::post('/bookings/reference/{reference}/simulate-payment', [BookingController::class, 'simulatePayment']);
Route::post('/bookings/reference/{reference}/cancel', [BookingController::class, 'cancel']);

Route::post('/chat', [ChatController::class, 'respond'])->middleware('throttle:15,1');

Route::post('/admin/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::get('/stats', [AdminBookingController::class, 'stats']);

    Route::get('/bookings', [AdminBookingController::class, 'index']);
    Route::post('/bookings', [AdminBookingController::class, 'store']);
    Route::put('/bookings/{id}', [AdminBookingController::class, 'update']);
    Route::patch('/bookings/{id}/cancel', [AdminBookingController::class, 'cancel']);

    Route::post('/rooms', [AdminRoomController::class, 'store']);
    Route::put('/rooms/{id}', [AdminRoomController::class, 'update']);
    Route::patch('/rooms/{id}/status', [AdminRoomController::class, 'updateStatus']);
    Route::delete('/rooms/{id}', [AdminRoomController::class, 'destroy']);

    Route::post('/room-types/{type}/photos', [AdminRoomTypePhotoController::class, 'store']);
    Route::delete('/room-type-photos/{id}', [AdminRoomTypePhotoController::class, 'destroy']);
});
