<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomTypePhoto extends Model
{
    public $timestamps = false;

    protected $fillable = ['room_type', 'path'];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        return asset('storage/'.$this->path);
    }
}
