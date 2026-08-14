# Match Dataset Builder

Este documento descreve a Etapa 3 do FAS: a camada de *Features*.

## Objetivo
Transformar dados crus da API-Football (Fixtures, Statistics, Events, Standings) em um dataset estatístico padronizado (`MatchDataset`) que servirá de entrada para o FAS Engine.

## Arquitetura
1. **MatchDatasetBuilder**: Orquestra a busca de histórico, respeitando a regra de *Data Leakage*.
2. **Calculators**: Isola a responsabilidade de cálculo (ex: `StatsCalculator`, `DataQualityCalculator`).
3. **DTOs**: `MatchDataset`, `TeamStats`, `MetricValue` organizam e encapsulam as features em um modelo orientado a objeto que é posteriormente serializado para JSON.
4. **Armazenamento**: Snapshots são salvos em `match_datasets`. Estatísticas cruas e eventos podem ser armazenados em `fixture_statistics` e `fixture_events` para economizar requests à API.

## Prevenção de Data Leakage
A proteção de *Data Leakage* é implementada na base do builder.
Ao chamar `$builder->build($fixture)`, o sistema utiliza `fixture_date` como `$cutoffAt`. 
Qualquer fixture histórico das equipes analisadas é obrigatoriamente recuperado utilizando `where('fixture_date', '<', $cutoffAt)`.
Testes unitários garantem que resultados de eventos futuros ou paralelos não interfiram nos datasets anteriores.

## Data Quality Score
A qualidade de um dataset é pontuada de 0 a 100, classificada no enum `DataQualityLevel`:
- **EXCELLENT (90-100)**
- **HIGH (75-89)**
- **MEDIUM (60-74)**
- **LOW (40-59)**
- **INSUFFICIENT (0-39)**

A pontuação considera métricas como: tamanho da amostra histórica (geral e casa/fora), disponibilidade de estatísticas avançadas (statistics, lineups), etc.

## Snapshots Imutáveis
Os datasets são gerados atrelados a uma versão do builder (`config('fas.dataset_version')`, ex: "1.0.0"). 
Uma vez gerado o JSON, o snapshot é persistido na tabela `match_datasets`. Caso o dataset sofra alterações algorítmicas futuramente, uma nova versão será salva, mantendo os snapshots antigos inalterados para fins de backtesting e auditoria.

## Job & CLI
A geração do Dataset pode ser feita por job (`BuildMatchDatasetJob`) para processamento assíncrono em lote.
CLI: `php artisan fas:build-dataset {date} {--fixture=} {--force}`
