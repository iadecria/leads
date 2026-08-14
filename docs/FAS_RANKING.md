# FAS Ranking Engine

O Ranking Engine do FAS (Etapa 5) é responsável por receber as predições de um dia, filtrá-las e construir uma recomendação oficial dividida em:

- **TOP 3:** Os eventos de maior grau de confiança e rigor estatístico.
- **TOP 5:** Contém o TOP 3 além de mais 2 candidatos fortes.
- **WATCHLIST (Em Observação):** Eventos que não passaram no corte severo, ou por usarem modelos experimentais, ou sofrerem deméritos (ex: falta de dados arbitrais), ou por serem "a segunda melhor escolha" de uma partida (que já possui representante no TOP).

## Eligibility

A elegibilidade é feita através de cortes:
1. **Fixture Eligibility:** Partidas canceladas, não elegíveis (`fas_enabled = false`), de amistosos ou competições barradas são descartadas.
2. **Pre-kickoff:** Um ranking snapshot criado não aceita partidas iniciadas.
3. **Data Requirements:** Dados insuficientes na amostra forçam a exclusão do evento.

## Deduplication Strategy

Não é permitido mais que **1** evento da mesma partida (fixture) ocupando o TOP 3. 
Caso existam vários candidatos fortes de um mesmo jogo (ex: Over 1.5 e BTTS com 90%+), o Ranking ordenará as opções baseando-se no `CandidateScore` e *Tie-Breakers* rigorosos.
O evento perdedor desta disputa vai para a **WATCHLIST**, anotado com `SECOND_EVENT_SAME_FIXTURE`.

## Penalties & Deductions

Configuráveis em `config/fas.php`. 
O Candidate Score possui range 0-100, mas sofre deduções estáticas dependendo das fraquezas observadas:
- `missing_feature`: Faltas de dados extras deduzem da confidence global e score.
- `low_sample`: Amostragens perigosas (mas suficientes para não cair no INSUFFICIENT).
- `cards_without_referee`: Cartões sem histórico de árbitro (por enquanto) causam forte penalidade e expurgam o evento do TOP oficial.

## Imutabilidade (Snapshots)

Toda vez que a `FasRankingEngine` processa os dados com êxito para o dia, um `FasRankingRun` é persistido. Este Run possui:
- O minuto de criação (cutoff limit).
- Um *Snapshot* idêntico em JSON de toda a `config('fas.ranking')` existente.

Isso garante que um ranking histórico visualizado meses depois mostre exatamente como ele foi montado, sob quais réguas e tolerâncias.
