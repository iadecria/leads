<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fixture extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'fixture_date' => 'datetime',
    ];

    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }

    public function homeTeam()
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam()
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function analyses()
    {
        return $this->hasMany(FasAnalysis::class);
    }

    public function statistics()
    {
        return $this->hasMany(FixtureStatistic::class);
    }

    public function events()
    {
        return $this->hasMany(FixtureEvent::class);
    }
}
