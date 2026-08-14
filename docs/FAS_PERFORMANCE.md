# FAS Performance Analytics

Este documento descreve a arquitetura da camada de performance (ETAPA 7).

## Objetivo

Fornecer métricas exatas e estritamente auditadas do sistema de inteligência do FAS, assegurando que não ocorra alteração na previsão histórica, mas propiciando aprendizado através da medição.

## 1. Métricas Principais

- **Total Predictions**: Todas as previsões no escopo analisado.
- **Audited Predictions**: Previsões cujo status final é `HIT` ou `MISS`.
- **Hit Rate**: `hits / audited_predictions`.

> [!IMPORTANT]
> `VOID`, `UNAVAILABLE` e `PENDING` jamais compõem o denominador do Hit Rate ou do Brier Score.

### O Brier Score

Medida de precisão de previsões probabilísticas binárias.

```math
BS = \frac{1}{N} \sum_{t=1}^{N} (f_t - o_t)^2
```
Onde $f_t$ é a probabilidade estimada (`estimated_probability`) e $o_t$ é o outcome real (`1` para `HIT`, `0` para `MISS`). O score varia de 0 (perfeito) a 1 (péssimo).

### O Calibration Gap

Mostra se as probabilidades do modelo superestimam ou subestimam os eventos no mundo real.
Calculado por: `Hit Rate - Average Probability`.
- **Gap Negativo**: O modelo está muito confiante (Overconfidence).
- **Gap Positivo**: O modelo está muito cauteloso (Underconfidence).

## 2. Componentes

- **`FasPerformanceService`**: Executa a busca base no `fas_audits` garantindo todos os joins (`fas_rankings`, `fas_events`, `fixtures`). Filtra por versões ativas.
- **Controllers e Views**: O `PerformanceController` expõe a visualização `/performance`. Dados JSON crus ficam em `/performance/data`.

## 3. Filtragem de Versões

Não combinamos performance de versões distintas do Engine ou do Dataset caso as atualizações tenham provocado disrupção na matemática. Use os parâmetros `engine_version` e `ranking_version` para isolar amostras.

## 4. Minimum Sample (Amostra Insuficiente)

Para evitar conclusões precipitadas (ex: Hit Rate 100% num "N" de 1 jogo), `config('fas.performance.minimum_sample')` deve ser respeitado antes de destacar uma métrica de competição como altamente atrativa.

---
**Camada apenas de Medição**. Alterações matemáticas e de regressão do motor não são parte desta etapa.
