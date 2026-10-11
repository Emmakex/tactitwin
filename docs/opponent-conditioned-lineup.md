# Opponent-conditioned Lineup Matchup

## Purpose

v0.8 conditions the existing Lineup Lab comparison on the specific opponent rather than treating lineup deltas as universally desirable.

The layer answers a narrower question:

> Between the two supplied XI variants, which one better matches the demands already detected for this opponent, and where is the evidence strong enough to support that view?

It does **not** claim to identify the globally optimal XI or predict the result of the match.

## Evidence reused

The evaluator does not create a new hidden model. It reuses existing auditable TactiTwin layers:

- team and opponent recent profiles
- full matchup scenario scan
- individual lineup-profile deltas
- nominal formation structural deltas
- global shared-pitch unit-network deltas
- phase-conditioned unit-network deltas
- lineup and pair evidence coverage

The same team/opponent profile bundle is reused for game-state context, matchup scenarios and opponent-conditioned lineup evaluation to avoid unnecessary repeated profile computation.

## Opponent-conditioned priorities

### Build-up resilience against pressure

Opponent demand: `build-up-vs-high-press` risk score.

Relative XI evidence: candidate-minus-baseline build-up phase interaction delta.

Material phase threshold: ±1.5.

### Territorial progression

Opponent demand: 75% of the larger of the high-press and low-block risk scores.

This is intentionally a demand heuristic rather than a new tactical truth: both an aggressive press and a compact deep defence can increase the importance of reliable territorial progression.

Relative XI evidence: candidate-minus-baseline progression phase interaction delta.

Material phase threshold: ±0.75.

### Final-third creation against a compact defence

Opponent demand: `attack-vs-low-block` risk score.

Relative XI evidence: candidate-minus-baseline final-third phase interaction delta.

Material phase threshold: ±0.08.

### Defensive transition security after loss

Opponent demand: `defensive-transition-after-loss` risk score.

Relative XI evidence is resolved by the direction of three independent signals rather than combining their incompatible numeric scales:

- global unit defensive-sequence delta, threshold ±0.35
- formation rest-defence delta, threshold ±8
- individual-profile ball-security delta, threshold ±0.75

The variant supported by more material directions receives the relative edge for this priority; a tie stays neutral.

### High-press opportunity against opponent build-up

Opponent demand is a transparent vulnerability heuristic:

- 60% inverse opponent build-up escape rate
- 40% normalized opponent own-third turnovers per match, with 6 per match as the reference ceiling

Relative XI evidence: candidate-minus-baseline high-press phase interaction delta.

Material phase threshold: ±0.35.

## Demand levels

- high: score >= 65
- medium: score >= 45
- low: score < 45

Only medium/high priorities can influence the lineup lean.

## Relative decision rule

There is no absolute lineup score.

For medium/high priorities:

- candidate edge: candidate receives 2 votes for a high-priority demand or 1 for medium
- baseline edge: baseline receives the same weighting
- neutral: no votes

A lineup lean is asserted only when one variant leads by at least 2 weighted votes.

Otherwise the result is `inconclusive`.

## Coverage gate

A lineup lean is blocked entirely when evidence coverage is low.

Coverage becomes low when any of the following is true:

- matchup scan coverage is low
- individual lineup-profile coverage is low
- more than 25% of possible candidate phase-network pairs are under-observed

Coverage is medium when one of the first two layers is medium or more than 15% of candidate pairs are under-observed.

With low coverage, priority evidence is still returned but votes are set to zero and no variant is preferred.

## API/UI integration

No separate endpoint is required for the initial version. `POST /analysis/lineup-what-if` already receives both the team and opponent and now returns:

- `unit_interactions`
- `phase_unit_interactions`
- `opponent_conditioned_matchup`

Opponent-conditioned recommendations are appended to the existing Lineup Lab recommendation stream while the underlying evidence layers remain separate.

## Method boundary

The output is relative decision support between the two supplied lineups. It is not a match forecast and not an optimizer over every possible squad combination.

Without tracking/video, TactiTwin cannot verify spacing, line distances, synchronized rotations, cover shadows or actual block geometry. Historical pair interactions and opponent tendencies also do not prove causal performance in the future match.
