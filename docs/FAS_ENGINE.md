# FAS Engine V1 - Mathematical & Statistical Core

## Introdução
O FAS Engine V1 é o orquestrador analítico que processa o `MatchDataset` (criado pelo Dataset Builder) e emite predições probabilísticas rigorosamente formatadas em um objeto `EventPrediction`.

## Princípios
1. **Determinismo:** O mesmo Dataset + a mesma Engine Version sempre geram exatamente a mesma predição.
2. **Prior & Regressão:** Taxas não são probabilidades. Um time com `5/5` over 1.5 não tem 100% de chance no futuro. O motor utiliza uma Regressão Bayesiana (Smoothed/Beta-Binomial pseudo-prior) para puxar amostras pequenas de volta à média (o "Prior").
3. **Priors Configuráveis:** Os priors e a "força" do prior (`prior_strengths`) estão localizados centralmente no `config/fas.php`.
4. **V1 é "Uncalibrated":** O motor gera estimativas de força estatística, mas só podem ser chamadas de "Probabilidade de Acontecer" após extensivos ciclos de backtesting e calibração de log-loss (Etapas futuras).

## Arquitetura de Calculadoras
Em vez de cada engine duplicar lógicas complexas, eles injetam 5 serviços-core:
- `ProbabilityRegressor`: Cuida do achatamento matemático das taxas usando o prior.
- `SampleStrengthCalculator`: Calcula o *Effective Sample Size* (para não somar erroneamente last5 + last10 = 15).
- `AgreementCalculator`: Usa o desvio padrão das variâncias para pontuar o quão concordantes são os recortes Home/Away/Overall (0 a 100).
- `ConfidenceCalculator`: Funde Data Quality (40%), Sample Strength (30%) e Agreement (30%), aplicando penalidades para atributos faltantes, emitindo `VERY_LOW` a `VERY_HIGH`.
- `FasScoreCalculator`: Avalia a recomendação final de 0 a 100, favorecendo altas probabilidades sustentadas por altas confianças.

## Motores de Evento
Os motores ficam em `App\Services\Fas\Engines\`:
- **ResultEngine:** Modelo único que projeta Home, Draw e Away de uma vez para garantir soma exata de 1.00.
- **Over15Engine / Over25Engine:** Independentes. Avaliam overall stats, home/away splits e gols absolutos médios.
- **FirstHalfGoalEngine:** Restrito a recortes de gols de primeiro tempo.
- **BttsEngine:** Avalia histórico de ambas marcam.
- **CornersEngine / CardsEngine:** Para V1, mapeiam médias gerais contra as linhas tradicionais, punindo mais agressivamente com o status `INSUFFICIENT` se a amostragem for rasa.

## Fatores e Missing Data
Cada `EventPrediction` obriga o registro de fatores (em texto claro legível). Assim, o Debug do FAS sempre explicará *por que* ele acredita em um número. Se dados como Escanteios simplesmente não existem na API, a predição é descartada com `INSUFFICIENT` ou continua apenas com `negative_factors` apontando a ausência e derrubando o Data Quality/Confidence.
