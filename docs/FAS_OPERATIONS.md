# FAS Operations & Pipeline Orchestration

Este documento descreve como a orquestração operacional e o uso diário do sistema foram modelados para funcionar com segurança, alta resiliência a falhas e sem riscos de encavalamento de execuções.

## Arquitetura de Orquestração

Para transformar os processos matemáticos de back-end em uma operação em lote ("One Click"), criamos a estrutura **Orchestrator**, composta por:
1. `FasDailyOrchestrator`: Executa as rotinas diárias antes dos jogos.
2. `FasResultOrchestrator`: Executa as auditorias diárias após os jogos.

### Comandos CLI
Existem dois comandos principais que podem ser inseridos no Cron ou chamados manualmente via SSH:
- `php artisan fas:run {date}` (Executa a rotina `DAILY_ANALYSIS`)
- `php artisan fas:check {date}` (Executa a rotina `RESULT_AUDIT`)

### Resiliência (Retries e Falhas Parciais)
O model `FasExecutionRun` possui uma coluna `status` e várias colunas de rastreamento (`current_step`, `fixtures_status`, etc.).
Se uma rotina de *Dataset* estourar timeout da API:
1. O banco salva o status do passo de *Fixtures* como `COMPLETED`.
2. O passo de *Datasets* falha (`FAILED`), e a execução encerra precocemente gravando o erro em JSON.
3. Quando o usuário clica em "Tentar Novamente" (ou roda o comando novamente), as fixtures **são puladas (Skipped)** e a execução recomeça instantaneamente de onde parou.

### Concurrency Lock em Banco de Dados
Para tornar o FAS compatível com provedores que não possuem instâncias separadas de Redis, o controle de lock e concorrência é feito diretamente no banco (verificação de status `RUNNING` para a mesma data e `execution_type`). Uma tentativa dupla no mesmo momento será rejeitada no Controller.

## Fluxo Diário Sugerido (One-Click Flow)

**Começo do dia (ex: 08:00 AM)**:
1. Acesse o Dashboard.
2. Selecione a data de hoje.
3. Clique em `EXECUTAR FAS`.
4. Uma progress bar exibirá o andamento. Após 100%, os rankings estarão prontos para consumo do usuário.

**Final do dia / Dia seguinte (ex: 23:50 PM ou 08:00 AM do dia seguinte)**:
1. Acesse o Dashboard.
2. Selecione a data de ontem (ou espere todos os jogos de hoje acabarem).
3. Clique em `✅ CONFERIR RESULTADOS`.
4. O FAS sincroniza todos os placares, avalia as apostas recomendadas no banco de dados e calcula Performance Metrics permanentemente.
