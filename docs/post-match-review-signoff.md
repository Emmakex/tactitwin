# v1.25 — Post-Match Review Sign-off

## Purpose

v1.25 turns the post-match review from a calculation that only exists while the screen is open into an auditable, immutable and versioned staff closure artifact.

The feature closes the operational loop:

`prepare -> approve -> play -> measure -> review -> sign off -> learn`

It does **not** score coaching quality and it does not alter tactical recommendations, scenario scores, evidence ranking or Plan A/B/C activation.

## Sign-off prerequisites

A review can be signed off only when all of the following are true:

1. the Live Session is closed;
2. the session originated from an approved Staff Brief snapshot;
3. that Staff Brief is bound to an exact `fixture_key`;
4. the Live Session provider match identifier matches the provider match identifier frozen in that fixture;
5. objective post-match evidence is currently available for that same match;
6. the review SHA-256 submitted by the browser still matches a fresh server-side recalculation.

If the review or evidence changes after the staff member opened the screen, the sign-off request is rejected. Staff must inspect the new review before signing it.

## Immutable snapshot

Each sign-off stores a complete snapshot containing:

- exact generated post-match review;
- all evidence already embedded in that review;
- `fixture_key`;
- Live Session UUID;
- source Staff Brief snapshot UUID;
- provider `source_match_id`;
- staff sign-off note;
- WordPress user ID that signed off;
- sign-off timestamp;
- review SHA-256;
- content SHA-256;
- revision lineage.

The snapshot is not recalculated when read later.

## Version chain

The first sign-off is revision `1`.

Later sign-offs for the same Live Session create revision `2`, `3`, and so on. They never overwrite previous revisions.

Each later revision binds to the exact previous revision with:

- `parent_review_uuid`;
- `parent_content_sha256`.

This provides an append-only review lineage while still allowing staff to issue a corrected or expanded closure later.

## Integrity versus signature

SHA-256 is used to detect content changes and bind review revisions to their exact parent artifact.

The recorded staff identity is the authenticated WordPress user ID. This is an **application-level staff sign-off**, not a cryptographic digital signature and not proof of external legal identity.

## Database schema

v1.25 advances the plugin schema from `0.4.0` to `0.5.0` and adds:

`tactitwin_post_match_review_snapshots`

The table stores searchable identity and lineage columns plus the immutable JSON snapshot.

The existing Schema Guard runs `dbDelta()` automatically when the stored schema version differs.

## Match Workspace lifecycle

v1.24 could only report the final stage as `available_on_demand` because no persistent review-completion artifact existed.

v1.25 adds a real persistent final state:

- `signed_off`
- provenance: `post_match_review_snapshot`

When a valid signed review exists, the recommended next action advances to the historical learning layer rather than continuing to ask staff to review the match.

The lifecycle still has no aggregate completion percentage or preparation score.

## Guardrails

- no tactical score change;
- no recommendation-generation change;
- no scenario-rule change;
- no evidence eligibility/order change;
- no suitability/robustness weight change;
- no Plan A/B/C activation change;
- no Staff Brief integrity or fixture-identity change;
- no Live Session provenance change;
- no claim that SHA-256 is a digital signature;
- no cross-fixture sign-off;
- no sign-off of an active Live Session;
- no sign-off without objective evidence;
- no overwrite of prior signed review revisions.
