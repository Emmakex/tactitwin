# v1.19 — Staff Brief Approval Revision Chain

## Goal

v1.19 turns repeated Staff Brief approvals into an auditable revision chain.

v1.17 made each approved brief immutable. v1.18 made the differences between versions visible. v1.19 records **which previous approved snapshot was reviewed and which exact structural diff was acknowledged when a new revision was approved**.

## Revision record

Every new v1.19 approved Staff Brief stores a sealed `revision` object inside its existing immutable snapshot JSON.

The revision contains:

- sequential revision number for the exact team/opponent pairing;
- parent approved snapshot UUID;
- parent content SHA-256;
- approved/current snapshot UUID binding;
- approved/current snapshot content SHA-256 binding;
- reviewed structural change count;
- stable reviewed change IDs;
- changed sections;
- a language-invariant machine copy of the v1.18 diff;
- SHA-256 of that reviewed diff;
- optional staff approval note;
- SHA-256 of the complete revision record.

The first approval has no parent and is revision `#1`.

Legacy v1.17/v1.18 snapshots without revision metadata remain valid.

## Approval workflow

On `TactiTwin > Staff Brief`:

1. generate the current Staff Brief;
2. TactiTwin finds the latest approved snapshot for the same team/opponent;
3. v1.18 renders the structural diff;
4. v1.19 builds the prospective revision record;
5. if tracked changes exist, the staff must explicitly acknowledge that it reviewed them;
6. an optional approval note may be added;
7. approval recomputes the latest parent and diff before persisting;
8. the expected parent UUID and expected diff digest submitted by the page must still match;
9. snapshot content is frozen and its `content_sha256` is calculated;
10. the revision is bound to the exact new `snapshot_uuid` plus `content_sha256` and re-sealed;
11. only then is the new immutable snapshot written.

## Stale-review protection

The approval form carries:

- `expected_parent_snapshot_uuid`;
- `expected_diff_sha256`.

The service recomputes both immediately before approval.

If another approval happened after the page was rendered, or the generated/current diff changed, the approval is rejected. The staff must review the newest state instead of silently approving against a different predecessor.

This is optimistic concurrency for the approval chain; it is not a distributed lock.

## Integrity model

Two linked SHA-256 checks exist for v1.19 snapshots:

1. `content_sha256` — existing v1.17 digest for the exact frozen Staff Brief + Match Plan + Contingency Playbook source;
2. `revision_sha256` — digest for the revision lineage, reviewed diff, approval note, `approved_snapshot_uuid` and `approved_content_sha256` binding.

The revision's `approved_snapshot_uuid` must equal the containing snapshot UUID and its `approved_content_sha256` must equal the snapshot's `content_sha256`. Therefore an independently valid revision record cannot be swapped into a different Staff Brief approval, even when two approvals contain byte-for-byte identical tactical content and therefore share the same content digest.

`TactiTwin_Staff_Brief_Snapshot::verify()` validates the content digest, revision digest, snapshot-identity binding and content binding whenever revision metadata is present. A legacy snapshot without revision metadata continues to validate using its original content digest only.

The reviewed diff also has its own `reviewed_diff_sha256`, calculated from machine data only. Localized labels are excluded, so ES/EN produce the same digest for the same structural changes.

### v1.19.1 integrity hardening

The v1.19.1 hotfix adds the explicit snapshot-UUID binding described above. The original v1.19 content binding correctly prevented cross-content revision swaps; v1.19.1 also prevents swaps between two different approval snapshots whose frozen tactical content is identical.

No tactical behavior changes in v1.19.1.

## Persistence

v1.19 and v1.19.1 require **no database schema change**.

The revision chain is stored inside the existing `snapshot_json` field of `tactitwin_staff_brief_snapshots`. Parent lookup continues to use the append-only snapshot store already introduced by v1.17.

## Language rule

Revision number, parent UUID, approved snapshot UUID, change IDs, before/after values and digests are language invariant.

English/Spanish changes presentation text only.

## Guardrails

v1.19/v1.19.1:

- creates no tactical or change-severity score;
- does not decide whether a change is good or bad;
- does not recalculate scenarios or profiles;
- does not change Match Plan priorities;
- does not reorder evidence;
- does not activate Plan A/B/C;
- does not mutate prior approved snapshots;
- does not turn SHA-256 into a digital signature or identity proof.

It answers one additional audit question:

**“Which previous version and which exact set of changes did the staff review when it approved this revision?”**
