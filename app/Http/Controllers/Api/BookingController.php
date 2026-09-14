<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    private const PENDING_EXPIRY_MINUTES = 30;

    private const REF_CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O/1/I - easier to read at check-in

    private function generateBookingReference(): string
    {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= self::REF_CHARS[random_int(0, strlen(self::REF_CHARS) - 1)];
        }

        return "MTX-{$code}";
    }

    private function nightsBetween(string $checkIn, string $checkOut): int
    {
        return (int) round((strtotime($checkOut) - strtotime($checkIn)) / 86400);
    }

    // Check-and-expire on demand for one reference, so a guest polling this
    // exact reference sees 'expired' the moment the window is up, without
    // waiting for the periodic sweep (see App\Console\Commands\ExpireStalePendingBookings).
    private function expireStalePending(string $bookingReference): void
    {
        Booking::where('booking_reference', $bookingReference)
            ->where('payment_status', 'pending')
            ->where('created_at', '<', now()->subMinutes(self::PENDING_EXPIRY_MINUTES))
            ->update(['status' => 'cancelled', 'payment_status' => 'expired']);
    }

    // POST /api/bookings - create one or more pending bookings (one per
    // room_id) under a single shared booking_reference. Nothing is
    // "confirmed" for the guest yet - status holds the room(s) so nobody
    // else can grab them while payment is pending, but payment_status
    // stays 'pending' until the simulated payment step flips it to 'paid'.
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email',
            'phone' => 'required|string',
            'room_ids' => 'required|array|min:1',
            'room_ids.*' => 'integer',
            'check_in_date' => 'required|date|after_or_equal:today',
            'check_out_date' => 'required|date',
            'special_requests' => 'nullable|string',
            'guests_count' => 'nullable|integer|min:1',
        ]);

        if (strtotime($data['check_in_date']) >= strtotime($data['check_out_date'])) {
            return response()->json([
                'success' => false,
                'message' => 'Check-out date must be after check-in date.',
            ], 400);
        }

        // Overlap check for every requested room - if any one of them is
        // already taken for these dates, reject the whole request rather
        // than partially booking.
        foreach ($data['room_ids'] as $roomId) {
            $conflict = Booking::where('room_id', $roomId)
                ->where('status', '!=', 'cancelled')
                ->where('check_in_date', '<', $data['check_out_date'])
                ->where('check_out_date', '>', $data['check_in_date'])
                ->exists();

            if ($conflict) {
                return response()->json([
                    'success' => false,
                    'message' => 'One of the selected rooms is no longer available for these dates.',
                ], 409);
            }
        }

        $result = DB::transaction(function () use ($data) {
            $guest = Guest::updateOrCreate(
                ['email' => $data['email']],
                ['name' => $data['name'], 'phone' => $data['phone']]
            );

            $bookingReference = $this->generateBookingReference();

            foreach ($data['room_ids'] as $roomId) {
                Booking::create([
                    'guest_id' => $guest->id,
                    'room_id' => $roomId,
                    'check_in_date' => $data['check_in_date'],
                    'check_out_date' => $data['check_out_date'],
                    'special_requests' => $data['special_requests'] ?? null,
                    'status' => 'confirmed',
                    'guests_count' => $data['guests_count'] ?? 1,
                    'booking_reference' => $bookingReference,
                    'payment_status' => 'pending',
                ]);
            }

            return $bookingReference;
        });

        $totalAmount = Room::whereIn('id', $data['room_ids'])->sum('price_per_night')
            * $this->nightsBetween($data['check_in_date'], $data['check_out_date']);
        $nights = $this->nightsBetween($data['check_in_date'], $data['check_out_date']);

        return response()->json([
            'success' => true,
            'data' => [
                'booking_reference' => $result,
                'total_amount' => $totalAmount,
                'nights' => $nights,
                'room_count' => count($data['room_ids']),
                'check_in_date' => $data['check_in_date'],
                'check_out_date' => $data['check_out_date'],
                'expires_at' => now()->addMinutes(self::PENDING_EXPIRY_MINUTES)->toIso8601String(),
            ],
        ], 201);
    }

    // Shared by showByReference (used internally by the payment modal,
    // which already knows the reference from just having created the
    // booking) and lookup (the public "Find My Booking" page, which
    // additionally requires the email to match before revealing anything).
    private function bookingResponseData(string $reference, $bookings): array
    {
        $first = $bookings->first();
        $nights = $this->nightsBetween($first->check_in_date, $first->check_out_date);
        $totalAmount = $bookings->sum(fn ($b) => $b->room->price_per_night * $nights);

        return [
            'booking_reference' => $reference,
            'status' => $first->status,
            'payment_status' => $first->payment_status,
            'guest_name' => $first->guest->name,
            'guest_email' => $first->guest->email,
            'check_in_date' => $first->check_in_date->format('Y-m-d'),
            'check_out_date' => $first->check_out_date->format('Y-m-d'),
            'expires_at' => $first->created_at->copy()->addMinutes(self::PENDING_EXPIRY_MINUTES)->toIso8601String(),
            'nights' => $nights,
            'total_amount' => $totalAmount,
            'rooms' => $bookings->map(fn ($b) => [
                'room_number' => $b->room->room_number,
                'room_type' => $b->room->room_type,
                'price_per_night' => $b->room->price_per_night,
            ]),
        ];
    }

    // GET /api/bookings/reference/:ref - look up a booking group by its
    // shared reference code. Used by the payment modal to poll for a
    // payment_status change.
    public function showByReference(string $reference): JsonResponse
    {
        $this->expireStalePending($reference);

        $bookings = Booking::with(['guest', 'room'])
            ->where('booking_reference', $reference)
            ->get();

        if ($bookings->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->bookingResponseData($reference, $bookings)]);
    }

    // GET /api/bookings/lookup?reference=X&email=Y - the public "Find My
    // Booking" self-service page. Requires the email to match the
    // reference's guest, unlike showByReference, so a guessed/leaked
    // reference alone isn't enough to see someone else's booking.
    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reference' => 'required|string',
            'email' => 'required|email',
        ]);

        $this->expireStalePending($data['reference']);

        $bookings = Booking::with(['guest', 'room'])
            ->where('booking_reference', $data['reference'])
            ->whereHas('guest', fn ($q) => $q->where('email', $data['email']))
            ->get();

        if ($bookings->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No booking found with that reference and email.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->bookingResponseData($data['reference'], $bookings),
        ]);
    }

    // POST /api/bookings/reference/:ref/simulate-payment - stands in for
    // the real payment gateway's webhook.
    public function simulatePayment(string $reference): JsonResponse
    {
        $updated = Booking::where('booking_reference', $reference)
            ->where('payment_status', 'pending')
            ->update(['payment_status' => 'paid']);

        if ($updated === 0) {
            return response()->json(['success' => false, 'message' => 'No pending booking found for that reference.'], 404);
        }

        Booking::sendConfirmationEmail($reference);

        return response()->json(['success' => true, 'message' => 'Payment marked as received.']);
    }

    // POST /api/bookings/reference/:ref/cancel - releases the held room(s)
    // if the guest backs out of a still-pending payment.
    public function cancel(string $reference): JsonResponse
    {
        $updated = Booking::where('booking_reference', $reference)
            ->where('payment_status', 'pending')
            ->update(['status' => 'cancelled', 'payment_status' => 'cancelled']);

        if ($updated === 0) {
            return response()->json(['success' => false, 'message' => 'No pending booking found for that reference.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Booking cancelled.']);
    }
}
