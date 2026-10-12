# v1.32 — Frozen Twin Handoff

## Goal

v1.32 binds the Tactical Twin preparation produced before the match to the same immutable approval chain already used by the Match-Day Staff Brief.

The key requirement is temporal integrity:

> The Twin consulted during the match must be the Twin the staff saw before approval, not a recalculation using newer code or newer data.

## Reuse the existing approval chain

v1.32 deliberately introduces **no new database table and no parallel snapshot model**.

The generated Tactical Twin Lab payload is attached to the Staff Brief before the brief is displayed and approved. When staff approve the brief, the existing `TactiTwin_Staff_Brief_Snapshot` freezes the complete brief payload and protects it with the existing SHA-256 content digest.

Therefore the approved snapshot now covers:

- the compact Staff Brief;
- frozen source Match Plan;
- frozen source Contingency Playbook;
- the exact Tactical Twin Lab preparation attached to the Staff Brief.

Older Staff Brief snapshots created before v1.32 remain valid and contain no Twin attachment.

## Attachment integrity

`TactiTwin_Frozen_Twin_Handoff` validates that:

- the source artifact is a Match-Day Staff Brief;
- the Twin artifact is a valid Tactical Twin Lab result;
- the Lab contains Response Matrix, Scenario Tree, Response Robustness and Response Switch Playbook layers;
- Staff Brief and Twin describe the same team/opponent pairing.

The attachment stores:

- the exact full Twin Lab payload;
- an internal SHA-256 digest of that Twin payload;
- a compact structural signature;
- method boundaries declaring no recalculation on live handoff and no automatic response activation.

The internal digest is additional traceability. The approved Staff Brief snapshot SHA remains the authoritative outer integrity boundary.

## Structural Twin signature

For approval/version review, v1.32 derives a compact signature from the Twin:

- Twin content SHA-256;
- baseline response ID;
- most robust response ID;
- number of switch candidates;
- number of coverage-caution switches;
- per-branch switch map containing decision, target response, state-fit margin and coverage caution.

This allows the existing Staff Brief version diff to detect a changed Tactical Twin without comparing the full large JSON payload field-by-field.

A Twin change appears with the stable change ID:

`TACTICAL-TWIN-PREPARATION-CHANGED`

and section:

`tactical_twin`

The approval revision/stale-review guard therefore sees the changed diff digest and requires review/reapproval through the same workflow already used for other Staff Brief changes.

## Staff Brief surface

When a Staff Brief is generated, an admin bridge computes the Tactical Twin from the same team, opponent, sample size and language, then attaches it before the generated result is stored for display.

The Staff Brief shows a compact prepared-Twin block containing:

- baseline response;
- most robust response;
- number of switch candidates;
- all prepared state rows with decision, target response and coverage caution;
- shortened Twin SHA-256 digest;
- an explicit statement that approval freezes preparation but activates no tactical switch.

## Live Session handoff

A Live Session created from an approved Staff Brief already persists `staff_brief_snapshot_uuid` as its immutable origin.

v1.32 uses that existing link:

`Live Session -> approved Staff Brief snapshot -> frozen Tactical Twin attachment`

No Tactical Twin calculation runs when the Live Session page is opened.

The live surface resolves the origin snapshot, verifies the Staff Brief snapshot integrity and Twin attachment integrity, then displays the frozen response/switch reference.

This creates the foundation for a later live-confirmation layer while preserving the rule that switch candidates are not automatic actions.

## Guardrails

v1.32 must never:

- recalculate Tactical Twin during Staff Brief approval;
- recalculate Tactical Twin during Live Session handoff or live viewing;
- create another Twin persistence table;
- silently attach a Twin from another matchup;
- bypass Staff Brief version review when the Twin changes;
- invalidate legacy Staff Brief snapshots that contain no Twin;
- convert switch candidates into automatic response activation;
- change Plan A/B/C activation logic;
- claim that snapshot integrity is a cryptographic signature of staff identity.

## Exit criterion

For a generated Staff Brief, the exact Tactical Twin displayed to staff must be attached before approval. Approval must freeze the attachment inside the existing Staff Brief snapshot SHA-256 boundary. A changed Twin must appear in the Staff Brief structural version diff and therefore participate in the existing review/stale-approval guard. A Live Session created from that snapshot must be able to resolve and display the same frozen Twin through its `staff_brief_snapshot_uuid` without recalculation. Legacy snapshots without Twin preparation must remain integrity-valid. Dedicated regression tests must prove attachment integrity, tamper detection, version-diff tracking, matchup rejection and legacy compatibility.
