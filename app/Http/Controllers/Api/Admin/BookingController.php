<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;

class BookingController extends Controller
{
    public function index(): JsonResponse
    {
        $bookings = Booking::with(['guest', 'room'])
            ->orderByDesc('check_in_date')
            ->get()
            ->map(fn (Booking $b) => array_merge($b->toArray(), [
                'guest_name' => $b->guest->name,
                'guest_email' => $b->guest->email,
                'guest_phone' => $b->guest->phone,
                'room_number' => $b->room->room_number,
                'room_type' => $b->room->room_type,
            ]));

        return response()->json(['success' => true, 'data' => $bookings]);
    }

    // PATCH /api/admin/bookings/:id/cancel - admin override, works
    // regardless of payment_status (unlike the guest-facing cancel route,
    // which only works while a booking is still pending payment).
    public function cancel(int $id): JsonResponse
    {
        $updated = Booking::where('id', $id)->update(['status' => 'cancelled']);

        if ($updated === 0) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Booking cancelled.']);
    }
}
