<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FasAnalysis extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'analysis_snapshot' => 'array',
    ];

    public function run()
    {
        return $this->belongsTo(FasRun::class, 'fas_run_id');
    }

    public function fixture()
    {
        return $this->belongsTo(Fixture::class);
    }

    public function events()
    {
        return $this->hasMany(FasEvent::class);
    }
}
