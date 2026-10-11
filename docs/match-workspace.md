# v1.23 — Match Workspace

## Purpose

v1.23 turns the selected football match into the operational home of the WordPress plugin. The active match introduced by v1.21 and promoted to a shared planning context by v1.22 is now surfaced as a dedicated **Match Workspace** rather than only appearing inside individual tools.

This is a workflow and identity layer. It does not introduce a new tactical score, recommendation model, evidence ranker or Plan A/B/C activation rule.

## WordPress home

The top-level **TactiTwin** admin page now opens the Match Workspace.

The previous data/import dashboard is preserved as:

- **TactiTwin → Data & Imports**

StatsBomb Open Data competition and match-event import behavior is unchanged.

## Active match

The workspace lets the authenticated staff user choose:

- one persisted row from `tactitwin_matches`;
- which participant is **our team**.

The selection is stored per WordPress user through the existing v1.22 planning-context user meta.

The workspace re-resolves the stored selection against the normalized match row before presenting it. The local database row ID remains only an installation-local locator. Provider source + provider match ID + team perspective remain the fixture-identity basis already defined by v1.20/v1.21.

The active-match summary exposes:

- our team and opponent;
- home/away perspective;
- match date;
- competition and season when available;
- provider and provider match ID;
- abbreviated `fixture_key` for audit visibility.

## Workflow hub

The Match Workspace presents the current product flow in deterministic order.

### Pre-match

1. Scenario Lab
2. Match Plan
3. What-if
4. Lineup Lab
5. Personnel Lab
6. Unit Lab
7. Staff Brief

### Match day

8. Live Playbook
9. Live Sessions

### Post-match

10. Session Review

Live Sessions are intentionally marked as downstream of the approved Staff Brief/session handoff. Session Review remains session-derived. v1.23 does not silently rebind either surface to whatever match happens to be active in the workspace.

## Context expansion

v1.22 originally applied the active match directly to:

- Scenario Lab;
- Match Plan;
- What-if;
- Live Playbook;
- Staff Brief.

v1.23 additionally applies it to:

- Lineup Lab;
- Personnel Lab;
- Unit Lab.

For Lineup and Personnel the canonical team/opponent pair is injected before calculation.

Unit Lab is opponent-independent, but its team is still forced to the active-match team so the player-pair analysis stays inside the same preparation context.

Every supported admin submission re-resolves the stored match through `TactiTwin_Match_Target_Service` before overriding `team_id` / `opponent_id`.

## Player-selector synchronization

Lineup, Personnel and Unit Lab filter player choices according to the selected team in browser JavaScript.

Because v1.23 can change the team from the shared context selector, the planning-context bridge now dispatches a real browser `change` event whenever it updates a team selector. Existing roster-filter listeners therefore refresh immediately and do not leave visible players from the previously selected club.

Server-side match/team validation remains authoritative; the browser event is UX synchronization only.

## Manual fallback

Selecting **manual mode / no active match** clears the per-user planning context.

All individual tools continue to expose their existing manual selectors. This is important when public/open sources do not contain the future fixture the staff wants to prepare.

## Guardrails

v1.23:

- does not change tactical scores;
- does not change recommendation text generation logic;
- does not change historical-evidence eligibility or ordering;
- does not change evidence suitability or robustness weights;
- does not change Plan A/B/C activation;
- does not create a database schema migration;
- does not convert the local `tactitwin_matches.id` into a cross-install canonical identity;
- does not rebind historical Live Sessions or post-match reviews to the current workspace selection.

## Regression coverage

`tests/test-match-workspace.php` verifies:

- deterministic context selection;
- canonical team/opponent propagation;
- Lineup, Personnel and Unit actions are included in shared context;
- only Staff Brief receives the canonical `target_match_id` handoff field;
- deterministic Match Workspace stage order;
- unique destination pages;
- Unit Lab remains team-context rather than opponent-dependent;
- Live Sessions remain handoff-derived;
- Session Review remains session-derived;
- opposite team perspectives remain different fixture scopes.
