# v1.3 Objective Post-Match Evidence

## Purpose

v1.3 adds a second, explicitly separate layer to the post-match audit:

1. **Live staff record** — what staff explicitly observed and what TactiTwin activated during the match.
2. **Post-match event evidence** — what the imported event feed supports at full-match level.

The second layer never overwrites the first. It is an audit aid, not a truth label for the coach.

## Session-to-match linking

A persistent Live Session can be linked to an imported StatsBomb Open Data match through:

- WordPress: `TactiTwin -> Evidence Review`
- REST: `POST /wp-json/tactitwin/v1/planning/live-sessions/{session_uuid}/link-match`

Only imported matches containing exactly the same two team source IDs as the live session can be linked. Home/away order may differ from the session's own-team/opponent order.

Matching imported matches can be queried through:

`GET /wp-json/tactitwin/v1/planning/live-sessions/{session_uuid}/matching-matches`

Linking match metadata does not imply that event evidence is available. The linked match must also have normalized events imported before the evidence engine can run.

## Review behavior

`GET /wp-json/tactitwin/v1/planning/live-sessions/{session_uuid}/review`

continues to return the descriptive prepared/observed/activated review. It now also contains `objective_evidence`:

- `status=unlinked` when no imported match is linked.
- `status=unavailable` when the match is linked but event evidence cannot be built yet.
- `status=available` when the linked match has compatible normalized event data.

A missing event layer never destroys the manual session review.

## Supported event proxies

### Opponent high press

Scenario: `build-up-vs-high-press`

Indicators:

- opponent high pressures
- opponent total pressures
- high-pressure share

Supported when:

- `high_pressures >= 12` AND `high_pressure_share_pct >= 20`, OR
- `high_pressures >= 16`

Partially supported when:

- `high_pressures >= 7`, OR
- `high_pressure_share_pct >= 15`

Boundary: pressure events are not tracking. They do not measure the whole block height, pressing synchrony or every off-ball press.

### Opponent low / compact block

Scenario: `attack-vs-low-block`

Indicators:

- deep defensive actions
- all defensive actions
- deep-defensive-action share

Supported when:

- `deep_defensive_actions >= 12` AND `deep_defensive_action_share_pct >= 45`

Partially supported when:

- `deep_defensive_actions >= 8` AND share `>= 35`, OR
- `deep_defensive_actions >= 15`

Boundary: this is a territorial event proxy, not tracking-derived block height or compactness.

### Opponent transition threat after our losses

Scenario: `defensive-transition-after-loss`

Indicators from our recorded turnovers:

- opponent progressions within 10 seconds
- opponent shots within 10 seconds
- xG conceded within 10 seconds
- transition progression rate
- dangerous-turnover rate

Supported when any of these is true:

- at least 2 opponent shots within 10 seconds
- at least 0.30 xG within 10 seconds
- at least 4 opponent progressions with progression rate at least 25%

Partially supported when any of these is true:

- at least 1 opponent shot
- at least 0.10 xG
- at least 2 opponent progressions

If fewer than three relevant turnovers exist and no positive threshold is reached, the result is `insufficient` instead of `not-supported`.

### Opponent lateral bias

Scenario: `opponent-side-bias`

Indicators:

- opponent final-third action count
- left / central / right final-third shares
- left-vs-right share gap

Supported when:

- at least 12 final-third actions
- lateral gap at least 20 percentage points
- dominant lateral channel share at least 38%

Partially supported when:

- at least 8 final-third actions
- lateral gap at least 12 points

Fewer than six final-third actions produces `insufficient`.

### Access to prepared weak-side channel

Scenario: `exploit-opponent-weak-side`

The engine refuses to reconstruct the intended weak-side target after the match. A structured pre-match target channel must already exist in the stored playbook evidence.

For sessions without that structured target, the result is `insufficient`.

When a target exists:

- supported: target channel >= 35% of our final-third actions
- partially supported: target channel >= 25%
- not supported: below 25%, provided there is enough final-third volume

This preserves temporal integrity: post-match data must not rewrite what TactiTwin believed before kick-off.

## Comparison labels

For every supported scenario the engine keeps two independent facts:

- whether staff recorded the behaviour
- whether the full-match event proxy supports it

Possible comparisons include:

- `observed-and-supported`
- `observed-and-partially-supported`
- `observed-not-supported-by-full-match-proxy`
- `observed-evidence-insufficient`
- `event-signal-not-recorded-by-staff`
- `not-observed-evidence-insufficient`
- `not-observed-no-event-signal`

`observed-not-supported-by-full-match-proxy` does **not** mean the staff observation was wrong. A behavior can occur intensely for a short period and disappear in the full-match aggregate.

## Scope and limitations

v1.3 uses **full-match aggregate event evidence**. It is not yet time-aligned to each staff snapshot. Therefore:

- it cannot prove that a full-match event signal existed at the exact minute of a staff observation;
- it cannot replace tracking for block shape, compactness or off-ball behavior;
- threshold values are transparent product heuristics, not universal football laws;
- agreement between staff observations and event evidence does not establish causality or coaching quality.

A later layer can segment imported events by live-session intervals so each A/B/C segment can be compared to event evidence from the same time window.
