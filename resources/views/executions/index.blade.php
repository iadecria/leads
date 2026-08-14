<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Execuções</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-slate-200">
    
    <nav class="bg-slate-800 p-4 shadow-lg mb-8">
        <div class="max-w-6xl mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold text-white tracking-wide"><a href="/dashboard">FAS <span class="text-indigo-400">Dashboard</span></a> / Execuções</h1>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4">
        
        <div class="bg-slate-800 rounded-xl shadow-lg border border-slate-700 overflow-hidden">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-700/50 text-slate-300 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-6 py-4">ID</th>
                        <th class="px-6 py-4">Tipo</th>
                        <th class="px-6 py-4">Data Alvo</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Duração</th>
                        <th class="px-6 py-4 text-right">Ação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/50">
                    @forelse ($executions as $run)
                    <tr class="hover:bg-slate-750 transition">
                        <td class="px-6 py-4">#{{ $run->id }}</td>
                        <td class="px-6 py-4 font-medium">{{ $run->execution_type }}</td>
                        <td class="px-6 py-4">{{ $run->analysis_date->format('d/m/Y') }}</td>
                        <td class="px-6 py-4">
                            @if($run->status === 'COMPLETED')
                                <span class="px-2 py-1 bg-emerald-500/20 text-emerald-400 rounded text-xs font-semibold">COMPLETED</span>
                            @elseif($run->status === 'FAILED')
                                <span class="px-2 py-1 bg-rose-500/20 text-rose-400 rounded text-xs font-semibold">FAILED</span>
                            @elseif($run->status === 'RUNNING')
                                <span class="px-2 py-1 bg-indigo-500/20 text-indigo-400 rounded text-xs font-semibold">RUNNING</span>
                            @else
                                <span class="px-2 py-1 bg-slate-500/20 text-slate-400 rounded text-xs font-semibold">{{ $run->status }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-slate-400">
                            @if($run->started_at && $run->finished_at)
                                {{ $run->started_at->diffInSeconds($run->finished_at) }}s
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="/fas/executions/{{ $run->id }}" class="text-indigo-400 hover:text-indigo-300 font-medium text-sm">Detalhes →</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-slate-500">Nenhuma execução registrada.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $executions->links() }}
        </div>

    </main>
</body>
</html>
