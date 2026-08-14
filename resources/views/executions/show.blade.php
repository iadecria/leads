<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes da Execução #{{ $run->id }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-slate-200">
    
    <nav class="bg-slate-800 p-4 shadow-lg mb-8">
        <div class="max-w-6xl mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold text-white tracking-wide"><a href="/dashboard">FAS Dashboard</a> / <a href="/fas/executions">Execuções</a> / #{{ $run->id }}</h1>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 pb-12">
        
        <div class="bg-slate-800 rounded-xl shadow border border-slate-700 p-6 mb-8">
            <h2 class="text-lg font-semibold text-white mb-4">Informações Gerais</h2>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <div>
                    <div class="text-xs text-slate-400 uppercase tracking-wide">Tipo</div>
                    <div class="font-medium mt-1">{{ $run->execution_type }}</div>
                </div>
                <div>
                    <div class="text-xs text-slate-400 uppercase tracking-wide">Data Alvo</div>
                    <div class="font-medium mt-1">{{ $run->analysis_date->format('d/m/Y') }}</div>
                </div>
                <div>
                    <div class="text-xs text-slate-400 uppercase tracking-wide">Status</div>
                    <div class="font-medium mt-1">
                        @if($run->status === 'COMPLETED')
                            <span class="text-emerald-400 font-semibold">{{ $run->status }}</span>
                        @elseif($run->status === 'FAILED')
                            <span class="text-rose-400 font-semibold">{{ $run->status }}</span>
                        @else
                            <span class="text-indigo-400 font-semibold">{{ $run->status }}</span>
                        @endif
                    </div>
                </div>
                <div>
                    <div class="text-xs text-slate-400 uppercase tracking-wide">Duração</div>
                    <div class="font-medium mt-1">
                        @if($run->started_at && $run->finished_at)
                            {{ $run->started_at->diffInSeconds($run->finished_at) }} segundos
                        @else
                            -
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-slate-800 rounded-xl shadow border border-slate-700 p-6 mb-8">
            <h2 class="text-lg font-semibold text-white mb-4">Progresso dos Passos</h2>
            
            <div class="space-y-4">
                @php
                    $steps = $run->execution_type === 'DAILY_ANALYSIS' 
                        ? ['Fixtures' => $run->fixtures_status, 'Datasets' => $run->datasets_status, 'Analysis' => $run->analysis_status, 'Ranking' => $run->ranking_status]
                        : ['Results' => $run->results_status, 'Audit' => $run->audit_status];
                @endphp

                @foreach($steps as $name => $status)
                    <div class="flex items-center justify-between p-3 bg-slate-900/50 rounded border border-slate-700">
                        <div class="font-medium">{{ $name }}</div>
                        <div>
                            @if($status === 'COMPLETED')
                                <span class="text-emerald-400">✓ Concluído</span>
                            @elseif($status === 'FAILED')
                                <span class="text-rose-400">✕ Falhou</span>
                            @elseif($status === 'RUNNING')
                                <span class="text-indigo-400">Em andamento...</span>
                            @else
                                <span class="text-slate-500">Aguardando</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        @if($run->summary)
        <div class="bg-slate-800 rounded-xl shadow border border-slate-700 p-6 mb-8">
            <h2 class="text-lg font-semibold text-white mb-4">Resumo (Summary)</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                @foreach($run->summary as $key => $val)
                    <div class="bg-slate-900/50 p-3 rounded border border-slate-700">
                        <div class="text-slate-400 text-xs mb-1 uppercase">{{ str_replace('_', ' ', $key) }}</div>
                        <div class="text-lg font-semibold text-white">{{ $val }}</div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        @if($run->errors)
        <div class="bg-slate-800 rounded-xl shadow border border-rose-500/30 p-6">
            <h2 class="text-lg font-semibold text-rose-400 mb-4 flex items-center gap-2">Erros Encontrados</h2>
            <div class="space-y-3">
                @foreach($run->errors as $error)
                    <div class="p-4 bg-rose-500/10 border border-rose-500/20 rounded text-sm text-rose-300">
                        <div class="font-medium text-white mb-1">Passo: {{ $error['step'] ?? 'Desconhecido' }} ({{ $error['time'] ?? '' }})</div>
                        <div>{{ $error['message'] ?? json_encode($error) }}</div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

    </main>
</body>
</html>
