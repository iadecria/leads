<?php

namespace App\Models;

use App\Enums\RankingType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FasRanking extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'ranking_type' => RankingType::class,
        'candidate_score' => 'decimal:2',
        'penalties' => 'array',
    ];

    public function run()
    {
        return $this->belongsTo(FasRankingRun::class, 'fas_ranking_run_id');
    }

    public function event()
    {
        return $this->belongsTo(FasEvent::class, 'fas_event_id');
    }
}
