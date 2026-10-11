# Live Observation Playbook

## Purpose

v1.0 closes the main v0.9 limitation: Plan B can now be activated from explicit staff observations during the match.

The engine still does **not** infer live opponent behaviour from pre-match history. A human must confirm the live behaviour through the WordPress Live Playbook or the REST endpoint.

## Supported live observations

| Observation | Matching scenario | Plan B mode |
| --- | --- | --- |
| Opponent presses high / locks short build-up | `build-up-vs-high-press` | `escape_high_press` |
| Opponent settles into a persistent low/compact block | `attack-vs-low-block` | `break_low_block` |
| Our losses repeatedly create immediate transition threat | `defensive-transition-after-loss` | `secure_transition` |
| Opponent repeatedly attacks through the same side/channel | `opponent-side-bias` | `protect_dominant_side` |
| The identified weak side remains consistently available | `exploit-opponent-weak-side` | `attack_weak_side` |

## Activation rule

1. Build the normal v0.9 Contingency Playbook.
2. Normalize the five manual observation inputs.
3. Map checked observations to their existing matchup scenarios.
4. If multiple behaviours are checked, select the observed scenario with the highest pre-match risk score. Opportunity score is used only as a tie-breaker.
5. If Plan C is already active, keep Plan C active and record the selected Plan B as suppressed by the higher-priority contingency.
6. Otherwise activate Plan B and return the selected scenario instructions.
7. If no observation is checked, keep the normal Plan A/C result unchanged.

This preserves the v0.9 precedence:

`Plan C > live-confirmed Plan B > Plan A`

## Important methodological boundary

The pre-match risk score helps prioritize **which observed live behaviour to address first** when several are present. It does not create the live observation itself.

The staff check is the live evidence source.

Manual observations can be subjective, incomplete or late. They are intentionally exposed in the output as `source: manual-staff-input` and are not described as tracking-derived detection.

## WordPress UI

A new submenu is available:

`TactiTwin -> Live Playbook`

The workspace contains:

- our team / opponent
- score
- player counts
- minute
- historical sample size
- five live behaviour checkboxes
- staff note

The result shows:

- current active plan and mode
- score/manpower context
- live observation count and activation status
- observed Plan B scenario candidates with pre-match risk
- prepared Plan A / B / C cards
- ordered decision tree
- current recommendations
- full technical evidence JSON

## REST API

`POST /wp-json/tactitwin/v1/planning/live-playbook`

Context parameters:

- `team_id`
- `opponent_id`
- `our_score`
- `opponent_score`
- `minute`
- `our_players`
- `opponent_players`
- `limit`
- `lang`

Observation parameters:

- `observed_high_press`
- `observed_low_block`
- `observed_transition_threat`
- `observed_side_bias`
- `observed_weak_side_open`
- `observation_note`

## Output additions

The v0.9 playbook is preserved and enriched with:

- `live_observation.observations`
- `live_observation.observed_count`
- `live_observation.candidate_scenarios`
- `live_observation.selected_scenario`
- `live_observation.activation`
- updated `active_plan`
- live recommendations
- `method.live_observation.source = manual-staff-input`

## Next evolution

The same contract can later accept observations from:

- automated video tagging
- tracking-derived press/block classifiers
- analyst event buttons
- live data provider feeds

Those future sources should retain source identity and confidence rather than silently replacing manual observations.
