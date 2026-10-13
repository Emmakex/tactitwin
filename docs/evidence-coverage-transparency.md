# v1.35 — Evidence Coverage Transparency

## Goal

Make the evidence volume behind matchup-derived tactical outputs auditable without changing any tactical score, recommendation, response ranking or Plan A/B/C state.

The first real WordPress test exposed an important UX ambiguity: an effective coverage value of `1` did not tell staff whether both teams contributed one match or whether the comparison was asymmetric, for example `5 vs 1`.

v1.35 keeps the conservative effective-coverage rule and makes the two source samples explicit.

## Coverage contract

For every Full Matchup Scan, TactiTwin now exposes:

- `team_matches`
- `opponent_matches`
- `effective_matches`
- `team_sample`
  - team source ID and name
  - exact match IDs
  - oldest and newest sample date
  - sample-size confidence label
- `opponent_sample`
  - the same fields for the rival
- `limiting_side`
  - `team`
  - `opponent`
  - `balanced`
- `asymmetry_matches`
- `is_asymmetric`
- a bilingual limitation note
- an explicit low-coverage warning when effective coverage is below three matches

The effective coverage formula remains:

`effective_matches = min(team_matches, opponent_matches)`

TactiTwin never fills the smaller side with invented matches and never averages sample sizes in a way that hides the weaker evidence base.

## Match Plan

Fresh Match Plan admin results are enriched through the existing per-user transient before rendering. The enrichment reuses the data-preparation result and the normalized match table to expose exact sample IDs and date windows.

The enrichment is descriptive only. It does not change:

- Match Plan summary scores
- scenario priorities
- phase instructions
- decision triggers
- historical precedent logic

## Tactical Twin Lab

Twin Lab now preserves the complete Full Matchup Scan coverage contract at the Lab level and inside its embedded Tactical Response Matrix.

Response fit, branch risk/opportunity, robustness ranking and response-switch candidates are unchanged.

## WordPress presentation

Match Plan and Twin Lab receive a coach-facing coverage panel showing:

- our-team sample size and date range
- opponent sample size and date range
- effective coverage
- limiting side
- exact match IDs inside an expandable evidence section

This panel is deliberately separate from tactical scores.

## Method boundaries

Coverage is an evidence-volume descriptor, not:

- a probability of winning
- a confidence percentage for a tactical recommendation
- a causal-effect estimate
- a correction factor applied to tactical scores

Low coverage requires stronger staff validation of the tactical hypothesis; it does not automatically suppress or rescale the existing scenario outputs.

## Exit criterion

For one asymmetric matchup sample such as `our team = 5 matches` and `opponent = 1 match`, Full Matchup Scan, Match Plan admin output and Tactical Twin Lab must expose the two source sample counts, effective coverage `1`, the opponent as the limiting side, exact sample IDs and available date windows. ES/EN must preserve identical numeric/sample identity. No tactical score, recommendation, ranking, branch output or Plan A/B/C state may change as a consequence of this layer. Dedicated regressions must prove source immutability and unchanged Match Plan tactical output.
