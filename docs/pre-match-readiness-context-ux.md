# v1.38 — Pre-match Readiness & Active Context UX

## Goal

Make the active fixture and its genuinely eligible pre-match evidence visible before staff enters tactical preparation, without creating a preparation score or triggering data imports.

The first real WordPress test revealed two UX gaps:

1. Match Workspace could report generic event history that included the target fixture, while tactical preparation correctly excluded that fixture and later matches.
2. Tactical Twin Lab showed raw team source IDs in its active-context notice even though Match Workspace already knew the human fixture identity.

v1.38 adds a read-only readiness view using the same temporal boundary as the tactical engines.

## Eligible pre-match evidence

`TactiTwin_Pre_Match_Readiness::inspect()` reads normalized matches/events for the active team and opponent with two mandatory boundaries:

- `match_date < active fixture date`
- `source_match_id != active target match ID`

It returns, for both sides:

- eligible match count
- exact match IDs
- oldest/newest eligible date
- sample-volume label

And across the matchup:

- effective matches = `min(team_matches, opponent_matches)`
- limiting side
- explicit temporal scope
- target-fixture exclusion flag
- future-match exclusion flag

The service is read-only: it imports no data and calculates no tactical score.

## Match Workspace

A new pre-match evidence panel shows:

- human team/opponent names
- eligible sample size for each side
- date window for each side
- effective evidence coverage
- limiting side
- temporal/leakage guard

The legacy lifecycle planning-data card is visually corrected to describe the same eligible pre-match sample rather than generic stored event history.

Tactical Twin Lab is also surfaced as a first-class Workspace step immediately after Match Plan when an older Workspace renderer does not already include it.

## Tactical Twin Lab

The active-context notice is humanized from raw source IDs to:

- our team vs opponent
- fixture date
- effective eligible evidence count
- direct link back to Match Workspace

This changes presentation only. Twin Lab continues to derive the matchup from the canonical planning context.

## Boundaries

v1.38 does not:

- import historical matches;
- run tactical scenarios while Workspace opens;
- create a readiness percentage;
- change lifecycle persistence;
- change tactical scores/recommendations;
- change Plan A/B/C activation;
- change fixture identity;
- create a database migration.

## Exit criterion

For an active historical fixture whose target match is already imported, readiness must exclude that target fixture and all later matches, expose exact eligible match IDs/date windows for both teams, retain the conservative `min(team, opponent)` effective coverage and identify the limiting side. The WordPress Workspace must display that eligible sample separately from tactical scores and Twin Lab must show the human active-fixture identity rather than raw team IDs. Opening either surface must perform read-only queries only. Dedicated regressions must prove cutoff/exclusion SQL, exact source scoping, no tactical-score/import behavior and admin wiring.
