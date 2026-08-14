<?php

namespace App\Models;

use App\Enums\ConfidenceLevel;
use App\Enums\FasEventType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FasEvent extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'event_type' => FasEventType::class,
        'confidence' => ConfidenceLevel::class,
        'eligible_top3' => 'boolean',
        'eligible_top5' => 'boolean',
    ];

    public function analysis()
    {
        return $this->belongsTo(FasAnalysis::class, 'fas_analysis_id');
    }

    public function audit()
    {
        return $this->hasOne(FasAudit::class);
    }
}
