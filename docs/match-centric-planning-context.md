# v1.22 — Match-Centric Planning Context

## Goal

TactiTwin v1.22 turns the imported match selected in v1.21 into a shared planning context instead of making each WordPress planning surface independently ask for the same matchup.

The shared context is scoped to the authenticated WordPress user and contains one canonical imported match plus one team perspective.

## Supported surfaces

The same context is reused by:

- Scenario Lab;
- Match Plan;
- What-if Lab;
- Live Playbook;
- Match-Day Staff Brief.

Staff Brief remains the integrity boundary for approval. v1.22 simply supplies the canonical imported match row to the existing v1.21 target resolver, which then produces the v1.20 fixture identity used by version diff, revision lineage, approval snapshot and Live Session handoff.

## Canonical selection

A selection is represented as:

`{tactitwin_matches.id}:{our_team_source_id}`

The local database row ID is only a locator inside the current WordPress installation. It is not promoted to a provider-independent football identity.

For every submitted planning action TactiTwin re-reads that local match row and resolves the selected team perspective through `TactiTwin_Match_Target_Service`.

The resulting canonical context includes:

- normalized match row locator;
- our team source ID;
- opponent source ID;
- home/away perspective;
- provider source;
- provider match ID;
- competition and season context;
- date, kick-off and match week where available;
- the deterministic v1.20 `fixture_key`.

## Server-side authority

The browser selector is convenience only.

Before the existing tactical handler runs, v1.22:

1. verifies the existing action nonce;
2. parses the selected match + perspective;
3. reloads the match from `tactitwin_matches`;
4. verifies that the selected team is actually a participant;
5. derives the opponent from the persisted match row;
6. writes the verified `team_id` and `opponent_id` into the request consumed by the existing tactical module.

For Staff Brief, it additionally supplies the canonical `target_match_id` and forces exact fixture binding so the existing v1.21/v1.20 integrity chain is retained.

## Persistence

The active context is stored in WordPress user meta under a TactiTwin-owned key.

This is intentionally user-scoped rather than global: two staff users may prepare different fixtures at the same time without changing one another's working context.

The stored data is not blindly trusted. On every supported planning submission, the match locator + team perspective are re-resolved against the current normalized match row before tactical calculation.

## Manual fallback

Selecting manual mode removes the user's shared planning context and restores the existing independent team/opponent controls.

This fallback remains necessary because public/open providers may not contain every future fixture or locally planned friendly match.

Staff Brief also retains the v1.20 manual fixture-identity path when no imported canonical match is selected.

## Method boundary

v1.22 is a workflow and identity-consistency layer.

It does not:

- create a tactical score;
- change any scenario score;
- change recommendation logic;
- reorder evidence;
- activate Plan A, B or C;
- infer a lineup;
- claim that provider fixture metadata is authentic merely because it is stored locally.

Its purpose is to ensure that the existing tactical modules operate on the same verified football match when the coach chooses a canonical imported target.
