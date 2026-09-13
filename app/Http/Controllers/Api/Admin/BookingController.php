<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    // POST /api/admin/bookings - front-desk walk-in booking. Unlike the
    // guest-facing POST /api/bookings, this skips the pending-payment /
    // 30-minute hold dance entirely: a walk-in guest is standing at the
    // desk paying right now, so the booking is confirmed immediately with
    // whatever payment_status the admin selects (defaults to paid).
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email',
            'phone' => 'required|string',
            'room_id' => 'required|integer|exists:rooms,id',
            'check_in_date' => 'required|date|after_or_equal:today',
            'check_out_date' => 'required|date',
            'special_requests' => 'nullable|string',
            'guests_count' => 'nullable|integer|min:1',
            'payment_status' => 'sometimes|in:paid,pending',
        ]);

        if (strtotime($data['check_in_date']) >= strtotime($data['check_out_date'])) {
            return response()->json([
                'success' => false,
                'message' => 'Check-out date must be after check-in date.',
            ], 400);
        }

        $conflict = Booking::where('room_id', $data['room_id'])
            ->where('status', '!=', 'cancelled')
            ->where('check_in_date', '<', $data['check_out_date'])
            ->where('check_out_date', '>', $data['check_in_date'])
            ->exists();

        if ($conflict) {
            return response()->json([
                'success' => false,
                'message' => 'This room is no longer available for those dates.',
            ], 409);
        }

        $booking = DB::transaction(function () use ($data) {
            $guest = Guest::firstOrCreate(
                ['email' => $data['email']],
                ['name' => $data['name'], 'phone' => $data['phone']]
            );

            return Booking::create([
                'guest_id' => $guest->id,
                'room_id' => $data['room_id'],
                'check_in_date' => $data['check_in_date'],
                'check_out_date' => $data['check_out_date'],
                'special_requests' => $data['special_requests'] ?? null,
                'status' => 'confirmed',
                'guests_count' => $data['guests_count'] ?? 1,
                'booking_reference' => Booking::generateReference(),
                'payment_status' => $data['payment_status'] ?? 'paid',
            ]);
        });

        if ($booking->payment_status === 'paid') {
            Booking::sendConfirmationEmail($booking->booking_reference);
        }

        $room = Room::find($data['room_id']);
        $nights = Booking::nightsBetween($data['check_in_date'], $data['check_out_date']);

        return response()->json([
            'success' => true,
            'data' => [
                'booking_reference' => $booking->booking_reference,
                'total_amount' => $room->price_per_night * $nights,
            ],
        ], 201);
    }

    // GET /api/admin/stats - quick front-desk snapshot for today.
    public function stats(): JsonResponse
    {
        $today = now()->toDateString();

        $activeToday = Booking::with('room')
            ->where('status', 'confirmed')
            ->where('check_in_date', '<=', $today)
            ->where('check_out_date', '>', $today)
            ->get();

        $revenueToday = $activeToday
            ->where('payment_status', 'paid')
            ->sum(fn (Booking $b) => $b->room->price_per_night);

        $totalRooms = Room::count();
        $occupiedRooms = $activeToday->pluck('room_id')->unique()->count();

        return response()->json([
            'success' => true,
            'data' => [
                'revenue_today' => $revenueToday,
                'occupied_rooms' => $occupiedRooms,
                'total_rooms' => $totalRooms,
                'occupancy_rate' => $totalRooms > 0 ? round($occupiedRooms / $totalRooms * 100) : 0,
            ],
        ]);
    }

    public function index(): JsonResponse
    {
        $bookings = Booking::with(['guest', 'room'])
            ->where('archived', false)
            ->orderByDesc('check_in_date')
            ->get()
            ->map(fn (Booking $b) => array_merge($b->toArray(), [
                'guest_name' => $b->guest->name,
                'guest_email' => $b->guest->email,
                'guest_phone' => $b->guest->phone,
                'room_number' => $b->room->room_number,
                'room_type' => $b->room->room_type,
                'price_per_night' => $b->room->price_per_night,
            ]));

        return response()->json(['success' => true, 'data' => $bookings]);
    }

    // PUT /api/admin/bookings/:id - reschedule (dates and/or room) without
    // having to cancel and recreate. Guest info is untouched; this is only
    // for the stay itself.
    public function update(Request $request, int $id): JsonResponse
    {
        $booking = Booking::find($id);

        if (! $booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        $data = $request->validate([
            'room_id' => 'required|integer|exists:rooms,id',
            'check_in_date' => 'required|date',
            'check_out_date' => 'required|date',
        ]);

        if (strtotime($data['check_in_date']) >= strtotime($data['check_out_date'])) {
            return response()->json([
                'success' => false,
                'message' => 'Check-out date must be after check-in date.',
            ], 400);
        }

        $conflict = Booking::where('room_id', $data['room_id'])
            ->where('id', '!=', $id)
            ->where('status', '!=', 'cancelled')
            ->where('check_in_date', '<', $data['check_out_date'])
            ->where('check_out_date', '>', $data['check_in_date'])
            ->exists();

        if ($conflict) {
            return response()->json([
                'success' => false,
                'message' => 'This room is not available for those dates.',
            ], 409);
        }

        $booking->update($data);

        return response()->json(['success' => true, 'message' => 'Booking updated.']);
    }

    // PATCH /api/admin/bookings/:id/confirm-payment - front desk confirms a
    // pending walk-in was actually paid (e.g. cash handed over after the
    // booking was created as pending). Sends the same confirmation email
    // the guest-facing payment flow sends.
    public function confirmPayment(int $id): JsonResponse
    {
        $booking = Booking::find($id);

        if (! $booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        if ($booking->payment_status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'This booking is not pending payment.',
            ], 400);
        }

        $booking->update(['payment_status' => 'paid']);
        Booking::sendConfirmationEmail($booking->booking_reference);

        return response()->json(['success' => true, 'message' => 'Payment confirmed.']);
    }

    // PATCH /api/admin/bookings/:id/archive - hides a booking from the
    // default admin list without deleting its history. Only meaningful for
    // cancelled bookings, but not restricted server-side in case an admin
    // wants to tidy up old completed stays too.
    public function archive(int $id): JsonResponse
    {
        $updated = Booking::where('id', $id)->update(['archived' => true]);

        if ($updated === 0) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Booking archived.']);
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
