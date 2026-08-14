<?php

namespace App\Models;

use App\Enums\FasAuditStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FasAudit extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'status' => FasAuditStatus::class,
        'is_correct' => 'boolean',
        'validated_at' => 'datetime',
        'payload' => 'array',
    ];

    public function event()
    {
        return $this->belongsTo(FasEvent::class, 'fas_event_id');
    }

    public function rankingRun()
    {
        return $this->belongsTo(FasRankingRun::class, 'fas_ranking_run_id');
    }

    public function ranking()
    {
        return $this->belongsTo(FasRanking::class, 'fas_ranking_id');
    }

    public function fixture()
    {
        return $this->belongsTo(Fixture::class, 'fixture_id');
    }
}
