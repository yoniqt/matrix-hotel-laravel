<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index(): JsonResponse
    {
        $rooms = Room::orderBy('room_number')->get();

        return response()->json(['success' => true, 'data' => $rooms]);
    }

    // GET /api/rooms/available?check_in=YYYY-MM-DD&check_out=YYYY-MM-DD
    // Returns only rooms that do NOT have a conflicting booking for the
    // given dates - mirrors the overlap condition used when a booking is
    // created, just filtering a list instead of validating one new booking.
    public function available(Request $request): JsonResponse
    {
        $checkIn = $request->query('check_in');
        $checkOut = $request->query('check_out');

        if (! $checkIn || ! $checkOut) {
            return response()->json([
                'success' => false,
                'message' => 'check_in and check_out dates are required.',
            ], 400);
        }

        if (strtotime($checkIn) >= strtotime($checkOut)) {
            return response()->json([
                'success' => false,
                'message' => 'Check-out date must be after check-in date.',
            ], 400);
        }

        $bookedRoomIds = Booking::where('status', '!=', 'cancelled')
            ->where('check_in_date', '<', $checkOut)
            ->where('check_out_date', '>', $checkIn)
            ->pluck('room_id');

        $rooms = Room::whereNotIn('id', $bookedRoomIds)
            ->orderBy('room_number')
            ->get();

        return response()->json(['success' => true, 'data' => $rooms]);
    }
}
