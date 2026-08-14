<?php

namespace App\Models;

use App\Enums\FasRunStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FasRun extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'analysis_date' => 'date',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'status' => FasRunStatus::class,
    ];

    public function analyses()
    {
        return $this->hasMany(FasAnalysis::class);
    }

    public function rankings()
    {
        return $this->hasMany(FasRanking::class);
    }
}
