# Game State / What-if

## Purpose

TactiTwin's Game State / What-if module adapts the tactical plan to a hypothetical match minute and exact scoreline while grounding the response in historically observed event data.

Example: minute 80, our team leads 1-0.

The exact scoreline is kept as scenario context. Historical conditioning is currently performed at the broader score-state level:

- leading
- drawing
- trailing

For the opponent, TactiTwin uses the reciprocal state. If we are leading, the opponent is evaluated through its historical trailing-state behaviour.

## Historical state reconstruction

The event stream is processed chronologically. Goals update an internal score timeline, and elapsed seconds are assigned to both teams' current score state. Penalty shoot-outs are excluded from tactical game-state time.

For each state TactiTwin records:

- observed minutes
- matches with exposure to the state
- pass completion
- shots and xG
- goals
- progressive actions
- final-third entries
- box entries
- pressures and high pressures
- recoveries and high recoveries

Activity metrics are normalized per 90 minutes of exposure to make states with different durations comparable.

## What-if output

The engine returns:

- hypothetical minute and exact scoreline
- derived team and opponent score states
- evidence coverage in relevant states
- our historical attacking-intensity index
- opponent reciprocal-state attacking-intensity index
- comparison against each team's overall baseline
- state-adjusted tactical recommendations
- observable decision triggers
- the highest-risk and best-opportunity scenarios from the existing Full Matchup Scan

The risk/opportunity intensity indexes are decision-support heuristics, not probabilities of scoring or conceding.

## Current limitations

This version does not yet condition on:

- exact historical scoreline (1-0 vs 2-0, etc.)
- red/yellow cards
- substitutions or personnel changes
- home/away split
- exact opponent-specific historical game state
- tracking-derived line height, compactness or inter-line distances

These are planned as additional conditioning dimensions once data coverage is sufficient.
