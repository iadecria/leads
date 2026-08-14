@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-white flex items-center">
                <a href="{{ route('dashboard', ['date' => $rankingRun->analysis_date->toDateString()]) }}" class="text-slate-400 hover:text-white mr-3">&larr;</a>
                Auditoria de Ranking
            </h2>
            <p class="text-slate-400 mt-1">Data-Alvo: {{ $rankingRun->analysis_date->format('d/m/Y') }} | Snapshot #{{ $rankingRun->id }}</p>
        </div>
        <div class="flex gap-4">
            <a href="{{ route('dashboard') }}" class="text-indigo-400 hover:text-indigo-300 font-medium">Voltar ao Dashboard</a>
        </div>
    </div>

    <div class="bg-slate-800 border border-slate-700 rounded-2xl overflow-hidden">
        <div class="p-6 border-b border-slate-700 bg-slate-900/50">
            <h3 class="text-lg font-bold text-white mb-4">Eventos Oficiais (TOP 3 / TOP 5)</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-slate-900/50 text-slate-400 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-4 font-medium">Pos</th>
                        <th class="px-6 py-4 font-medium">Partida</th>
                        <th class="px-6 py-4 font-medium">Evento Previsto</th>
                        <th class="px-6 py-4 font-medium">Score FAS</th>
                        <th class="px-6 py-4 font-medium">Resultado / Valor</th>
                        <th class="px-6 py-4 font-medium">Status da Auditoria</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700">
                    @foreach($rankingRun->rankings->whereIn('ranking_type', ['TOP3', 'TOP5'])->sortBy('position') as $ranking)
                        @php
                            $audit = $ranking->event->audits->where('fas_ranking_run_id', $rankingRun->id)->first();
                        @endphp
                        <tr class="hover:bg-slate-700/50 transition-colors">
                            <td class="px-6 py-4 text-slate-300 font-bold">#{{ $ranking->position }}</td>
                            <td class="px-6 py-4">
                                <div class="text-white font-medium">
                                    {{ $ranking->event->analysis->fixture->homeTeam->name }} 
                                    <span class="text-slate-500">v</span> 
                                    {{ $ranking->event->analysis->fixture->awayTeam->name }}
                                </div>
                                <div class="text-[10px] text-slate-500 mt-1">
                                    {{ $ranking->event->analysis->fixture->status }}
                                    @if($ranking->event->analysis->fixture->home_score !== null)
                                        (FT {{ $ranking->event->analysis->fixture->home_score }}x{{ $ranking->event->analysis->fixture->away_score }})
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="inline-block bg-slate-900 text-blue-400 font-bold px-2 py-1 rounded text-xs border border-blue-500/20">
                                    {{ str_replace('_', ' ', $ranking->event->event_type->value) }} {{ $ranking->event->line }}
                                </div>
                                <div class="text-[10px] text-slate-400 mt-1">
                                    Prob: {{ round($ranking->event->estimated_probability * 100, 1) }}%
                                </div>
                            </td>
                            <td class="px-6 py-4 text-emerald-400 font-bold">
                                {{ $ranking->event->fas_score }}
                            </td>
                            <td class="px-6 py-4 text-slate-300">
                                @if($audit)
                                    <span class="font-bold">{{ $audit->result_value ?? '-' }}</span>
                                    <div class="text-[10px] text-slate-500 mt-1 font-mono truncate max-w-[150px]" title="{{ $audit->payload['rule'] ?? '' }}">
                                        {{ $audit->payload['rule'] ?? '' }}
                                    </div>
                                @else
                                    <span class="text-slate-500">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($audit)
                                    @if($audit->status->value === 'HIT')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-green-500/20 text-green-400 border border-green-500/30">✅ HIT</span>
                                    @elseif($audit->status->value === 'MISS')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-red-500/20 text-red-400 border border-red-500/30">❌ MISS</span>
                                    @elseif($audit->status->value === 'PENDING')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-blue-500/20 text-blue-400 border border-blue-500/30">⏳ PENDING</span>
                                    @elseif($audit->status->value === 'UNAVAILABLE')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30">⚠️ UNAVAILABLE</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-slate-500/20 text-slate-300 border border-slate-500/30">{{ $audit->status->value }}</span>
                                    @endif
                                    <div class="text-[9px] text-slate-600 mt-1">v{{ $audit->audit_version }}</div>
                                @else
                                    <span class="text-slate-600 text-xs">Sem auditoria</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
