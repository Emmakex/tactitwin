# v1.21 — Imported Match Target Resolution

## Purpose

v1.20 gave TactiTwin an exact fixture identity, but a coach could still need to enter fixture fields manually. v1.21 connects that identity to the normalized `tactitwin_matches` table so the Staff Brief can target an already imported football match directly.

The imported match is an audit/input target. Selecting it does not change tactical formulas, scenario weights, evidence ranking or Plan A/B/C activation logic.

## Staff Brief workflow

The Staff Brief screen now exposes an **Imported match / Partido importado** selector before the manual fixture fields.

When a persisted match is selected:

1. the selector exposes the stored home and away participants;
2. the current `Our team / Nuestro equipo` selection is kept if it is one of those participants, otherwise the UI defaults to the home participant;
3. the opponent is derived as the other participant;
4. source, provider match ID, competition, season, date, kick-off and match week are displayed from the persisted match;
5. before Match Plan calculation, the server resolves the selected target again and verifies the team perspective;
6. after Match Plan calculation, fixture binding resolves the target again and verifies that the generated opponent matches the imported target;
7. the resulting v1.20 `fixture_key` is built from the persisted provider match identity and is then protected by the existing snapshot/revision integrity chain.

The browser fields are convenience only. Server-side resolution from `tactitwin_matches` is authoritative.

## Canonical target resolver

`TactiTwin_Match_Target_Service` reads the normalized match table and joins team and competition labels for the WordPress selector.

For an imported match it returns:

- internal match-row ID for UI selection;
- provider source;
- provider `source_match_id`;
- competition and season IDs;
- match date and kick-off;
- match week;
- home and away team IDs/names;
- the selected team perspective (`home` or `away`);
- the resolved opponent;
- the normalized v1.20 fixture identity.

A target is rejected when:

- the imported row is missing provider source identity;
- either participant is missing;
- home and away resolve to the same team;
- the selected team is not a participant in the match.

## Identity behavior

Imported targets use the v1.20 `provider-match-id` mode:

`source + source_match_id + team + opponent`

Competition, season, date, kick-off and match week remain attached as descriptive/audit context, but do not alter the key when a provider match ID exists. This means a schedule correction does not create a second approval chain for the same provider fixture.

The same physical match viewed from opposite team perspectives deliberately produces different fixture keys because Staff Brief approval lineage is team-perspective-specific.

## Manual fallback

The v1.20 manual fixture workflow remains available through **Manual identity / no imported match**. No schema migration is required for v1.21.

## ES / EN

The selector and all new helper labels are available in Spanish and English. Machine fixture identity remains language-invariant.

## Guardrails

v1.21:

- does not recalculate or modify tactical scores;
- does not alter recommendation order;
- does not alter historical evidence order;
- does not activate any plan;
- does not trust browser-provided fixture metadata when an imported target is selected;
- does not infer a participant that is absent from the persisted normalized match;
- does not change legacy/manual Staff Brief fixture behavior.

## Regression coverage

`tests/test-match-target-resolution.php` covers:

- home-team perspective;
- away-team perspective;
- automatic opponent resolution;
- provider/source propagation;
- competition/season/date/kick-off/week context propagation;
- stable provider fixture identity across descriptive schedule changes;
- rejection of a non-participant team;
- rejection of incomplete canonical match identity.
