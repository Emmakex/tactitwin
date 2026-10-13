# v1.39 — Fixture Lifecycle Readiness Semantics

## Goal

Make the fixture lifecycle use the same pre-match evidence boundary as tactical preparation instead of counting every imported event-data match for each team.

## Planning readiness

A fixture is evaluated against the evidence sample that is eligible **before** the active fixture:

- only matches with `match_date < active_fixture_date`;
- the target fixture is explicitly excluded by `source_match_id`;
- both team and opponent samples remain separately auditable;
- `effective_matches = min(team_matches, opponent_matches)`;
- the limiting side is retained.

The lifecycle declares planning data:

- `missing` when neither side has an eligible match;
- `partial` when either side has fewer than 3 eligible event-data matches;
- `ready` when both sides have at least 3 eligible event-data matches.

This threshold matches the existing low-coverage warning used by tactical planning. It is not a probability, confidence percentage or readiness score.

## Phase precedence

Pre-match data gaps must not rewind a fixture that has already reached match day or post-match.

The deterministic next action therefore follows this precedence:

1. no context → select match;
2. active Live Session → continue Live Session;
3. closed Live Session → evidence/review/learning flow;
4. only while still pre-match → planning-data readiness, Match Plan, Staff Brief and handoff.

## Planning-data artifact

The lifecycle planning stage now exposes:

- eligible matches for our team and opponent;
- effective matches;
- limiting side;
- minimum ready sample per side;
- exact team/opponent sample metadata when available;
- temporal scope and target-fixture exclusion provenance.

Legacy `team_history_matches` / `opponent_history_matches` fields remain available for compatibility, but now describe the eligible pre-match sample when `inspect()` is used.

## Guardrails

v1.39:

- imports no data by itself;
- creates no preparation percentage;
- changes no tactical score or recommendation;
- does not activate a plan;
- does not alter fixture identity;
- does not block an active or closed session because of retrospective pre-match coverage gaps;
- adds no database schema.

## Exit criterion

For one active fixture, lifecycle planning readiness must be derived from the same eligible-before-fixture evidence scope used by preparation. `1 vs 1` and `5 vs 1` must remain partial, `3 vs 3` must be ready, exact sample/temporal provenance must survive into the lifecycle stage, and active/post-match artifacts must take precedence over pre-match data gaps when choosing the next action.
