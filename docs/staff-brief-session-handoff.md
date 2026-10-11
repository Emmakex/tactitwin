# v1.17 — Match-Day Brief Snapshot & Session Handoff

## Goal

v1.17 connects the pre-match Match-Day Staff Brief to the persistent Live Session timeline without recalculating what the staff approved.

The rule is simple:

`generate -> review -> explicit approval -> immutable snapshot -> Live Session handoff`

A Live Session created from an approved Staff Brief starts from the exact frozen Contingency Playbook that was present when the brief was approved.

## Approval snapshot

Approval creates a new immutable `match-day-staff-brief-snapshot` with:

- snapshot UUID;
- team and opponent IDs;
- language;
- sample size;
- approval user/time;
- plugin version;
- compact Staff Brief presentation payload;
- exact source Match Plan;
- exact source Contingency Playbook;
- SHA-256 digest over canonicalized snapshot content.

The snapshot model has no edit/update operation. A later approval creates another version rather than changing the previous one.

## Integrity

`content_sha256` is computed from the canonicalized content payload using SHA-256.

Before handoff, TactiTwin recalculates the digest. If the stored content no longer matches the digest, handoff is rejected.

The integrity check is an audit/tamper guard. It is not a cryptographic signature or identity proof.

## Persistence

Schema version becomes `0.3.0`.

New table:

`tactitwin_staff_brief_snapshots`

Stores immutable approved snapshot JSON plus searchable metadata.

`live_sessions` gains nullable `staff_brief_snapshot_uuid`.

That field is unique when populated, making one approved Staff Brief the origin of at most one Live Session. Existing/manual Live Sessions keep a NULL origin and remain fully supported.

## Handoff behavior

When staff chooses **Start / open Live Session** from an approved snapshot:

1. the snapshot is loaded;
2. SHA-256 integrity is verified;
3. the exact frozen playbook is extracted;
4. a Live Session is created for the same team/opponent;
5. the session stores the Staff Brief snapshot UUID as immutable origin;
6. the frozen playbook is written as the first append-only Live Session snapshot.

No tactical engine, opponent profile, evidence rank, recommendation or Plan A/B/C rule is recalculated during handoff.

Repeated handoff requests are idempotent. If a Live Session already exists for the approved snapshot, TactiTwin opens the existing session rather than creating another one.

## WordPress workflow

On `TactiTwin > Staff Brief`:

- generate and review the brief as in v1.16;
- click **Approve and freeze this brief**;
- TactiTwin shows the snapshot UUID and SHA-256;
- click **Start / open Live Session**.

Recent approved snapshots remain listed independently of the temporary generated-brief transient, so an approved version survives page/transient expiry.

On `TactiTwin > Live Sessions`, sessions created through v1.17 show their approved Staff Brief origin and integrity status.

## Guardrails

v1.17:

- does not change tactical scores;
- does not reorder Match Plan priorities;
- does not reorder historical evidence;
- does not activate Plan B or C;
- does not regenerate the approved Match Plan or Playbook during handoff;
- does not mutate an approved snapshot;
- does not retroactively change the first Live Session state when code/data later changes.

The initial frozen playbook still uses the neutral pre-match reference context from v1.16: minute 1, 0–0, 11v11.

## Why this matters

Before v1.17, Staff Brief and Live Session were adjacent surfaces but not the same historical artifact. A later recalculation could theoretically differ from what staff had seen pre-match.

v1.17 closes that audit gap: post-match review can trace the session back to the exact pre-match plan that was explicitly approved.
