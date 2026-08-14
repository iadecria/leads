@extends('layouts.app')

@section('content')
<div class="space-y-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-white">Performance Analytics</h2>
            <p class="text-slate-400">Histórico de validações, calibração e taxa de acerto do FAS</p>
        </div>
        <form action="{{ route('performance.index') }}" method="GET" class="flex gap-2">
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="bg-slate-800 border border-slate-700 text-slate-200 rounded-xl px-4 py-2" placeholder="Desde">
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="bg-slate-800 border border-slate-700 text-slate-200 rounded-xl px-4 py-2" placeholder="Até">
            <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-xl font-medium transition-all shadow-lg shadow-blue-900/20">
                Filtrar
            </button>
        </form>
    </div>

    <!-- Overall Overview -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-slate-800 border border-slate-700 rounded-2xl p-5">
            <p class="text-slate-400 text-sm font-medium mb-1">Total Predictions (Auditadas)</p>
            <p class="text-3xl font-bold text-white">{{ $overall['total_predictions'] }} <span class="text-lg text-slate-500">({{ $overall['audited_predictions'] }})</span></p>
        </div>
        <div class="bg-slate-800 border border-slate-700 rounded-2xl p-5">
            <p class="text-slate-400 text-sm font-medium mb-1">Geral Hit Rate</p>
            <p class="text-3xl font-bold {{ $overall['hit_rate'] >= 60 ? 'text-emerald-400' : 'text-amber-400' }}">{{ $overall['hit_rate'] }}%</p>
        </div>
        <div class="bg-slate-800 border border-slate-700 rounded-2xl p-5">
            <p class="text-slate-400 text-sm font-medium mb-1">Brier Score (Geral)</p>
            <p class="text-3xl font-bold text-blue-400">{{ $overall['brier_score'] ?? '-' }}</p>
        </div>
        <div class="bg-slate-800 border border-slate-700 rounded-2xl p-5">
            <p class="text-slate-400 text-sm font-medium mb-1">Calibration Gap (Geral)</p>
            <p class="text-3xl font-bold {{ ($overall['calibration_gap'] ?? 0) >= 0 ? 'text-emerald-400' : 'text-red-400' }}">{{ ($overall['calibration_gap'] ?? 0) > 0 ? '+' : '' }}{{ $overall['calibration_gap'] ?? '-' }}</p>
        </div>
    </div>

    <!-- Tiers Performance -->
    <h3 class="text-xl font-bold text-white mt-8 mb-4">Performance por Tier</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        @foreach(['top3', 'top5', 'watchlist'] as $tier)
            @if(isset($tiers[$tier]))
                <div class="bg-slate-800/50 border border-slate-700 rounded-2xl p-6">
                    <h4 class="text-lg font-bold text-white mb-4 uppercase">{{ $tier }}</h4>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-slate-400">Audited</span>
                            <span class="text-white font-bold">{{ $tiers[$tier]['audited_predictions'] }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-400">Hits / Misses</span>
                            <span class="text-white font-bold"><span class="text-emerald-400">{{ $tiers[$tier]['hits'] }}</span> / <span class="text-red-400">{{ $tiers[$tier]['misses'] }}</span></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-400">Hit Rate</span>
                            <span class="text-white font-bold text-xl {{ $tiers[$tier]['hit_rate'] >= 60 ? 'text-emerald-400' : 'text-amber-400' }}">{{ $tiers[$tier]['hit_rate'] }}%</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-400">Brier Score</span>
                            <span class="text-white font-bold">{{ $tiers[$tier]['brier_score'] ?? '-' }}</span>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    </div>

    <!-- Events Performance -->
    <h3 class="text-xl font-bold text-white mt-8 mb-4">Performance por Evento</h3>
    <div class="bg-slate-800 border border-slate-700 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-slate-900/50 text-slate-400 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-4 font-medium">Evento</th>
                        <th class="px-6 py-4 font-medium text-center">Audited</th>
                        <th class="px-6 py-4 font-medium text-center">Hits</th>
                        <th class="px-6 py-4 font-medium text-center">Hit Rate</th>
                        <th class="px-6 py-4 font-medium text-center">Avg Prob</th>
                        <th class="px-6 py-4 font-medium text-center">Brier</th>
                        <th class="px-6 py-4 font-medium text-right">Calib Gap</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700">
                    @foreach($events as $event => $metrics)
                        <tr class="hover:bg-slate-700/50 transition-colors">
                            <td class="px-6 py-4 text-white font-bold">{{ str_replace('_', ' ', $event) }}</td>
                            <td class="px-6 py-4 text-center text-slate-300">{{ $metrics['audited_predictions'] }}</td>
                            <td class="px-6 py-4 text-center text-emerald-400 font-bold">{{ $metrics['hits'] }}</td>
                            <td class="px-6 py-4 text-center font-bold {{ $metrics['hit_rate'] >= 60 ? 'text-emerald-400' : 'text-amber-400' }}">{{ $metrics['hit_rate'] }}%</td>
                            <td class="px-6 py-4 text-center text-slate-300">{{ $metrics['average_probability'] ? round($metrics['average_probability'] * 100, 1) . '%' : '-' }}</td>
                            <td class="px-6 py-4 text-center text-blue-400">{{ $metrics['brier_score'] ?? '-' }}</td>
                            <td class="px-6 py-4 text-right {{ ($metrics['calibration_gap'] ?? 0) >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                {{ ($metrics['calibration_gap'] ?? 0) > 0 ? '+' : '' }}{{ $metrics['calibration_gap'] ?? '-' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Calibration -->
    <h3 class="text-xl font-bold text-white mt-8 mb-4">Probability Calibration (Faixas)</h3>
    <div class="bg-slate-800 border border-slate-700 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-slate-900/50 text-slate-400 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-4 font-medium">Faixa Probabilidade</th>
                        <th class="px-6 py-4 font-medium text-center">Audited</th>
                        <th class="px-6 py-4 font-medium text-center">Avg Predicted</th>
                        <th class="px-6 py-4 font-medium text-center">Observed (Hit Rate)</th>
                        <th class="px-6 py-4 font-medium text-center">Gap (Over/Under)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700">
                    @foreach($calibration as $metrics)
                        <tr class="hover:bg-slate-700/50 transition-colors">
                            <td class="px-6 py-4 text-white font-bold">{{ $metrics['label'] }}</td>
                            <td class="px-6 py-4 text-center text-slate-300">{{ $metrics['audited_predictions'] }}</td>
                            <td class="px-6 py-4 text-center text-slate-300">{{ $metrics['average_probability'] ? round($metrics['average_probability'] * 100, 1) . '%' : '-' }}</td>
                            <td class="px-6 py-4 text-center font-bold text-emerald-400">{{ $metrics['hit_rate'] }}%</td>
                            <td class="px-6 py-4 text-center font-bold {{ ($metrics['calibration_gap'] ?? 0) >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                {{ ($metrics['calibration_gap'] ?? 0) > 0 ? '+' : '' }}{{ $metrics['calibration_gap'] ? round($metrics['calibration_gap'] * 100, 1) . '%' : '-' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
