# API-Football v3 Integration

Este documento descreve a integração com a API-Football v3 desenvolvida na Etapa 2 do FAS.

## Configuração

As configurações ficam no arquivo `.env`:

```env
API_FOOTBALL_KEY=sua_chave_aqui
API_FOOTBALL_BASE_URL=https://v3.football.api-sports.io
API_FOOTBALL_TIMEOUT=15
API_FOOTBALL_CACHE_ENABLED=true
```

Estas chaves são mapeadas para o `config/api-football.php`.

## Endpoints Utilizados
A integração atual prepara e consome (através de comandos Artisan) os seguintes endpoints:
- `/leagues`: Sincroniza competições e temporadas, incluindo a informação de coverage.
- `/fixtures`: Sincroniza jogos (fixtures) por data.
- `/teams`: Importados diretamente através dos dados de `/fixtures`.
- Preparado na interface para futuras consultas: `/standings`, `/fixtures/statistics`, `/predictions`.

## Estratégia de Cache e Consumo
Foi criada a tabela `api_caches` e o serviço `ApiFootballCacheService`.
- **TTL**: Fixtures do dia atual tem cache de 15 minutos. Competitions tem cache de 24h.
- O controle global de limites (rate limits) e exceptions são geridos pelo `ApiFootballClient`.
- Todos os requests são registrados na tabela `api_request_logs` contendo informações de endpoint, cache hits e cache misses.

## Comandos Artisan
Foram disponibilizados três comandos principais para controle da API:
- `php artisan fas:api-status`: Verifica se a chave está funcional, mostra o plano, o limite de requests e hit-rate de cache.
- `php artisan fas:sync-competitions --season=2026`: Sincroniza ligas para a temporada informada. Mapeia o array de coverage para a tabela `competition_seasons`.
- `php artisan fas:sync-fixtures {date}`: Busca e importa jogos e times para uma data (YYYY-MM-DD), classificando em `ELIGIBLE` ou `EXCLUDED`.

## Tratamento de Erros e Limites
Criamos exceções específicas:
- `ApiFootballException`
- `ApiFootballRateLimitException`
- `ApiFootballAuthenticationException`
Em caso de falha na API, o dashboard continua funcionando com os dados locais ou em cache, evitando que a aplicação quebre para os usuários.

## Coverage (Cobertura de Dados)
Diferentes ligas fornecem diferentes dados. Mapeamos a disponibilidade na tabela `competition_seasons` no campo `coverage` (JSON). A interface lê esses dados para indicar com um badge se uma partida deverá ter `Statistics`, `Lineups`, etc.
