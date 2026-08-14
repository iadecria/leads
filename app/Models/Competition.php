<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Competition extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function seasons()
    {
        return $this->hasMany(CompetitionSeason::class);
    }

    public function fixtures()
    {
        return $this->hasMany(Fixture::class);
    }
}
