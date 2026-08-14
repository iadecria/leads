<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiRequestLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'last_request_at' => 'datetime',
    ];
}
