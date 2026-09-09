<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guest extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'email', 'phone'];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
