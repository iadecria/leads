<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAS Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-slate-950 text-slate-100">
    <main class="mx-auto max-w-5xl px-4 py-4 sm:py-6" x-data="fasDashboard()">
        <header class="mb-4 rounded-2xl border border-slate-800 bg-slate-900/90 p-4 shadow-lg">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">FAS</h1>
                    <p class="text-sm text-slate-400">Painel operacional</p>
                </div>
                <a href="/fas/executions" class="text-sm text-indigo-300 hover:text-indigo-200">Execuções</a>
            </div>
        </header>

        @if(session('success'))
            <div class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-emerald-300">
                {{ session('success') }}
            </div>
        @endif

        <div x-show="message" class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-emerald-300" x-text="message" style="display:none;"></div>
        <div x-show="error" class="mb-4 rounded-xl border border-rose-500/30 bg-rose-500/10 p-4 text-rose-300" x-text="error" style="display:none;"></div>

        <section class="mb-4 rounded-2xl border border-slate-800 bg-slate-900 p-4 shadow-lg">
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <label class="mb-2 block text-sm text-slate-400">Data</label>
                        <input type="date" x-model="selectedDate" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-base text-white outline-none focus:border-indigo-500 sm:w-56">
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:w-auto sm:grid-cols-3">
                        <button @click="searchGameDay" :disabled="isRunning || !canGenerate"
                            class="rounded-xl bg-sky-600 px-5 py-3 font-semibold text-white transition hover:bg-sky-500 disabled:cursor-not-allowed disabled:opacity-50">
                            BUSCAR JOGOS DO DIA
                        </button>
                        <button @click="runResearch" :disabled="isRunning || !canGenerate || !hasDiscoveredGames"
                            class="rounded-xl bg-indigo-600 px-5 py-3 font-semibold text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50">
                            RODAR FAS
                        </button>
                        <button @click="runAudit" :disabled="isRunning || !canAudit"
                            class="rounded-xl bg-emerald-600 px-5 py-3 font-semibold text-white transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-50">
                            CONFERIR
                        </button>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-800 bg-slate-950 px-4 py-3 text-sm text-slate-300">
                    <span class="font-medium text-slate-100">Status:</span>
                    <span x-text="statusText || '{{ $statusText }}'"></span>
                </div>
            </div>
        </section>

        <section x-show="gameDay && gameDay.selected_count > 0" class="mb-4 rounded-2xl border border-slate-800 bg-slate-900 p-4 shadow-lg">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Jogos do Dia</h2>
            </div>
            <div class="space-y-4">
                <div class="rounded-xl border border-slate-800 bg-slate-950 p-4">
                    <h3 class="mb-3 font-semibold text-sky-300">JOGOS ATÉ 17H (Janela 1)</h3>
                    <template x-for="(game, i) in gameDay?.window_1 || []" :key="i">
                        <div class="flex justify-between rounded-xl bg-slate-900 px-3 py-2 text-sm">
                            <span x-text="(i + 1) + '. ' + game.home_team + ' x ' + game.away_team"></span>
                            <span class="text-xs text-slate-400" x-text="game.kickoff_time + ' · ' + game.competition"></span>
                        </div>
                    </template>
                    <p x-show="!gameDay?.window_1?.length" class="text-sm text-slate-500">Sem jogos até 17h.</p>
                </div>
                <div class="rounded-xl border border-slate-800 bg-slate-950 p-4">
                    <h3 class="mb-3 font-semibold text-indigo-300">JOGOS APÓS 17H (Janela 2)</h3>
                    <template x-for="(game, i) in gameDay?.window_2 || []" :key="i">
                        <div class="flex justify-between rounded-xl bg-slate-900 px-3 py-2 text-sm">
                            <span x-text="(i + 1) + '. ' + game.home_team + ' x ' + game.away_team"></span>
                            <span class="text-xs text-slate-400" x-text="game.kickoff_time + ' · ' + game.competition"></span>
                        </div>
                    </template>
                    <p x-show="!gameDay?.window_2?.length" class="text-sm text-slate-500">Sem jogos após 17h.</p>
                </div>
            </div>
            <div class="mt-4 rounded-xl border border-slate-800 bg-slate-950 px-4 py-3 text-xs text-slate-400">
                <span class="font-medium text-slate-300">Descoberta:</span>
                <span x-text="(gameDay?.fixtures_eligible || 0) + ' jogos · ' + (gameDay?.tokens || 0) + ' tokens · US$ ' + (gameDay?.estimated_cost_usd || '0')"></span>
            </div>
        </section>

        <!-- RESULTADOS RESEARCH AGENT -->
        <section x-show="researchResult" class="mb-4 rounded-2xl border border-slate-800 bg-slate-900 p-4 shadow-lg">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold">Top 3 FAS — Research Agent</h2>
                    <p class="text-xs text-slate-500">Research Agent · Não calibrado</p>
                </div>
                <span class="text-xs text-slate-400">{{ $date }}</span>
            </div>
            <div class="space-y-3">
                <template x-for="(item, i) in researchResult?.top3 || []" :key="i">
                    <div class="rounded-xl border border-slate-800 bg-slate-950 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-semibold" x-text="'#' + (i + 1) + ' ' + top3Label(item)"></div>
                                <div class="text-xs text-slate-400" x-text="top3Team(item) + ' · ' + top3Event(item)"></div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-bold text-emerald-300" x-text="Math.round((item.estimated_probability || 0) * 100) + '%'"></div>
                                <div class="text-xs text-slate-500" x-text="item.confidence || ''"></div>
                            </div>
                        </div>
                    </div>
                </template>
                <p x-show="!(researchResult?.top3 || []).length" class="text-sm text-slate-500">Nenhum TOP 3 gerado.</p>
            </div>

            <div class="mt-6 mb-3">
                <h3 class="text-lg font-semibold">Top 5</h3>
                <p class="text-xs text-slate-500">Research Agent · Não calibrado</p>
            </div>
            <div class="space-y-3">
                <template x-for="(item, i) in researchResult?.top5 || []" :key="i">
                    <div class="rounded-xl border border-slate-800 bg-slate-950 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-semibold" x-text="'#' + (i + 1) + ' ' + top3Label(item)"></div>
                                <div class="text-xs text-slate-400" x-text="top3Team(item) + ' · ' + top3Event(item)"></div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-bold text-indigo-300" x-text="Math.round((item.estimated_probability || 0) * 100) + '%'"></div>
                                <div class="text-xs text-slate-500" x-text="item.confidence || ''"></div>
                            </div>
                        </div>
                    </div>
                </template>
                <p x-show="!(researchResult?.top5 || []).length" class="text-sm text-slate-500">Nenhum TOP 5 gerado.</p>
            </div>

            <div class="mt-6 mb-3">
                <h3 class="text-lg font-semibold">Melhores Estatísticas</h3>
                <p class="text-xs text-slate-500">Research Agent · Não calibrado</p>
            </div>
            <div class="space-y-4">
                <template x-for="(game, gi) in researchResult?.best_games || []" :key="gi">
                    <div class="rounded-2xl border border-slate-800 bg-slate-950 p-4">
                        <div class="mb-3 flex items-start justify-between gap-3">
                            <div>
                                <div class="text-base font-semibold" x-text="game.home + ' x ' + game.away"></div>
                                <div class="text-xs text-slate-400" x-text="game.competition || ''"></div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs uppercase text-slate-500">Eventos</div>
                                <div class="text-lg font-bold text-amber-300" x-text="(game.events || []).length"></div>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <template x-for="(ev, ei) in game.events || []" :key="ei">
                                <div class="flex items-center justify-between rounded-xl bg-slate-900 px-3 py-2 text-sm">
                                    <span x-text="ev.label || ev.event_type"></span>
                                    <span class="font-semibold text-emerald-300" x-text="Math.round((ev.estimated_probability || 0) * 100) + '%'"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
                <p x-show="!(researchResult?.best_games || []).length" class="text-sm text-slate-500">Nenhum jogo forte gerado.</p>
            </div>

            <div class="mt-6 mb-3">
                <h3 class="text-lg font-semibold">Ranking dos Melhores Jogos</h3>
                <p class="text-xs text-slate-500">Research Agent · Não calibrado</p>
            </div>
            <div class="space-y-3">
                <template x-for="(r, ri) in researchResult?.ranking || []" :key="ri">
                    <div class="rounded-xl border border-slate-800 bg-slate-950 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-semibold" x-text="(ri + 1) + '. ' + r.home + ' x ' + r.away"></div>
                                <div class="text-xs text-slate-400" x-text="r.competition || ''"></div>
                                <div class="mt-1 text-sm text-slate-300" x-text="r.summary || ''"></div>
                            </div>
                            <div class="text-right text-sm" x-text="rankingStrength(r.strength)"></div>
                        </div>
                    </div>
                </template>
                <p x-show="!(researchResult?.ranking || []).length" class="text-sm text-slate-500">Nenhum ranking gerado.</p>
            </div>

            <div x-show="researchDebug" class="mt-6 rounded-xl border border-slate-800 bg-slate-950 px-4 py-3 text-xs text-slate-400">
                <span class="font-medium text-slate-300">Debug:</span>
                <span x-text="'input ' + (researchDebug?.fixtures_input || 0) + ' · analisados ' + (researchDebug?.fixtures_analyzed || 0) + ' · rejeitados ' + (researchDebug?.fixtures_rejected || 0) + ' · calls ' + (researchDebug?.openrouter_calls || 0) + ' · tokens ' + (researchDebug?.tokens || 0) + ' · US$ ' + (researchDebug?.cost_usd || '0')"></span>
            </div>
        </section>

        <section class="mb-4 rounded-2xl border border-slate-800 bg-slate-900 p-4 shadow-lg">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Top 3 FAS (Engine Determinístico)</h2>
                <span class="text-xs text-slate-400">{{ $rankingRun ? $rankingRun->analysis_date->format('d/m/Y') : $date }}</span>
            </div>
            <div class="space-y-3">
                @forelse($dashboardSections['top3'] as $item)
                    <div class="rounded-xl border border-slate-800 bg-slate-950 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-semibold">{{ $item['home_team'] }} x {{ $item['away_team'] }}</div>
                                <div class="text-xs text-slate-400">{{ $item['competition'] }} · {{ $item['event_type'] }}</div>
                            </div>
                            <div class="text-right text-sm text-emerald-300">{{ number_format($item['candidate_score'], 2) }}</div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Nenhum TOP 3 disponível para esta data.</p>
                @endforelse
            </div>
        </section>

        <section class="mb-4 rounded-2xl border border-slate-800 bg-slate-900 p-4 shadow-lg">
            <h2 class="mb-4 text-lg font-semibold">Top 5 (Engine Determinístico)</h2>
            <div class="space-y-3">
                @forelse($dashboardSections['top5'] as $item)
                    <div class="rounded-xl border border-slate-800 bg-slate-950 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-semibold">{{ $item['home_team'] }} x {{ $item['away_team'] }}</div>
                                <div class="text-xs text-slate-400">{{ $item['competition'] }} · {{ $item['event_type'] }}</div>
                            </div>
                            <div class="text-right text-sm text-indigo-300">{{ number_format($item['candidate_score'], 2) }}</div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Nenhum TOP 5 disponível para esta data.</p>
                @endforelse
            </div>
        </section>

        <section class="mb-4 rounded-2xl border border-slate-800 bg-slate-900 p-4 shadow-lg">
            <h2 class="mb-4 text-lg font-semibold">Melhores Estatísticas (Engine Determinístico)</h2>
            <div class="space-y-4">
                @forelse($dashboardSections['best_games'] as $game)
                    <div class="rounded-2xl border border-slate-800 bg-slate-950 p-4">
                        <div class="mb-3 flex items-start justify-between gap-3">
                            <div>
                                <div class="text-base font-semibold">{{ $game['home_team'] }} x {{ $game['away_team'] }}</div>
                                <div class="text-xs text-slate-400">{{ $game['competition'] }} · {{ $game['kickoff'] }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs uppercase text-slate-500">Eventos</div>
                                <div class="text-lg font-bold text-amber-300">{{ count($game['items']) }}</div>
                            </div>
                        </div>
                        <div class="space-y-2">
                            @foreach($game['items'] as $item)
                                <div class="flex items-center justify-between rounded-xl bg-slate-900 px-3 py-2 text-sm">
                                    <span>{{ $item['event_type'] }}</span>
                                    <span class="font-semibold text-emerald-300">{{ number_format($item['adjusted_probability'] * 100, 0) }}%</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Nenhum jogo forte disponível nesta data.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-2xl border border-slate-800 bg-slate-900 p-4 shadow-lg">
            <h2 class="mb-4 text-lg font-semibold">Confere</h2>
            @if($canAudit)
                <p class="text-sm text-slate-400">Existe análise salva para esta data.</p>
            @else
                <p class="text-sm text-slate-500">Nenhuma análise foi gerada para esta data.</p>
            @endif
        </section>
    </main>

    <script>
        function fasDashboard() {
            return {
                selectedDate: '{{ $date }}',
                canGenerate: @json($canGenerate),
                canAudit: @json($canAudit),
                isRunning: false,
                activeRunType: null,
                runId: null,
                progress: 0,
                statusText: '{{ $statusText }}',
                message: '',
                error: '',
                summary: null,
                pollingInterval: null,
                gameDay: @json($gameDayDiscovery ?? null),
                researchResult: @json($researchResult ?? null),
                researchDebug: @json($researchDebug ?? null),

                get hasDiscoveredGames() {
                    return (this.gameDay?.selected_count || 0) > 0;
                },

                searchGameDay() {
                    this.resetState();
                    this.statusText = 'Buscando jogos do dia...';
                    this.isRunning = true;

                    fetch('/gameday/search', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ date: this.selectedDate })
                    })
                    .then(async res => {
                        const raw = await res.text();
                        let body = null;
                        try {
                            body = raw ? JSON.parse(raw) : null;
                        } catch (e) {
                            body = { raw };
                        }
                        return { status: res.status, body };
                    })
                    .then(res => {
                        this.isRunning = false;
                        if (res.status >= 400) {
                            this.error = res.body?.error || res.body?.raw || 'Erro ao buscar jogos.';
                            this.statusText = 'Erro';
                            return;
                        }

                        this.gameDay = res.body.result;
                        this.message = res.body.message || 'Busca concluída.';
                        this.statusText = 'Descoberta concluída.';
                    })
                    .catch(() => {
                        this.isRunning = false;
                        this.error = 'Falha de rede ao buscar jogos.';
                        this.statusText = 'Erro de rede';
                    });
                },

                runResearch() {
                    this.resetState();
                    this.statusText = 'Analisando jogos com agente...';
                    this.isRunning = true;

                    fetch('/fas/research/run', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ date: this.selectedDate })
                    })
                    .then(async res => {
                        const raw = await res.text();
                        let body = null;
                        try {
                            body = raw ? JSON.parse(raw) : null;
                        } catch (e) {
                            body = { raw };
                        }
                        return { status: res.status, body };
                    })
                    .then(res => {
                        this.isRunning = false;
                        if (res.status >= 400) {
                            this.error = res.body?.error || res.body?.raw || 'Erro na análise FAS.';
                            this.statusText = 'Erro';
                            return;
                        }

                        this.researchResult = res.body.result;
                        this.researchDebug = res.body.debug;
                        this.message = res.body.message || 'Análise concluída.';
                        this.statusText = 'Análise concluída.';
                    })
                    .catch(() => {
                        this.isRunning = false;
                        this.error = 'Falha de rede na análise FAS.';
                        this.statusText = 'Erro de rede';
                    });
                },

                runDaily() {
                    this.startRun('/fas/executions/daily', 'DAILY_ANALYSIS', 'Pesquisando jogos...');
                },

                runAudit() {
                    this.startRun('/fas/executions/audit', 'RESULT_AUDIT', 'Conferindo análise...');
                },

                startRun(url, type, text) {
                    this.resetState();
                    this.activeRunType = type;
                    this.statusText = text;
                    this.isRunning = true;

                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ date: this.selectedDate })
                    })
                    .then(async res => {
                        const raw = await res.text();
                        let body = null;
                        try {
                            body = raw ? JSON.parse(raw) : null;
                        } catch (e) {
                            body = { raw };
                        }
                        return { status: res.status, body };
                    })
                    .then(res => {
                        if (res.status >= 400) {
                            this.error = res.body?.error || res.body?.message || res.body?.raw || 'Erro ao iniciar execução.';
                            this.isRunning = false;
                            return;
                        }

                        this.message = res.body.message;
                        this.runId = res.body.run_id;
                        this.startPolling();
                    })
                    .catch(() => {
                        this.error = 'Falha de rede ao iniciar.';
                        this.isRunning = false;
                    });
                },

                startPolling() {
                    if (this.pollingInterval) clearInterval(this.pollingInterval);

                    this.pollingInterval = setInterval(() => {
                        fetch(`/fas/executions/${this.runId}/status`)
                            .then(res => res.json())
                            .then(data => {
                                this.progress = data.progress;
                                this.statusText = data.current_step || 'Aguardando';

                                if (data.status === 'COMPLETED' || data.status === 'FAILED' || data.status === 'PARTIAL') {
                                    clearInterval(this.pollingInterval);
                                    this.isRunning = false;
                                    this.summary = data.summary;

                                    if (data.status === 'COMPLETED') {
                                        this.message = 'Execução finalizada.';
                                    } else if (data.status === 'PARTIAL') {
                                        this.message = data.summary?.notice || 'Execução finalizada com aviso.';
                                    } else {
                                        this.error = 'Execução finalizada com erros.';
                                        if (data.errors && data.errors.length > 0) {
                                            this.error += ' - ' + data.errors[0].message;
                                        }
                                    }
                                }
                            });
                    }, 2000);
                },

                top3Team(item) {
                    const game = (this.researchResult?.games || []).find(g => String(g.fixture_id) === String(item.fixture_id));
                    return game ? (game.home + ' x ' + game.away) : ('Fixture ' + item.fixture_id);
                },

                top3Event(item) {
                    const game = (this.researchResult?.games || []).find(g => String(g.fixture_id) === String(item.fixture_id));
                    return (game?.competition || '') + ' · ' + (item.label || item.event_type);
                },

                top3Label(item) {
                    const game = (this.researchResult?.games || []).find(g => String(g.fixture_id) === String(item.fixture_id));
                    return game ? (game.home + ' x ' + game.away) : ('Fixture ' + item.fixture_id);
                },

                rankingStrength(strength) {
                    const map = { 'VERY_HIGH': '🔥 Muito forte', 'HIGH': '🟢 Forte', 'MEDIUM': '🟡 Médio', 'LOW': '⚪ Fraco' };
                    return map[strength] || strength || '';
                },

                resetState() {
                    this.message = '';
                    this.error = '';
                    this.summary = null;
                    this.progress = 0;
                }
            }
        }
    </script>
</body>
</html>