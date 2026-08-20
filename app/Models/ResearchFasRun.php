<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResearchFasRun extends Model
{
    protected $fillable = [
        'analysis_date',
        'window',
        'status',
        'model',
        'prompt_version',
        'generated_at',
        'input_fixtures',
        'result',
        'debug',
        'errors',
    ];

    protected $casts = [
        'analysis_date' => 'date',
        'generated_at' => 'datetime',
        'input_fixtures' => 'array',
        'result' => 'array',
        'debug' => 'array',
        'errors' => 'array',
    ];
}
