<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchDatasetRecord extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'generated_at' => 'datetime',
        'cutoff_at' => 'datetime',
        'payload' => 'array',
    ];

    public function fixture()
    {
        return $this->belongsTo(Fixture::class);
    }
}
