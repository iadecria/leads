@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto space-y-8">
    
    <!-- Back button -->
    <div>
        <a href="{{ route('dashboard') }}" class="inline-flex items-center text-sm font-medium text-slate-400 hover:text-white transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            Voltar para o Dashboard
        </a>
    </div>

    <!-- Header -->
    <div class="bg-slate-800 border border-slate-700 rounded-3xl p-8 relative overflow-hidden">
        <div class="absolute top-0 right-0 w-64 h-64 bg-emerald-600/10 rounded-full blur-3xl"></div>
        
        <div class="relative z-10">
            <h2 class="text-3xl font-black text-white mb-2">Auditoria do Ranking FAS</h2>
            <div class="flex flex-wrap gap-4 text-sm text-slate-400">
                <span class="bg-slate-900 px-3 py-1 rounded-lg border border-slate-700">Data Base: <span class="text-white font-bold">{{ $run->analysis_date->format('d/m/Y') }}</span></span>
                <span class="bg-slate-900 px-3 py-1 rounded-lg border border-slate-700">Gerado: <span class="text-white font-bold">{{ $run->generated_at->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s') }}</span></span>
                <span class="bg-slate-900 px-3 py-1 rounded-lg border border-slate-700">Engine V: <span class="text-white font-bold">{{ $run->engine_version }}</span></span>
                <span class="bg-slate-900 px-3 py-1 rounded-lg border border-slate-700">Ranking V: <span class="text-white font-bold">{{ $run->ranking_version }}</span></span>
            </div>
        </div>
    </div>

    <!-- Config Snapshot Info -->
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
        <h3 class="text-lg font-bold text-white mb-4">Configuração Utilizada (Snapshot)</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-slate-900 rounded p-4 border border-slate-700">
                <h4 class="text-sm font-bold text-slate-400 mb-2">Penalidades Aplicadas</h4>
                <ul class="text-xs text-slate-300 space-y-1">
                    @foreach($run->config_snapshot['penalties'] ?? [] as $key => $val)
                        <li>{{ $key }}: <span class="text-red-400">-{{ $val }}</span></li>
                    @endforeach
                </ul>
            </div>
            <div class="bg-slate-900 rounded p-4 border border-slate-700">
                <h4 class="text-sm font-bold text-slate-400 mb-2">Thresholds TOP 3</h4>
                <ul class="text-xs text-slate-300 space-y-1">
                    <li>Probabilidade: >= {{ ($run->config_snapshot['top3']['minimum_probability'] ?? 0) * 100 }}%</li>
                    <li>Score FAS: >= {{ $run->config_snapshot['top3']['minimum_fas_score'] ?? 0 }}</li>
                    <li>Confiança: {{ implode(', ', $run->config_snapshot['top3']['allowed_confidence'] ?? []) }}</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- The Lists -->
    @foreach(['TOP3', 'TOP5', 'WATCHLIST', 'REJECTED'] as $tier)
        @php
            $tierItems = $run->rankings->where('ranking_type', $tier);
            if($tierItems->isEmpty()) continue;
        @endphp
        
        <div class="mt-8">
            <h3 class="text-xl font-bold text-white mb-4">
                {{ $tier }} <span class="text-sm font-normal text-slate-400">({{ $tierItems->count() }} itens)</span>
            </h3>
            
            <div class="bg-slate-800 border border-slate-700 rounded-xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-slate-900/50 text-slate-400 text-xs uppercase tracking-wider">
                            <tr>
                                @if(in_array($tier, ['TOP3', 'TOP5']))
                                    <th class="px-4 py-3 font-medium">Pos</th>
                                @endif
                                <th class="px-4 py-3 font-medium">Partida</th>
                                <th class="px-4 py-3 font-medium">Evento</th>
                                <th class="px-4 py-3 font-medium text-center">Score FAS</th>
                                <th class="px-4 py-3 font-medium text-center">Cand. Score</th>
                                <th class="px-4 py-3 font-medium text-right">Penalidades / Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700">
                            @foreach($tierItems->sortBy($tier === 'TOP3' || $tier === 'TOP5' ? 'position' : 'candidate_score', SORT_REGULAR, $tier === 'WATCHLIST') as $item)
                                <tr class="hover:bg-slate-700/50 transition-colors">
                                    @if(in_array($tier, ['TOP3', 'TOP5']))
                                        <td class="px-4 py-3 text-white font-bold">#{{ $item->position }}</td>
                                    @endif
                                    <td class="px-4 py-3">
                                        <div class="text-xs text-slate-400">{{ $item->event->analysis->fixture->fixture_date->format('H:i') }} | {{ $item->event->analysis->fixture->competition->name }}</div>
                                        <div class="text-white font-medium">{{ $item->event->analysis->fixture->homeTeam->name }} v {{ $item->event->analysis->fixture->awayTeam->name }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-emerald-400">{{ str_replace('_', ' ', $item->event->event_type->value) }} {{ $item->event->line }}</div>
                                        <div class="text-xs text-slate-400">Prob: {{ round($item->event->estimated_probability * 100, 1) }}% | Conf: {{ $item->event->confidence->value }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-center text-white font-bold">{{ $item->event->fas_score }}</td>
                                    <td class="px-4 py-3 text-center text-emerald-400 font-bold">{{ $item->candidate_score }}</td>
                                    <td class="px-4 py-3 text-right">
                                        @if($item->watchlist_reason)
                                            <span class="inline-block bg-slate-900 border border-slate-700 px-2 py-1 rounded text-xs text-slate-300 mb-1">
                                                {{ $item->watchlist_reason }}
                                            </span>
                                            <br>
                                        @endif
                                        @foreach($item->penalties ?? [] as $penalty)
                                            <span class="inline-block bg-red-500/10 border border-red-500/20 text-red-400 px-2 py-0.5 rounded text-[10px] uppercase">
                                                {{ str_replace('_', ' ', $penalty) }}
                                            </span>
                                        @endforeach
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endforeach

</div>
@endsection
