<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
