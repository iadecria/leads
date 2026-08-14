<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FasRankingRun extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'analysis_date' => 'date',
        'config_snapshot' => 'array',
        'cutoff_at' => 'datetime',
        'generated_at' => 'datetime',
    ];

    public function rankings()
    {
        return $this->hasMany(FasRanking::class);
    }
}
