# v1.20 — Exact Staff Brief Fixture Identity

## Purpose

Before v1.20, the Staff Brief approval history was scoped by `team_source_id + opponent_source_id`. That is sufficient to describe a matchup, but it is not sufficient to identify one football match.

A club can face the same opponent several times in the same season or competition. Without an explicit fixture identity, two different matches could be treated as successive revisions of the same Staff Brief approval chain.

v1.20 separates **matchup identity** from **fixture identity**.

## Fixture identity

`TactiTwin_Fixture_Identity` creates a deterministic, language-independent `fixture_key` using one of two modes.

### Provider match ID

When a provider match ID exists, the identity basis is:

- source/provider;
- provider `source_match_id`;
- our team source ID;
- opponent source ID.

The resulting mode is `provider-match-id`.

Descriptive metadata such as a corrected date does not change the identity when the same provider match ID remains present.

### Scheduled composite

When no provider match ID exists, a scheduled fixture can still be identified from:

- source/provider;
- competition ID when available;
- season ID when available;
- match date;
- kick-off time when available;
- match week when available;
- our team source ID;
- opponent source ID.

The resulting mode is `scheduled-composite`.

A match date is mandatory when no provider match ID exists.

## WordPress Staff Brief workflow

The Staff Brief screen can bind a generated brief to a target fixture before approval. The fixture inputs are independent from the tactical model and do not change Match Plan calculations.

The staff can provide:

- match date;
- source/provider;
- provider match ID;
- competition ID;
- season ID;
- match week;
- kick-off time.

The fixture identity is normalized when the Staff Brief is composed and the resulting `fixture_key` is embedded in the brief.

## Approval scope

When `fixture_key` exists:

- the previous approved snapshot is searched by exact `fixture_key`;
- revision numbers are counted only inside that fixture;
- another match against the same opponent cannot become the revision parent;
- stale-review protection remains unchanged;
- the revision digest includes the fixture key.

Legacy Staff Briefs without fixture identity continue to use the previous team/opponent scope and are kept in a separate legacy chain. Fixture-bound snapshots are never silently mixed into a legacy chain.

## Version Diff

The v1.18 structural Staff Brief diff keeps its original behavior but v1.20 adds a scope guard when fixture identity is available.

Two versions can be compared only when:

1. they describe the same team/opponent pairing; and
2. both belong to the same exact fixture, or both are legacy versions without fixture identity.

A fixture-bound version and a legacy version are not considered the same revision scope.

## Immutable snapshot integrity

The normalized fixture object is stored inside the frozen Staff Brief content. Therefore it is covered by the existing snapshot `content_sha256`.

The snapshot also exposes `fixture_key` at top level for efficient persistence/indexing. Verification requires the top-level key, normalized fixture payload and immutable content to agree.

The approval revision record stores its own `fixture_key`, which is also covered by `revision_sha256`.

Changing fixture metadata after approval invalidates snapshot verification.

## Persistence

Schema version moves from `0.3.0` to `0.4.0`.

`tactitwin_staff_brief_snapshots` adds:

- `fixture_key char(64) NOT NULL DEFAULT ''`;
- an index on `fixture_key`.

Existing rows receive an empty key and remain valid legacy snapshots.

The database fixture index is checked against the immutable snapshot payload when a snapshot is loaded. An index/payload mismatch is treated as an integrity error rather than silently changing revision scope.

## Live Session handoff

When a fixture-bound approved snapshot contains a provider `source_match_id`, handoff automatically carries that match ID into the Live Session if no explicit source match ID was supplied.

This strengthens the link between pre-match preparation and the subsequent match-day/post-match evidence workflow without recalculating the frozen tactical plan.

## Guardrails

v1.20:

- creates no tactical score;
- changes no tactical score;
- changes no recommendation;
- changes no evidence order;
- changes no Plan A/B/C activation rule;
- does not claim that fixture metadata proves provider authenticity;
- does not replace provider-specific match validation;
- preserves legacy snapshots and legacy revision chains.

Fixture identity is an audit/scope primitive. It answers **which exact match is this preparation for?**, not **what should the team do?**.

## Regression coverage

`tests/test-fixture-identity.php` covers:

- deterministic scheduled fixture keys;
- different dates against the same opponent producing different keys;
- provider match-ID stability when descriptive date metadata changes;
- invalid incomplete manual fixture identity;
- Staff Brief fixture binding;
- fixture key inside revision and snapshot integrity;
- rejection of a revision parent from another fixture;
- language invariance;
- valid same-fixture revision continuation;
- fixture metadata tamper detection.
