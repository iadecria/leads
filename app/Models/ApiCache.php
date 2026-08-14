<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiCache extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'response' => 'array',
        'expires_at' => 'datetime',
    ];
}
