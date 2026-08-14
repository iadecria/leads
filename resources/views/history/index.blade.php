@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-white">Histórico de Análises</h2>
            <p class="text-slate-400">Consulte auditorias de resultados passados</p>
        </div>
        
        <!-- Filters (Placeholder for future) -->
        <div class="flex gap-2">
            <button class="bg-slate-800 border border-slate-700 text-slate-300 px-4 py-2 rounded-xl text-sm font-medium hover:bg-slate-700 transition-colors flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                Filtros
            </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-slate-800 border border-slate-700 rounded-2xl p-5 relative overflow-hidden">
            <div class="absolute right-0 top-0 opacity-10 m-4">
                <svg class="w-12 h-12 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
            </div>
            <p class="text-slate-400 text-sm font-medium mb-1">Total Analisado</p>
            <p class="text-3xl font-bold text-white">0</p>
        </div>
        <div class="bg-slate-800 border border-slate-700 rounded-2xl p-5">
            <p class="text-slate-400 text-sm font-medium mb-1">Acertos</p>
            <p class="text-3xl font-bold text-green-400">0</p>
        </div>
        <div class="bg-slate-800 border border-slate-700 rounded-2xl p-5">
            <p class="text-slate-400 text-sm font-medium mb-1">Erros</p>
            <p class="text-3xl font-bold text-red-400">0</p>
        </div>
        <div class="bg-slate-800 border border-slate-700 rounded-2xl p-5 bg-gradient-to-br from-slate-800 to-slate-900">
            <p class="text-slate-400 text-sm font-medium mb-1">Taxa de Acerto</p>
            <p class="text-3xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-purple-500">0%</p>
        </div>
    </div>

    <!-- Runs Table -->
    <div class="bg-slate-800 border border-slate-700 rounded-2xl overflow-hidden mt-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-slate-900/50 text-slate-400 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-4 font-medium">Data da Análise</th>
                        <th class="px-6 py-4 font-medium">Jogos Analisados</th>
                        <th class="px-6 py-4 font-medium">Quality Score</th>
                        <th class="px-6 py-4 font-medium">Versão Algo</th>
                        <th class="px-6 py-4 font-medium text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700">
                    @forelse($runs as $run)
                        <tr class="hover:bg-slate-700/50 transition-colors">
                            <td class="px-6 py-4 font-medium text-white">
                                {{ $run->analysis_date->format('d/m/Y') }}
                            </td>
                            <td class="px-6 py-4 text-slate-300">
                                {{ $run->fixtures_analyzed }} / {{ $run->fixtures_eligible }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-purple-400 font-medium">{{ $run->data_quality_score ?? '-' }}</span>
                            </td>
                            <td class="px-6 py-4 text-slate-500 text-xs font-mono">
                                v{{ $run->algorithm_version ?? '1.0.0' }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-900 text-slate-300 border border-slate-700">
                                    {{ $run->status->value }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-slate-400">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="w-12 h-12 mb-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <p>Nenhum histórico encontrado.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($runs->hasPages())
            <div class="px-6 py-4 border-t border-slate-700 bg-slate-900/30">
                {{ $runs->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
