# Personnel What-if

## Purpose

Personnel What-if compares a hypothetical substitution using each player's recent on-pitch event profile while retaining the current match context from TactiTwin's score-state and manpower What-if engines.

Example:

- minute 70
- trailing 0-1
- 11v11
- Player A out
- Player B in

The output explains what the recent data suggests we gain, what we may sacrifice and how different the incoming player's profile is from the outgoing player's profile.

## On-pitch reconstruction

For each imported match TactiTwin reconstructs player exposure from:

- `Starting XI` events
- `Substitution` events
- dismissals
- the match clock

A starter's interval begins at minute 0. A substitution closes the outgoing player's interval and opens the incoming player's interval. A dismissal closes the dismissed player's interval. Extra time is supported; penalty shoot-outs are excluded from match exposure.

## Player profile

Recent player profiles aggregate:

- minutes, appearances and starts
- passes and pass completion
- carries
- progressive pass/carry actions
- final-third entries
- box entries
- shots and xG
- pressures
- recoveries
- duels
- dispossessions/miscontrols as a simple turnover signal

Activity metrics are normalized per 90 minutes.

## Sample confidence

- high: at least 450 recent minutes
- medium: at least 180 recent minutes
- low: below 180 recent minutes

For a replacement comparison the effective sample is the smaller of the two player samples. Low-confidence comparisons remain visible but include an explicit warning.

## Replacement comparison

TactiTwin exposes raw incoming-minus-outgoing deltas and five interpretable dimensions:

- progression
- ball security
- attacking threat
- pressing activity
- defensive activity

It also returns a 0–100 **profile similarity** index. This index describes similarity between recent event profiles and primary position; it is not a causal compatibility score.

## Tactical interpretation

The engine surfaces the strongest gains and trade-offs. Examples:

- more progression but lower pass security
- more box threat but fewer defensive recoveries
- more pressing activity but a non-like-for-like role change
- similar statistical profile, suggesting the substitution is more about freshness or a specific instruction than a large measured shift

The Personnel What-if retains the current score/manpower hypothesis and top tactical scan context so the coaching staff can interpret the substitution inside the actual scenario being prepared.

## Current limitations

Personnel What-if is descriptive, not causal. It does not claim that replacing one player with another will cause the same team-level statistical change.

Event data also under-observes:

- off-ball runs
- marking quality
- spacing and compactness
- role discipline
- communication
- physical fatigue/load

Tracking, video-derived positions and physical data are required before those effects can be modeled responsibly.

## REST

Player profile:

`GET /wp-json/tactitwin/v1/analysis/player/{player_id}`

Personnel scenario:

`GET /wp-json/tactitwin/v1/planning/personnel-what-if`

The personnel scenario accepts team/opponent, player out/in, minute, score, player counts, recent-match sample size and language.
