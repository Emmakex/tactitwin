# v1.33 — Live Twin Switch Confirmation

## Goal

v1.33 connects the approved frozen Tactical Twin to the real Live Session state without turning the Twin into an automatic control system.

The workflow is:

`Live Session state -> resolve prepared frozen-Twin branch -> staff review -> append-only confirmation snapshot`

No Tactical Twin calculation runs during this process.

## Prepared-state resolution

The resolver deliberately refuses to invent a branch when the real state falls outside the preparation catalog.

Deterministic mapping:

- any numerical superiority -> `numerical-superiority` as a structural manpower analogy;
- any numerical inferiority -> `numerical-inferiority` as a structural manpower analogy;
- level score through minute 30 -> `opening-neutral`;
- level score from minute 46 through 74 -> `second-half-neutral`;
- level score from minute 75 onward -> `late-draw`;
- exactly one goal ahead from minute 60 onward -> `protect-lead`;
- exactly one goal behind from minute 60 onward -> `chase-game`;
- every other live state -> no prepared branch.

The resolver labels exact matches to the original prepared hypothesis separately from windowed prepared-state matches. Manpower mappings are explicitly labelled `structural-manpower-analogy` because the frozen branch was prepared at a specific minute/score but is being reused for its structural 11v10/10v11 logic.

## Staff decisions

For a prepared switch candidate, staff can record:

- `confirm-prepared` — accept the prepared target response;
- `retain-baseline` — reject the prepared switch and retain the v1.27 baseline response;
- `defer` — record no response selection yet.

For a branch whose preparation already says `retain-baseline`, staff can confirm that baseline or defer.

If there is no prepared branch, TactiTwin records `no-prepared-branch` and selects no response even if a confirm action was supplied.

## Persistence and provenance

v1.33 creates no new table.

The confirmation bridge takes the latest Live Session snapshot, keeps its existing Contingency Playbook and `active_plan` unchanged, adds a `live_twin_switch_review` payload, then uses the existing `TactiTwin_Live_Session_Service::append_snapshot()` path.

The persisted review carries:

- approved Staff Brief snapshot UUID;
- approved Staff Brief SHA-256;
- frozen Tactical Twin SHA-256;
- reviewer user ID;
- review timestamp;
- exact live minute, score and manpower state;
- resolved prepared branch;
- prepared target/baseline response;
- compatibility margin and validation signals;
- staff decision and note.

Because it is a new Live Session snapshot, previous match-day state is never overwritten.

## Relationship with Plan A/B/C

Response confirmation and Plan A/B/C are deliberately separate layers.

v1.33 does not:

- change `active_plan`;
- activate Plan B;
- suppress or replace Plan C;
- alter existing live-observation rules;
- rewrite the frozen Twin;
- convert response fit into probability or confidence.

Existing Plan A/B/C behavior remains governed by the Contingency Playbook and explicit live observations.

## WordPress surface

The Live Session page shows:

- current real match state;
- matching prepared branch or `no prepared branch`;
- match type: exact, windowed or structural manpower analogy;
- prepared retain/switch decision;
- target and baseline responses;
- state-fit separation margin;
- historical-coverage caution;
- existing prepared validation signals;
- latest recorded Twin decision;
- explicit staff decision controls while the session is active.

The frozen Twin SHA and Staff Brief snapshot UUID remain visible provenance anchors.

## Guardrails

- No branch is forced for an unprepared state.
- No response is activated automatically.
- A staff confirmation is a recorded decision, not proof of causal effectiveness.
- No Tactical Twin recalculation occurs in live review.
- No existing Live Session snapshot is edited.
- No Plan A/B/C rule is changed.
- The frozen approved preparation remains immutable.

## Exit criterion

Given a Live Session created from an approved Staff Brief containing a frozen Twin, TactiTwin can resolve the latest real match state against the prepared branch catalog, display the exact frozen switch candidate and allow staff to confirm, reject or defer it. The decision must persist as a new append-only Live Session snapshot with frozen-Twin provenance while leaving the active Plan A/B/C and the approved Twin unchanged. States outside the prepared catalog must remain explicitly unsupported rather than being mapped fuzzily.
