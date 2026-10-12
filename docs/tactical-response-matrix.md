# v1.27 — Tactical Response Matrix

## Goal

v1.27 is the first explicit step from a single Match Plan toward a practical tactical twin.

Instead of asking TactiTwin to predict a final score, the Tactical Response Matrix asks a narrower and more useful coaching question:

> Given the same current matchup evidence, how would different tactical response packages distribute their attention, and what would each package deliberately de-emphasise?

The matrix is deterministic and explainable. It composes the existing Full Matchup Scan; it does not replace the underlying scenario engines, rewrite their scores or claim causal effectiveness.

## Source evidence

The matrix requires one valid `full-matchup-scan` containing the five current scenario dimensions:

- Build-up vs High Press
- Opponent Side Bias
- Defensive Transition After Loss
- Attack vs Low Block
- Exploit Opponent Weak Side

For every scenario it reuses the same Match Plan priority expression:

`scenario_priority = 0.60 * risk + 0.40 * opportunity`

No scenario risk score, opportunity score, recommendation or evidence payload is modified.

## Response packages

v1.27 compares four fixed response packages. Their weights represent tactical emphasis, not probabilities.

| Response | High press | Side bias | Transition | Low block | Weak side |
| --- | ---: | ---: | ---: | ---: | ---: |
| Balanced Control | 25% | 15% | 25% | 15% | 20% |
| Press-Resistant Build-up | 45% | 10% | 20% | 10% | 15% |
| Transition Security | 15% | 15% | 45% | 10% | 15% |
| Width & Switch | 10% | 15% | 10% | 30% | 35% |

Every response profile sums to 100%.

The response fit index is:

`response_fit = sum(response_weight * scenario_priority)`

A larger fit value means that the response package places more emphasis on the risks/opportunities that are currently more prominent in the Full Matchup Scan. It does **not** mean that the response has a higher probability of winning, scoring or succeeding.

## Explainability

Each response exposes:

- response ID and bilingual name;
- deterministic fit score and rank;
- gap to the current top-fit response;
- per-scenario weights and contributions;
- dominant driver;
- up to two existing primary scenario instructions;
- explicit trade-offs when a high-priority matchup issue receives limited weight;
- coverage inherited from the source Full Matchup Scan.

Trade-offs are deliberately visible. A response package should not look universally superior simply because its top-line fit score is slightly higher.

## REST

Authenticated WordPress administrators can request:

`GET /wp-json/tactitwin/v1/planning/tactical-response-matrix`

Parameters:

- `team_id` — required;
- `opponent_id` — required;
- `limit` — 1–10 imported matches per team profile, default 5;
- `lang` — `en` or `es`.

## Method boundaries

v1.27 explicitly does not:

- predict a match result or scoreline;
- estimate win/draw/loss probability;
- estimate expected goals created by choosing a response;
- claim causal tactical effectiveness;
- change the five existing tactical scenario scores;
- change existing scenario recommendations;
- activate Plan A, Plan B or Plan C;
- mutate the source Full Matchup Scan;
- use response fit as a replacement for staff judgement.

The matrix is a comparative decision-support layer over existing evidence.

## Regression guarantees

The v1.27 regression test verifies that:

- all four response packages are returned;
- the declared fit formula is deterministic;
- ES/EN presentation produces identical numeric fit values and ranking;
- source scan data is not mutated;
- existing recommendations are reused rather than regenerated;
- high-priority underweighted dimensions are surfaced as trade-offs;
- invalid or incomplete Full Matchup Scan input is rejected;
- the output explicitly declares that fit is not an outcome probability or scoreline prediction.

## Next direction

The matrix establishes the comparison contract needed for later tactical-twin work. Future layers can vary personnel, game state, opponent behaviour or response assumptions around the same auditable interface, while keeping observed evidence, simulated assumptions and staff decisions clearly separated.
