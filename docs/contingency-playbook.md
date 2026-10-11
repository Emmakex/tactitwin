# Contingency Playbook — Plan A / B / C

## Purpose

v0.9 turns TactiTwin's static pre-match evidence into an operational decision tree.

The playbook does not generate new tactical probabilities. It composes three already-auditable layers:

- the pre-match Match Plan
- the Full Matchup Scan
- the score/manpower Context What-if

The objective is to give the staff a prepared answer to:

> What do we start with, what do we change if the opponent behaves differently, and what do we do when the match state changes?

## Plan A — baseline match plan

Plan A is the default opening plan.

It reuses:

- opening 10–15 minute instructions
- in-possession instructions
- out-of-possession instructions
- after-loss instructions
- highest match-plan priorities

Plan A remains the automatic plan while there is no active score/manpower contingency.

Plan A exit prompts include:

- live high press / short-build-up lock
- persistent low/compact block
- repeated dangerous transition exposure after loss
- score/time or manpower contingency

## Plan B — opponent adaptation

Plan B is selected pre-match from the highest-risk supported opponent scenario among:

- build-up vs high press
- attack vs low block
- defensive transition after loss
- opponent side bias
- exploit opponent weak side

A specific Plan B is armed only when its risk score is at least 45/100.

If no supported scenario reaches 45, Plan B stays dormant in observation mode.

### Critical guardrail

Historical opponent risk **does not activate Plan B**.

The current plugin does not ingest live opponent-behaviour observations. Therefore v0.9 can say that Plan B is `armed`, but automatic `active_plan` remains Plan A unless a score/manpower contingency activates Plan C.

The decision tree explicitly labels all Plan B branches as requiring live staff observation.

## Plan C — score/manpower contingency

Plan C is driven by explicit current context.

Precedence:

1. numerical inferiority -> `manpower_inferior`
2. numerical superiority -> `manpower_superior`
3. trailing from minute 60 -> `chase`
4. leading from minute 70 -> `protect`
5. otherwise -> `standby`

A manpower imbalance overrides the normal time thresholds because the structural problem exists immediately.

Plan C instructions reuse the existing Context What-if recommendations and decision triggers rather than inventing a second score-state model.

## Automatic active-plan precedence

The deterministic order is:

1. IF player counts differ -> Plan C
2. ELSE IF trailing at minute 60+ -> Plan C chase
3. ELSE IF leading at minute 70+ -> Plan C protect
4. ELSE IF a live opponent-behaviour trigger is observed -> Plan B
5. ELSE -> Plan A

Because v0.9 has no live-observation input yet, branch 4 is represented in the playbook but is not automatically selected by the engine.

## Coverage

Playbook coverage is the lowest relevant evidence status from:

- Match Plan coverage
- Full Matchup Scan coverage
- current score-state Context What-if coverage
- manpower-state coverage when player counts are unequal

Low coverage does not delete Plan A/B/C. It adds an explicit warning and lowers the confidence of the active-plan explanation.

## REST

`GET /wp-json/tactitwin/v1/planning/contingency-playbook`

Parameters:

- `team_id`
- `opponent_id`
- `our_score` (default 0)
- `opponent_score` (default 0)
- `minute` (default 1)
- `our_players` (default 11)
- `opponent_players` (default 11)
- `limit` (1–10, default 5)
- `lang` (`es` / `en`)

The service loads the team/opponent profile bundle once, then creates:

1. Full Matchup Scan
2. Match Plan
3. Context What-if
4. Contingency Playbook

## Output contract

The playbook returns:

- `current_context`
- `coverage`
- `active_plan`
- `plans.plan_a`
- `plans.plan_b`
- `plans.plan_c`
- ordered `decision_tree`
- recommendations
- source evidence references
- explicit method and limitations

## Method boundary

This is deterministic decision support, not a live tactical controller and not a match-result forecast.

Without a live observation input, Plan B cannot be automatically verified as active. Without tracking/video, the system cannot verify real-time block geometry, line distances, synchronized pressing, cover shadows or off-ball rotations.
