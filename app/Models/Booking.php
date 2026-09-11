<?php

namespace App\Models;

use App\Mail\BookingConfirmed;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class Booking extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'guest_id',
        'room_id',
        'check_in_date',
        'check_out_date',
        'special_requests',
        'status',
        'guests_count',
        'booking_reference',
        'payment_status',
    ];

    protected $casts = [
        'check_in_date' => 'date:Y-m-d',
        'check_out_date' => 'date:Y-m-d',
        'created_at' => 'datetime',
    ];

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    // No 0/O/1/I - easier to read at check-in.
    private const REF_CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function generateReference(): string
    {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= self::REF_CHARS[random_int(0, strlen(self::REF_CHARS) - 1)];
        }

        return "MTX-{$code}";
    }

    public static function nightsBetween(string $checkIn, string $checkOut): int
    {
        return (int) round((strtotime($checkOut) - strtotime($checkIn)) / 86400);
    }

    // Sends the confirmation email for every booking row sharing this
    // reference (a guest can book several rooms under one reference).
    // Failure to send (bad SMTP creds, provider outage) is only logged -
    // it never throws, since the booking/payment itself already
    // succeeded by the time this is called and shouldn't be undone or
    // surfaced as an error just because the email didn't go out.
    public static function sendConfirmationEmail(string $reference): void
    {
        $bookings = self::with(['guest', 'room'])
            ->where('booking_reference', $reference)
            ->get();

        if ($bookings->isEmpty()) {
            return;
        }

        $first = $bookings->first();
        $nights = self::nightsBetween($first->check_in_date, $first->check_out_date);
        $totalAmount = $bookings->sum(fn (self $b) => $b->room->price_per_night * $nights);

        try {
            Mail::to($first->guest->email)->send(new BookingConfirmed(
                guestName: $first->guest->name,
                bookingReference: $reference,
                rooms: $bookings->map(fn (self $b) => [
                    'room_type' => $b->room->room_type,
                    'room_number' => $b->room->room_number,
                ])->all(),
                checkInDate: $first->check_in_date->format('Y-m-d'),
                checkOutDate: $first->check_out_date->format('Y-m-d'),
                nights: $nights,
                totalAmount: (float) $totalAmount,
            ));
        } catch (\Throwable $e) {
            Log::error("Failed to send booking confirmation email for {$reference}: {$e->getMessage()}");
        }
    }
}
