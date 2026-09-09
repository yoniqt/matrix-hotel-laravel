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
}
