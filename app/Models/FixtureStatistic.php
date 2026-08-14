<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FixtureStatistic extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'raw_payload' => 'array',
    ];

    public function fixture()
    {
        return $this->belongsTo(Fixture::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
