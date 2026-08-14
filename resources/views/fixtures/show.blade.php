@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    
    <!-- Back button -->
    <div>
        <a href="{{ route('dashboard') }}" class="inline-flex items-center text-sm font-medium text-slate-400 hover:text-white transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            Voltar para o Dashboard
        </a>
    </div>

    <!-- Match Header -->
    <div class="bg-slate-800 border border-slate-700 rounded-3xl p-6 md:p-10 relative overflow-hidden">
        <!-- Background accents -->
        <div class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-blue-600/10 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 left-0 -ml-16 -mb-16 w-64 h-64 bg-purple-600/10 rounded-full blur-3xl"></div>
        
        <div class="relative z-10">
            <div class="text-center mb-6">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">{{ $fixture->competition->name ?? 'Competição' }} - Season {{ $fixture->season }} - {{ $fixture->round }}</p>
                <p class="text-sm text-slate-500 mt-1">{{ $fixture->fixture_date->setTimezone('America/Sao_Paulo')->format('d/m/Y \à\s H:i') }} | {{ $fixture->venue ?? 'Estádio Desconhecido' }}</p>
            </div>
            
            <div class="flex items-center justify-between md:justify-center md:gap-16">
                <!-- Home Team -->
                <div class="flex flex-col items-center w-1/3">
                    @if($fixture->homeTeam->logo)
                        <img src="{{ $fixture->homeTeam->logo }}" alt="{{ $fixture->homeTeam->name }}" class="w-16 h-16 md:w-24 md:h-24 mb-4 object-contain drop-shadow-lg">
                    @else
                        <div class="w-16 h-16 md:w-24 md:h-24 bg-slate-900 rounded-2xl flex items-center justify-center border border-slate-700 mb-4 shadow-lg shadow-black/20">
                            <span class="text-2xl font-bold text-slate-600">H</span>
                        </div>
                    @endif
                    <h2 class="text-lg md:text-xl font-bold text-white text-center">{{ $fixture->homeTeam->name ?? 'Home' }}</h2>
                </div>
                
                <!-- Score / VS -->
                <div class="flex flex-col items-center justify-center w-1/3">
                    <div class="bg-slate-900 px-4 py-2 rounded-xl border border-slate-700 shadow-inner">
                        <span class="text-2xl md:text-4xl font-black tracking-widest text-white">
                            @if(in_array($fixture->status, ['FT', 'PEN', 'AET', 'HT', '1H', '2H']))
                                {{ $fixture->home_score ?? 0 }} - {{ $fixture->away_score ?? 0 }}
                            @else
                                VS
                            @endif
                        </span>
                    </div>
                    <span class="mt-3 text-xs font-medium px-2.5 py-0.5 rounded bg-slate-700 text-slate-300">
                        {{ $fixture->status }}
                    </span>
                    @if($fixture->elapsed)
                        <span class="mt-1 text-xs text-blue-400 font-bold">{{ $fixture->elapsed }}'</span>
                    @endif
                </div>
                
                <!-- Away Team -->
                <div class="flex flex-col items-center w-1/3">
                    @if($fixture->awayTeam->logo)
                        <img src="{{ $fixture->awayTeam->logo }}" alt="{{ $fixture->awayTeam->name }}" class="w-16 h-16 md:w-24 md:h-24 mb-4 object-contain drop-shadow-lg">
                    @else
                        <div class="w-16 h-16 md:w-24 md:h-24 bg-slate-900 rounded-2xl flex items-center justify-center border border-slate-700 mb-4 shadow-lg shadow-black/20">
                            <span class="text-2xl font-bold text-slate-600">A</span>
                        </div>
                    @endif
                    <h2 class="text-lg md:text-xl font-bold text-white text-center">{{ $fixture->awayTeam->name ?? 'Away' }}</h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Coverage Badges -->
    <h3 class="text-lg font-bold text-white mt-8 mb-4 flex items-center">
        <span class="w-1.5 h-5 bg-blue-500 rounded-full mr-2"></span>
        Cobertura da API
    </h3>

    @php
        $season = $fixture->competition->seasons->where('season', $fixture->season)->first();
        $coverage = $season ? ($season->coverage ?? []) : [];
    @endphp

    <div class="flex flex-wrap gap-3">
        <div class="px-4 py-2 rounded-xl border {{ !empty($coverage['statistics_fixtures']) ? 'bg-green-500/10 border-green-500/20 text-green-400' : 'bg-slate-800 border-slate-700 text-slate-500' }}">
            <span class="font-medium text-sm">Statistics {{ !empty($coverage['statistics_fixtures']) ? '✓' : '✕' }}</span>
        </div>
        <div class="px-4 py-2 rounded-xl border {{ !empty($coverage['lineups']) ? 'bg-green-500/10 border-green-500/20 text-green-400' : 'bg-slate-800 border-slate-700 text-slate-500' }}">
            <span class="font-medium text-sm">Lineups {{ !empty($coverage['lineups']) ? '✓' : '✕' }}</span>
        </div>
        <div class="px-4 py-2 rounded-xl border {{ !empty($coverage['predictions']) ? 'bg-green-500/10 border-green-500/20 text-green-400' : 'bg-slate-800 border-slate-700 text-slate-500' }}">
            <span class="font-medium text-sm">Predictions {{ !empty($coverage['predictions']) ? '✓' : '✕' }}</span>
        </div>
        <div class="px-4 py-2 rounded-xl border {{ !empty($coverage['odds']) ? 'bg-green-500/10 border-green-500/20 text-green-400' : 'bg-slate-800 border-slate-700 text-slate-500' }}">
            <span class="font-medium text-sm">Odds {{ !empty($coverage['odds']) ? '✓' : '✕' }}</span>
        </div>
        <div class="px-4 py-2 rounded-xl border {{ !empty($coverage['injuries']) ? 'bg-green-500/10 border-green-500/20 text-green-400' : 'bg-slate-800 border-slate-700 text-slate-500' }}">
            <span class="font-medium text-sm">Injuries {{ !empty($coverage['injuries']) ? '✓' : '✕' }}</span>
        </div>
    </div>

    <!-- FAS Status -->
    <div class="bg-slate-800 border border-slate-700 rounded-2xl p-6 mt-6">
        <h4 class="text-slate-400 font-medium mb-2">Elegibilidade no Sistema FAS</h4>
        <div class="flex items-center gap-4">
            @if($fixture->fas_status === 'ELIGIBLE')
                <span class="px-4 py-2 bg-green-500/20 text-green-400 rounded-lg font-bold">ELEGIBLE</span>
                <p class="text-sm text-slate-400">Esta partida atende a todos os critérios e será analisada pelo algoritmo.</p>
            @elseif($fixture->fas_status === 'EXCLUDED')
                <span class="px-4 py-2 bg-red-500/20 text-red-400 rounded-lg font-bold">EXCLUDED</span>
                <p class="text-sm text-slate-400">Excluído: {{ str_replace('_', ' ', $fixture->fas_status_reason) }}</p>
            @else
                <span class="px-4 py-2 bg-slate-700 text-slate-300 rounded-lg font-bold">UNKNOWN</span>
            @endif
        </div>
    </div>

    <div class="bg-yellow-500/10 border border-yellow-500/20 rounded-xl p-6 text-center text-yellow-500 mt-6">
        "Não calculado" - O algoritmo FAS ainda não foi executado para esta partida.
    </div>

    <!-- Dataset FAS -->
    <div class="bg-slate-800 border border-slate-700 rounded-2xl p-6 mt-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-4">
            <div>
                <h4 class="text-white font-bold text-lg">Dataset FAS</h4>
                <p class="text-slate-400 text-sm">Dados brutos normalizados pelo Builder.</p>
            </div>
            
            <form action="{{ route('fixtures.dataset.build', $fixture->id) }}" method="POST">
                @csrf
                <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    Gerar Dataset
                </button>
            </form>
        </div>

        @if(session('success'))
            <div class="bg-green-500/10 border border-green-500/20 text-green-400 px-4 py-3 rounded-xl mb-4 text-sm" role="alert">
                {{ session('success') }}
            </div>
        @endif

        @if($dataset)
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                <div class="bg-slate-900 rounded-xl p-4 border border-slate-700">
                    <span class="block text-slate-500 text-xs mb-1">Status</span>
                    <span class="text-green-400 font-bold">Gerado</span>
                </div>
                <div class="bg-slate-900 rounded-xl p-4 border border-slate-700">
                    <span class="block text-slate-500 text-xs mb-1">Versão</span>
                    <span class="text-white font-bold">{{ $dataset->dataset_version }}</span>
                </div>
                <div class="bg-slate-900 rounded-xl p-4 border border-slate-700">
                    <span class="block text-slate-500 text-xs mb-1">Data Quality</span>
                    <span class="text-blue-400 font-bold">{{ $dataset->data_quality_score }}/100 — {{ $dataset->data_quality_level }}</span>
                </div>
                <div class="bg-slate-900 rounded-xl p-4 flex items-center justify-center border border-slate-700 hover:bg-slate-800 transition-colors">
                    <a href="{{ route('fixtures.dataset.show', $fixture->id) }}" target="_blank" class="text-blue-400 hover:text-blue-300 text-sm font-medium flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                        Ver Dataset JSON
                    </a>
                </div>
            </div>
            <div class="text-xs text-slate-500 text-right">
                Gerado em: {{ $dataset->generated_at->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s') }}
            </div>
        @else
            <div class="bg-slate-900 rounded-xl p-6 text-center border border-slate-700">
                <span class="text-slate-500 font-medium">Dataset ainda não gerado para esta partida.</span>
                <p class="text-slate-600 text-xs mt-2">Clique em "Gerar Dataset" para extrair os dados da API-Football e processar as estatísticas.</p>
            </div>
        @endif
    <!-- FAS Engine Results -->
    <div class="bg-slate-800 border border-slate-700 rounded-2xl p-6 mt-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <div>
                <h4 class="text-white font-bold text-lg">Análise FAS Engine V1</h4>
                <p class="text-slate-400 text-sm">Previsões probabilísticas e recomendação final.</p>
            </div>
            
            <form action="{{ route('fixtures.run_fas', $fixture->id) }}" method="POST">
                @csrf
                <button type="submit" class="bg-purple-600 hover:bg-purple-500 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-lg shadow-purple-600/20">
                    Rodar Engine V1
                </button>
            </form>
        </div>

        @php
            $analysis = $fixture->analyses->first();
        @endphp

        @if($analysis && $analysis->events->isNotEmpty())
            <div class="space-y-4">
                @foreach($analysis->events as $event)
                    <div class="bg-slate-900 border border-slate-700 rounded-xl p-4 hover:border-slate-600 transition-colors">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                            
                            <!-- Event Info -->
                            <div class="w-full md:w-1/4">
                                <span class="text-xs font-bold text-slate-500 tracking-wider">EVENTO</span>
                                <h5 class="text-white font-bold text-lg">
                                    {{ str_replace('_', ' ', $event->event_type) }} 
                                    @if($event->line !== null) {{ $event->line }} @endif
                                </h5>
                                <div class="flex items-center mt-1">
                                    <span class="text-xs text-slate-400">Score FAS:</span>
                                    <span class="ml-1 text-sm font-bold {{ $event->fas_score >= 80 ? 'text-green-400' : ($event->fas_score >= 50 ? 'text-yellow-400' : 'text-slate-400') }}">
                                        {{ $event->fas_score }}
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Probabilities -->
                            <div class="w-full md:w-1/4 flex items-center justify-between md:justify-around bg-slate-800/50 p-2 rounded-lg">
                                <div class="text-center">
                                    <span class="block text-[10px] text-slate-500 uppercase">Ajustada</span>
                                    <span class="font-bold text-white text-lg">
                                        {{ $event->estimated_probability !== null ? round($event->estimated_probability * 100, 1) . '%' : 'N/A' }}
                                    </span>
                                </div>
                                <div class="w-px h-8 bg-slate-700"></div>
                                <div class="text-center">
                                    <span class="block text-[10px] text-slate-500 uppercase">Bruta (Raw)</span>
                                    @php
                                        $payload = json_decode($event->payload, true);
                                    @endphp
                                    <span class="font-bold text-slate-400">
                                        {{ isset($payload['raw_probability']) ? round($payload['raw_probability'] * 100, 1) . '%' : 'N/A' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Confidence & Quality -->
                            <div class="w-full md:w-1/4">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-xs text-slate-500">Confiança</span>
                                    <span class="text-xs font-bold 
                                        {{ $event->confidence === 'VERY_HIGH' || $event->confidence === 'HIGH' ? 'text-green-400' : 
                                           ($event->confidence === 'MEDIUM' ? 'text-yellow-400' : 'text-red-400') }}">
                                        {{ $event->confidence }}
                                    </span>
                                </div>
                                <div class="w-full bg-slate-800 rounded-full h-1.5 mb-2">
                                    @php
                                        $confWidth = ['VERY_LOW' => 10, 'LOW' => 30, 'MEDIUM' => 50, 'HIGH' => 80, 'VERY_HIGH' => 100][$event->confidence] ?? 0;
                                    @endphp
                                    <div class="bg-blue-500 h-1.5 rounded-full" style="width: {{ $confWidth }}%"></div>
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-slate-500">Data Quality</span>
                                    <span class="text-xs font-medium text-slate-300">{{ $payload['data_quality_score'] ?? 'N/A' }}</span>
                                </div>
                            </div>
                            
                            <!-- Debug Action -->
                            <div class="w-full md:w-auto text-right">
                                <button onclick="alert('Funcionalidade de debug visual JSON: \n' + JSON.stringify({{ $event->payload }}, null, 2))" class="text-xs text-blue-400 hover:text-blue-300 font-medium flex items-center justify-end w-full">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Ver Fatores
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            
            <div class="mt-4 text-xs text-slate-500 flex justify-between">
                <span>Versão do Engine: {{ $analysis->events->first()->payload ? (json_decode($analysis->events->first()->payload, true)['engine_version'] ?? '1.0.0') : '1.0.0' }}</span>
                <span>Gerado em: {{ $analysis->created_at->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</span>
            </div>
        @else
            <div class="bg-slate-900 rounded-xl p-8 text-center border border-slate-700">
                <svg class="w-12 h-12 mx-auto text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path></svg>
                <span class="text-slate-400 font-medium block text-lg mb-1">Nenhuma análise disponível</span>
                <p class="text-slate-500 text-sm max-w-md mx-auto">Clique em "Rodar Engine V1" para que o algoritmo do FAS analise o Dataset desta partida e gere as previsões.</p>
            </div>
        @endif
    </div>

</div>
@endsection
