<?php

return [
    /*
    |--------------------------------------------------------------------------
    | FAS Match Dataset Version
    |--------------------------------------------------------------------------
    |
    | The current version of the Match Dataset builder logic.
    | Changing this allows tracking how datasets were generated historically.
    |
    */
    'dataset_version' => '1.0.0',

    /*
    |--------------------------------------------------------------------------
    | Data Quality Weights
    |--------------------------------------------------------------------------
    |
    | Defines the maximum score (weight) for each component of the dataset.
    | The total sum should ideally be 100.
    |
    */
    'data_quality_weights' => [
        'historical_fixtures' => 30,
        'fixture_statistics' => 20,
        'home_away_sample' => 15,
        'standings' => 10,
        'head_to_head' => 5,
        'injuries' => 5,
        'lineups' => 5,
        'events' => 5,
        'coverage_completeness' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | FAS Engine Version
    |--------------------------------------------------------------------------
    |
    | The current version of the Engine calculation logic.
    |
    */
    'engine_version' => '1.0.0',

    /*
    |--------------------------------------------------------------------------
    | Global Priors
    |--------------------------------------------------------------------------
    |
    | Used by the Bayesian Regression to penalize/smooth small samples.
    | These values act as the baseline probability of an event happening.
    | They should be updated as we gather more data.
    |
    */
    'priors' => [
        'over_1_5' => 0.72,
        'over_2_5' => 0.48,
        'first_half_goal' => 0.65,
        'btts' => 0.52,
        'home_win' => 0.45,
        'draw' => 0.25,
        'away_win' => 0.30,
        'corners' => 0.50, // Base prior for corner lines
        'cards' => 0.50, // Base prior for cards
    ],

    /*
    |--------------------------------------------------------------------------
    | Prior Strengths
    |--------------------------------------------------------------------------
    |
    | Determines how strongly the prior pulls the observed rate.
    | Higher = Requires larger sample to override the prior.
    |
    */
    'prior_strengths' => [
        'over_1_5' => 3.0,
        'over_2_5' => 4.0,
        'first_half_goal' => 3.0,
        'btts' => 4.0,
        'result' => 5.0,
        'corners' => 5.0,
        'cards' => 5.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Minimum Samples
    |--------------------------------------------------------------------------
    |
    | The absolute minimum sample size required to generate a prediction.
    | If the data available provides less sample than this, it returns INSUFFICIENT_DATA.
    |
    */
    'minimum_samples' => [
        'over_1_5' => 5,
        'over_2_5' => 5,
        'first_half_goal' => 5,
        'btts' => 5,
        'result' => 8,
        'corners' => 10,
        'cards' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Selection & Ranking Engine Settings
    |--------------------------------------------------------------------------
    */
    'ranking' => [
        'version' => '1.0.0',

        'official_event_types' => [
            'OVER_1_5',
            'OVER_2_5',
            'FIRST_HALF_GOAL',
            'BTTS',
            'HOME_WIN',
            'AWAY_WIN',
        ],

        'experimental_event_types' => [
            'OVER_CORNERS',
            'OVER_CARDS',
        ],

        'maximum_events_per_fixture' => 1,

        'penalties' => [
            'missing_feature' => 5, // Confidence penalty per missing feature
            'low_competition_tier' => 10, // Candidate score penalty
            'medium_sample' => 5, // Candidate score penalty
            'low_sample' => 15, // Candidate score penalty
            'experimental_engine' => 10, // Candidate score penalty
            'cards_without_referee' => 20, // Candidate score penalty
        ],

        'requirements' => [
            'cards_requires_referee_for_top3' => true,
        ],

        'top3' => [
            'minimum_probability' => 0.65,
            'minimum_fas_score' => 70,
            'minimum_data_quality' => 70,
            'allowed_confidence' => ['HIGH', 'VERY_HIGH'],
        ],

        'top5' => [
            'minimum_probability' => 0.60,
            'minimum_fas_score' => 60,
            'minimum_data_quality' => 60,
            'allowed_confidence' => ['MEDIUM', 'HIGH', 'VERY_HIGH'],
        ],

        'watchlist' => [
            'minimum_probability' => 0.50,
            'minimum_fas_score' => 50,
        ],

        'candidate_score_weights' => [
            'adjusted_probability' => 40,
            'fas_score' => 30,
            'data_quality' => 15,
            'agreement' => 15,
            // confidence and sample_strength are factored into FAS Score directly.
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | FAS Audit & Confere
    |--------------------------------------------------------------------------
    */
    'audit' => [
        'version' => '1.0.0',
        'red_card_weight' => 1, // Defines how many cards a red card counts for in OVER_CARDS validations
    ],

    /*
    |--------------------------------------------------------------------------
    | FAS Performance & Analytics
    |--------------------------------------------------------------------------
    |
    | Configuration for historical performance metrics and dashboard.
    |
    */
    'performance' => [
        'minimum_sample' => 10,

        'probability_buckets' => [
            '50-54' => [0.50, 0.5499],
            '55-59' => [0.55, 0.5999],
            '60-64' => [0.60, 0.6499],
            '65-69' => [0.65, 0.6999],
            '70-74' => [0.70, 0.7499],
            '75-79' => [0.75, 0.7999],
            '80-84' => [0.80, 0.8499],
            '85-89' => [0.85, 0.8999],
            '90+' => [0.90, 1.00],
        ],

        'fas_score_buckets' => [
            '0-49' => [0, 49.99],
            '50-59' => [50, 59.99],
            '60-69' => [60, 69.99],
            '70-79' => [70, 79.99],
            '80-89' => [80, 89.99],
            '90-100' => [90, 100],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | FAS Descoberta de Jogos do Dia
    |--------------------------------------------------------------------------
    |
    | Etapa leve de agenda + relevância (sem research profundo).
    | Usa OpenRouter Research/Web para encontrar jogos da data,
    | filtra por competição e divide em Janela 1 (< 17:00) e Janela 2 (>= 17:00).
    |
    */
    'discovery' => [
        'version' => '1.0.0',
        'timezone' => env('FAS_DISCOVERY_TIMEZONE', 'America/Sao_Paulo'),
        'cutoff_time' => '17:00',
        'max_per_window' => (int) env('FAS_DISCOVERY_MAX_PER_WINDOW', 10),

        // Modo de análise: RESEARCH_AGENT | DETERMINISTIC_ENGINE
        'analysis_mode' => env('FAS_ANALYSIS_MODE', 'RESEARCH_AGENT'),

        'research_agent' => [
            'model' => env('FAS_RESEARCH_AGENT_MODEL', env('OPENROUTER_RESEARCH_MODEL', 'google/gemini-2.5-flash:floor')),
            'prompt_version' => '1.0.0',
            'max_analyzed_fixtures' => 20,
            'probability_source' => 'RESEARCH_AGENT',
            'calibration_status' => 'UNCALIBRATED',
            'timeout' => 90,
        ],

        // Estimativa de custo por token (USD) — Gemini Flash 2.5
        'cost_per_prompt_token' => 0.00000015,
        'cost_per_completion_token' => 0.00000060,

        // Níveis de competição
        'tiers' => [
            5 => [
                'champions league',
                'premier league',
                'la liga',
                'serie a',
                'bundesliga',
                'brasileirão',
                'brasileirao',
            ],
            4 => [
                'ligue 1',
                'primeira liga',
                'liga portugal',
                'liga nos',
                'eredivisie',
                'mls',
                'liga mx',
                'championship',
                'europa league',
            ],
            3 => [
                'copa do brasil',
                'fa cup',
                'copa del rey',
                'dfb pokal',
                'coppa italia',
                'coupe de france',
                'taça de portugal',
                'scottish premiership',
                'jupiler pro league',
                'belgian pro league',
                'super lig',
                'süper lig',
                'superliga',
                'conference league',
                'sul-americana',
                'sudamericana',
                'ekstraklasa',
                'allsvenskan',
                'eliteserien',
                'austrian bundesliga',
                'swiss super league',
            ],
        ],

        // Blocos de descoberta multi-busca (agenda leve, sem research profundo)
        'blocks' => [
            'europa_elite' => [
                'label' => 'Europa Elite',
                'competitions' => 'UEFA Champions League (incl. qualifying), UEFA Europa League (incl. qualifying), UEFA Conference League (incl. qualifying), Premier League, La Liga, Serie A, Bundesliga, Ligue 1, Liga Portugal, Eredivisie, Championship',
            ],
            'brasil' => [
                'label' => 'Brasil',
                'competitions' => 'Brasileirão Série A, Copa do Brasil, Copa Sudamericana (Sul-Americana)',
            ],
            'americas' => [
                'label' => 'Américas',
                'competitions' => 'MLS, Liga MX, Argentina Primera División, and other FAS-eligible national leagues/cups',
            ],
        ],

        'tier_scores' => [
            5 => 50,
            4 => 40,
            3 => 30,
        ],

        // Bônus para fase final / mata-mata (quando disponível)
        'knockout_bonus' => 15,
        'knockout_patterns' => [
            'final',
            'semi',
            'quarter',
            'round of',
            'oitavas',
            'quartas',
            'semifinal',
        ],

        // Competições / categorias excluídas
        'excluded_patterns' => [
            'libertadores',
            'série b',
            'serie b',
            'amistoso',
            'friendly',
            'feminino',
            'women',
            'sub-2',
            'sub 2',
            'u23',
            'u21',
            'u19',
            'reserve',
            'youth',
            'juniores',
            'segunda liga',
            'segunda divis',
        ],
    ],
];
