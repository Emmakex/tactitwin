# v1.24 — Fixture Lifecycle & Workspace Progress

## Purpose

v1.24 makes the Match Workspace explain the **real operational state of one exact fixture** without inventing a preparation percentage or a new tactical score.

The lifecycle is derived from artifacts TactiTwin already owns: the selected match context, normalized event history, temporary planning outputs, approved immutable Staff Brief snapshots, Live Sessions and imported match events.

## Lifecycle stages

The Workspace exposes seven stages:

1. **Match target** — whether one canonical imported match + team perspective is active for the current user.
2. **Planning data** — whether normalized event history exists for both our team and the opponent under the active provider/source identity.
3. **Match Plan** — whether a temporary matchup result exists or the exact source Match Plan has been sealed inside an approved Staff Brief snapshot.
4. **Staff Brief** — not generated, temporary draft, or approved immutable snapshot for the exact fixture.
5. **Live Session** — not started, active, or closed; only handoff-derived sessions belonging to approved snapshots in the same fixture chain count.
6. **Match evidence** — whether the Live Session points to the same provider match ID as the active fixture and normalized events are actually available.
7. **Post-match review** — whether the existing review engine can reconstruct a review from a closed session plus evidence.

## Provenance is part of the status

Each state exposes its source instead of presenting all states as equivalent:

- **user meta** — persistent per-user workspace context;
- **database** — persistent normalized or session records;
- **approved snapshot** — immutable approved Staff Brief content;
- **transient** — temporary WordPress output that expires;
- **database-derived** — a status reconstructed from persistent records;
- **computed on demand** — an analysis that is produced when opened and is not stored as a completed artifact.

## Match Plan boundary

The existing standalone Match Plan screen stores its result in a temporary WordPress transient and identifies only the team/opponent matchup. It does not currently persist exact fixture identity.

v1.24 therefore labels that state **temporary matchup result**. It becomes fixture-bound and persistent only when the Staff Brief approval snapshot freezes the exact source Match Plan for the canonical fixture.

This prevents two matches against the same opponent from being treated as the same persisted Match Plan.

## Planning-data state

For the active provider/source identity, v1.24 counts distinct normalized matches with events for our team and the opponent:

- `ready` — both sides have historical event matches;
- `partial` — only one side has event history;
- `missing` — neither side has event history.

This is a coverage/readiness signal only. It is not a tactical-quality score and does not claim that a particular sample size is sufficient for every coaching decision.

## Live Session scope

A Live Session counts toward the active fixture lifecycle only when it is linked through a Staff Brief snapshot carrying the same `fixture_key`.

The active Workspace selector never silently rebinds a historical Live Session.

## Evidence state

A session can be:

- waiting for a session;
- unlinked;
- linked to a different fixture;
- linked to the active fixture but still waiting for normalized events;
- evidence available.

Events from another fixture never satisfy evidence readiness for the current fixture, even if the same two clubs are involved.

## Review boundary

The current post-match review service reconstructs its result from the persistent Live Session timeline and imported match evidence. It does not store a separate durable “review completed” record.

For that reason v1.24 reports **available on demand**, not “completed”. A later version may add explicit review sign-off/history if there is a product need for that workflow.

## Recommended next action

The lifecycle exposes one deterministic next action, for example:

`select fixture -> complete data -> Match Plan -> fixture-bound Staff Brief -> approval -> Live Session handoff -> live session -> evidence -> post-match review`

The recommendation is rule-based from lifecycle states. The states are not summed, weighted or converted into a percentage.

## Guardrails

v1.24 does not change:

- tactical scores;
- scenario rules;
- recommendation generation;
- historical evidence eligibility or ranking;
- suitability/robustness weights;
- Plan A/B/C activation rules;
- Staff Brief snapshot integrity;
- fixture identity;
- Live Session provenance.

No database schema migration is required.
