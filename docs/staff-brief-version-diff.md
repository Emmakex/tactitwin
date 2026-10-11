# v1.18 — Staff Brief Version Diff

## Goal

v1.18 makes repeated pre-match approvals auditable.

When a new Staff Brief is generated for a team/opponent pairing that already has an approved v1.17 snapshot, TactiTwin compares the new generated brief with the most recent approved version **before** the staff approves again.

It can also compare two integrity-valid approved snapshots of the same matchup.

## What is compared

The diff is deliberately structural and limited to fields already visible or operational in the Staff Brief:

- headline;
- effective coverage/sample status;
- top-priority order and membership;
- priority score/instruction changes;
- Plan A/B/C status, mode and scenario;
- plan risk score, trigger and activation rule;
- visible plan instructions;
- primary and alternative historical precedent group keys;
- evidence conflict state and signal IDs;
- opening-15-minute checklist;
- halftime checklist;
- decision tree;
- material evidence-watch signals.

## Output

`TactiTwin_Staff_Brief_Version_Diff` returns:

- `status = unchanged | changed`;
- stable `change` IDs;
- `change_count` as a literal number of reported field/group changes;
- changed sections;
- before/after values;
- plan/scenario context where applicable;
- metadata identifying the previous approved snapshot and the new generated/approved version.

`change_count` is **not a score or severity metric**. Five changes are not inherently better/worse or more important than two changes.

## Pre-approval workflow

On `TactiTwin > Staff Brief`:

1. generate a new Staff Brief;
2. TactiTwin finds the latest approved snapshot for the same team/opponent;
3. snapshot integrity is verified;
4. the generated brief is structurally compared with that version;
5. the staff sees **Changes since last approval** before clicking approve;
6. after approval, the same comparison can be reproduced approved-to-approved.

The comparison never mutates either version.

## Same-matchup rule

A diff is allowed only when team and opponent IDs match exactly.

TactiTwin does not compare unrelated matches merely because they have similar scenarios or tactical profiles. Historical tactical similarity belongs to the evidence stack; version diff is an audit/versioning feature.

## Language rule

Change IDs, sections, before/after values and counts are language invariant.

English/Spanish only changes display labels.

## Guardrails

v1.18:

- creates no change score;
- does not classify changes as tactically good/bad;
- does not recalculate scenarios or profiles;
- does not modify Match Plan priorities;
- does not modify historical evidence ordering;
- does not activate Plan A/B/C;
- does not mutate approved snapshots;
- does not replace the v1.17 SHA-256 integrity check.

It answers one question only:

**“What changed in the Staff Brief since the last version we approved?”**
