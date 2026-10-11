# Manpower Context

## Purpose

TactiTwin extends the What-if Lab with numerical context so a coach can model situations such as:

- 1-0 at minute 75, 10v11
- 0-1 at minute 65, 11v10
- 1-1 at minute 80, 10v10

The exact player counts are retained in the hypothetical scenario. Historical evidence is grouped into three broader numerical states:

- `superior`
- `equal`
- `inferior`

## Historical reconstruction

TactiTwin reads the stored StatsBomb event `raw_json` and detects dismissals from:

- `foul_committed.card.name`
- `bad_behaviour.card.name`

Supported dismissal values:

- `Red Card`
- `Second Yellow`

The match starts as 11v11. After each unique dismissal, the affected team's on-pitch player count is reduced. Elapsed time and event metrics are then assigned to each team's current numerical state. Penalty shoot-outs are excluded.

No database migration is required because normalized events already retain `player_source_id`, `position_name` and the complete provider event JSON.

## Metrics by numerical state

For each state TactiTwin aggregates:

- observed minutes
- matches observed
- pass completion
- shots and xG
- progressive actions
- final-third entries
- box entries
- pressures and high pressures

Activity is normalized per 90 minutes of exposure.

## Combined What-if model

At 11v11, the existing score-state What-if scores remain unchanged.

For a numerical imbalance, TactiTwin computes historical intensity for:

- our selected numerical state
- the opponent's reciprocal state

The numerical evidence only changes the headline risk/opportunity indexes when both relevant historical samples provide at least 45 effective minutes.

When coverage is sufficient:

`combined intensity = 0.70 * score-state intensity + 0.30 * manpower-state intensity`

When coverage is below 45 effective minutes, the score-state index is preserved and the numerical layer is used only for structural recommendations and warnings.

This weighting is explicit in the output and is a decision-support heuristic, not a probability model.

## Tactical response

### Numerical inferiority

- protect central compactness
- use explicit pressing triggers rather than continuous pressure
- keep one credible transition outlet
- avoid combining an aggressive press with an unprotected last line

### Numerical superiority

- stretch the reduced block horizontally
- create and find the free receiver
- use switches, overlap/underlap and third-man actions after moving the block
- preserve rest defence against counters

## Current limitations

The current model understands the number of available players, but not yet the identity-specific tactical impact of the dismissed/substituted player. It also does not infer the post-dismissal formation or role redistribution without lineup, tracking or explicit coach input.

Substitution/personnel-specific scenario modeling is the next layer after this numerical-state foundation.
