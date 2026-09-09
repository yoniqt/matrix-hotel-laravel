<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Model
{
    use HasApiTokens;

    public $timestamps = false;

    protected $fillable = ['username', 'password_hash'];

    protected $hidden = ['password_hash'];
}
