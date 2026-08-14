<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FasExecutionRun extends Model
{
    protected $fillable = [
        'execution_type',
        'analysis_date',
        'status',
        'started_at',
        'finished_at',
        'current_step',
        'fixtures_status',
        'datasets_status',
        'analysis_status',
        'ranking_status',
        'results_status',
        'audit_status',
        'summary',
        'errors',
    ];

    protected $casts = [
        'analysis_date' => 'date',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'summary' => 'array',
        'errors' => 'array',
    ];
}
