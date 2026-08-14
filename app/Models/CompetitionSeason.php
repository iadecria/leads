<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitionSeason extends Model
{
    protected $guarded = [];

    protected $casts = [
        'coverage' => 'array',
        'is_current' => 'boolean',
    ];

    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }
}
