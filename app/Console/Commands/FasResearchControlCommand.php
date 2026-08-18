<?php

namespace App\Console\Commands;

use App\DTOs\Dataset\MatchDataset;
use App\Models\Competition;
use App\Models\Fixture;
use App\Models\Team;
use App\Services\Dataset\MatchDatasetBuilder;
use App\Services\Fas\Engines\BttsEngine;
use App\Services\Fas\Engines\FirstHalfGoalEngine;
use App\Services\Fas\Engines\Over15Engine;
use App\Services\Fas\Engines\Over25Engine;
use App\Services\Fas\Engines\ResultEngine;
use App\Services\OpenRouter\OpenRouterResearchProvider;
use App\Services\Research\ResearchDatasetAdapter;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FasResearchControlCommand extends Command
{
    protected $signature = 'fas:research-control {date} {--home=Internacional} {--away=Remo} {--competition=Copa Controle} {--kickoff=}';

    protected $description = 'Run a controlled Research -> Dataset -> Engine validation without ranking.';

    public function handle(
        OpenRouterResearchProvider $provider,
        ResearchDatasetAdapter $adapter,
        MatchDatasetBuilder $builder,
        Over15Engine $over15,
        Over25Engine $over25,
        FirstHalfGoalEngine $firstHalf,
        BttsEngine $btts,
        ResultEngine $result
    ): int {
        $date = Carbon::parse($this->argument('date'));
        $homeName = (string) $this->option('home');
        $awayName = (string) $this->option('away');
        $kickoff = $this->option('kickoff') ? Carbon::parse((string) $this->option('kickoff')) : $date->copy()->addHours(20);

        $competition = Competition::firstOrCreate(
            ['name' => (string) $this->option('competition')],
            ['external_id' => crc32((string) $this->option('competition')), 'country' => 'Brasil', 'fas_enabled' => true]
        );

        $home = Team::firstOrCreate(['name' => $homeName], ['external_id' => crc32($homeName), 'country' => 'Brasil']);
        $away = Team::firstOrCreate(['name' => $awayName], ['external_id' => crc32($awayName), 'country' => 'Brasil']);

        $fixture = Fixture::firstOrCreate(
            ['home_team_id' => $home->id, 'away_team_id' => $away->id, 'fixture_date' => $kickoff],
            [
                'external_id' => crc32($homeName.'|'.$awayName.'|'.$kickoff->toIso8601String()),
                'competition_id' => $competition->id,
                'season' => (int) $date->year,
                'status' => 'NS',
                'fas_status' => 'ELIGIBLE',
            ]
        );

        $research = $provider->buscar_dados_partida($homeName, $awayName, $date->toDateString(), $kickoff->toIso8601String());
        $normalized = $adapter->normalize($research, $homeName, $awayName, $kickoff->toIso8601String());
        $datasetRecord = $builder->buildFromResearch($fixture, $research, true);
        $dataset = MatchDataset::fromArray($datasetRecord->payload);

        $this->line('=== CONTROLLED RESEARCH RUN ===');
        $this->line('Fixture: '.$homeName.' x '.$awayName);
        $this->line('Kickoff: '.$kickoff->toDateTimeString());
        $this->newLine();

        $this->line('Research debug:');
        $this->line(json_encode($research['_debug'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->newLine();

        $this->line('Raw home matches: '.count($research['home_recent_matches'] ?? []));
        $this->line('Normalized home matches: '.$normalized['home_recent_matches']->count());
        $this->line('Raw away matches: '.count($research['away_recent_matches'] ?? []));
        $this->line('Normalized away matches: '.$normalized['away_recent_matches']->count());
        $this->newLine();

        $this->line('Dataset quality: '.$dataset->dataQuality['score'].' '.$dataset->dataQuality['level']);
        $this->line('Home last5 sample: '.($dataset->homeStats->last5['goals']['over_15_rate']['sample_size'] ?? 0));
        $this->line('Home last10 sample: '.($dataset->homeStats->last10['goals']['over_15_rate']['sample_size'] ?? 0));
        $this->line('Away last5 sample: '.($dataset->awayStats->last5['goals']['over_15_rate']['sample_size'] ?? 0));
        $this->line('Away last10 sample: '.($dataset->awayStats->last10['goals']['over_15_rate']['sample_size'] ?? 0));
        $this->newLine();

        $engines = [
            'Over15' => $over15->calculate($dataset),
            'Over25' => $over25->calculate($dataset),
            'FirstHalfGoal' => $firstHalf->calculate($dataset),
            'BTTS' => $btts->calculate($dataset),
            'Result' => $result->calculate($dataset),
        ];

        foreach ($engines as $name => $predictions) {
            $this->line("{$name}:");
            foreach ($predictions as $prediction) {
                $eventType = is_object($prediction->event_type) && property_exists($prediction->event_type, 'value')
                    ? $prediction->event_type->value
                    : (string) $prediction->event_type;

                $this->line(' - '.$eventType.' | strength='.$prediction->sample_strength.' | raw='.(is_null($prediction->raw_probability) ? 'null' : $prediction->raw_probability).' | adj='.(is_null($prediction->adjusted_probability) ? 'null' : $prediction->adjusted_probability).' | dq='.$prediction->data_quality_score.' | fas='.$prediction->fas_score);
            }
        }

        return self::SUCCESS;
    }
}
