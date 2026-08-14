<?php

namespace App\Jobs;

use App\Services\Fas\Engines\FasRankingEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateFasRankingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $date,
        public bool $force = false
    ) {}

    public function handle(FasRankingEngine $rankingEngine): void
    {
        $rankingEngine->generate($this->date, $this->force);
    }
}
