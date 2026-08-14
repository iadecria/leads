<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAS - Dashboard Operacional</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-slate-900 text-slate-200">
    
    <nav class="bg-slate-800 p-4 shadow-lg mb-8">
        <div class="max-w-6xl mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold text-white tracking-wide">FAS <span class="text-indigo-400">Dashboard</span></h1>
            <div class="flex gap-4">
                <a href="/fas/executions" class="text-sm font-medium hover:text-indigo-400 transition">Histórico de Execuções</a>
                <a href="/performance" class="text-sm font-medium hover:text-indigo-400 transition">Performance</a>
            </div>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4" x-data="fasDashboard()">

        <!-- MENSAGENS -->
        <div x-show="message" class="mb-6 p-4 rounded bg-emerald-500/20 text-emerald-400 border border-emerald-500/30" x-text="message" style="display: none;"></div>
        <div x-show="error" class="mb-6 p-4 rounded bg-rose-500/20 text-rose-400 border border-rose-500/30" x-text="error" style="display: none;"></div>

        <!-- 1-CLICK OPERATIONS -->
        <div class="bg-slate-800 rounded-xl shadow-lg border border-slate-700 p-6 mb-8">
            <h2 class="text-lg font-semibold text-white mb-6 flex items-center gap-2">
                Orquestração de Rotina
            </h2>
            
            <div class="flex flex-col md:flex-row items-center gap-6">
                <!-- Seletor de Data -->
                <div class="w-full md:w-auto flex items-center gap-3">
                    <label class="text-sm font-medium text-slate-400">Data Alvo:</label>
                    <input type="date" x-model="selectedDate" class="bg-slate-900 border border-slate-600 rounded px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                </div>

                <div class="flex gap-4 w-full md:w-auto">
                    <!-- Botão Executar FAS -->
                    <button @click="runDaily" 
                            :disabled="isRunning"
                            class="flex-1 md:flex-none px-6 py-3 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold rounded-lg shadow transition flex justify-center items-center gap-2">
                        <span x-show="!isRunning || activeRunType !== 'DAILY_ANALYSIS'">▶ Executar FAS</span>
                        <span x-show="isRunning && activeRunType === 'DAILY_ANALYSIS'">Executando...</span>
                    </button>

                    <!-- Botão Conferir -->
                    <button @click="runAudit" 
                            :disabled="isRunning"
                            class="flex-1 md:flex-none px-6 py-3 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold rounded-lg shadow transition flex justify-center items-center gap-2">
                        <span x-show="!isRunning || activeRunType !== 'RESULT_AUDIT'">✅ Conferir Resultados</span>
                        <span x-show="isRunning && activeRunType === 'RESULT_AUDIT'">Auditando...</span>
                    </button>
                </div>
            </div>

            <!-- STATUS BOX -->
            <div x-show="isRunning" class="mt-8 p-6 bg-slate-900/50 rounded-lg border border-slate-700" style="display: none;">
                <div class="flex justify-between items-center mb-2">
                    <h3 class="font-medium text-white" x-text="statusText">Iniciando...</h3>
                    <span class="text-sm text-indigo-400" x-text="progress + '%'"></span>
                </div>
                
                <!-- Progress bar -->
                <div class="w-full bg-slate-800 rounded-full h-2.5 mb-4 border border-slate-700 overflow-hidden">
                    <div class="bg-indigo-500 h-2.5 rounded-full transition-all duration-500" :style="'width: ' + progress + '%'"></div>
                </div>

                <div class="text-sm text-slate-400">
                    <p><strong>Step atual:</strong> <span x-text="currentStep"></span></p>
                </div>
            </div>

            <!-- RESULT SUMMARY -->
            <div x-show="summary && !isRunning" class="mt-8 p-6 bg-slate-900/50 rounded-lg border border-slate-700" style="display: none;">
                <h3 class="font-medium text-white mb-4">Resumo da Execução</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <template x-for="(value, key) in summary" :key="key">
                        <div class="bg-slate-800 p-3 rounded border border-slate-700">
                            <div class="text-slate-400 text-xs mb-1 uppercase" x-text="key.replace('_', ' ')"></div>
                            <div class="text-lg font-semibold text-white" x-text="value"></div>
                        </div>
                    </template>
                </div>
                
                <div class="mt-6 flex gap-4" x-show="activeRunType === 'DAILY_ANALYSIS'">
                     <a href="/fas/executions" class="text-indigo-400 hover:text-indigo-300 text-sm font-medium">Ver Histórico e Rankings →</a>
                </div>
            </div>
            
        </div>

        <!-- LEGACY TOOLS (Avançado) -->
        <div class="bg-slate-800 rounded-xl shadow border border-slate-700 p-6 opacity-75">
            <h3 class="text-md font-medium text-slate-300 mb-4 border-b border-slate-700 pb-2">Ferramentas Avançadas (Manual)</h3>
            <div class="flex flex-wrap gap-3">
                <form action="/dashboard/sync" method="POST">
                    @csrf
                    <input type="hidden" name="date" :value="selectedDate">
                    <button class="px-3 py-1.5 text-xs bg-slate-700 hover:bg-slate-600 rounded text-slate-300 transition">Sincronizar Jogos</button>
                </form>
                <form action="/dashboard/datasets" method="POST">
                    @csrf
                    <input type="hidden" name="date" :value="selectedDate">
                    <button class="px-3 py-1.5 text-xs bg-slate-700 hover:bg-slate-600 rounded text-slate-300 transition">Gerar Datasets</button>
                </form>
            </div>
        </div>

    </main>

    <script>
        function fasDashboard() {
            return {
                selectedDate: new Date().toISOString().split('T')[0],
                isRunning: false,
                activeRunType: null,
                runId: null,
                progress: 0,
                statusText: '',
                currentStep: '',
                message: '',
                error: '',
                summary: null,
                pollingInterval: null,

                runDaily() {
                    this.startRun('/fas/executions/daily', 'DAILY_ANALYSIS', 'Executando FAS...');
                },

                runAudit() {
                    this.startRun('/fas/executions/audit', 'RESULT_AUDIT', 'Auditando Resultados...');
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
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ date: this.selectedDate })
                    })
                    .then(res => res.json().then(data => ({ status: res.status, body: data })))
                    .then(res => {
                        if (res.status >= 400) {
                            this.error = res.body.error || 'Erro ao iniciar execução.';
                            this.isRunning = false;
                        } else {
                            this.message = res.body.message;
                            this.runId = res.body.run_id;
                            this.startPolling();
                        }
                    })
                    .catch(err => {
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
                            this.currentStep = data.current_step || 'Iniciando';
                            
                            if (data.status === 'COMPLETED' || data.status === 'FAILED' || data.status === 'PARTIAL') {
                                clearInterval(this.pollingInterval);
                                this.isRunning = false;
                                this.summary = data.summary;
                                
                                if (data.status === 'COMPLETED') {
                                    this.statusText = 'Concluído com Sucesso!';
                                    this.message = 'Execução finalizada.';
                                } else {
                                    this.error = 'Execução finalizada com erros. Verifique o histórico.';
                                    if(data.errors && data.errors.length > 0) {
                                         this.error += ' - ' + data.errors[0].message;
                                    }
                                }
                            }
                        });
                    }, 2000); // poll every 2 seconds
                },

                resetState() {
                    this.message = '';
                    this.error = '';
                    this.summary = null;
                    this.progress = 0;
                    this.currentStep = '';
                }
            }
        }
    </script>
</body>
</html>
