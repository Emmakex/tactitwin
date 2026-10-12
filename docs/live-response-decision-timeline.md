# v1.34 — Live Response Decision Timeline

## Goal

v1.34 reconstructs the Tactical Twin response decisions that staff actually recorded during a Live Session.

It closes the loop between:

- the approved frozen Tactical Twin;
- the prepared branch for the current match state;
- the staff confirmation / rejection / deferral recorded by v1.33;
- the active Plan A/B/C that remained in force at that same snapshot.

The timeline is descriptive and append-only. It never invents a decision between staff submissions.

## Source of truth

The only source is the existing Live Session snapshot history.

A row is included only when the stored playbook contains a valid `live_twin_switch_review` with `review_id=live-twin-switch-confirmation`.

v1.34 does not recalculate the Tactical Twin, resolve the state again or reinterpret an old decision with newer code.

## Timeline row

Each recorded decision exposes:

- Live Session snapshot ID;
- minute;
- score;
- manpower state;
- prepared branch and match type;
- prepared response decision and target;
- state-fit margin and coverage caution;
- staff decision status;
- selected response when one was explicitly selected;
- staff note;
- active Plan A/B/C + mode at that same snapshot;
- approved Staff Brief / frozen Twin provenance.

## Summary

The timeline reports counts for:

- confirmed prepared switches;
- confirmed prepared baseline decisions;
- rejected prepared switches;
- confirmed baseline decisions;
- deferred reviews;
- reviews with no prepared branch.

It also reports first/last decision minute, latest decision status, selected-response frequency and branch-review frequency.

These are descriptive counts only. They are not coaching-quality or effectiveness scores.

## WordPress surface

The Live Session page displays a dedicated **Response Decision Timeline** below the existing live workflow.

It deliberately remains separate from the existing A/B/C tactical timeline so staff can compare:

`Twin response decision` vs `Plan A/B/C actually active`

without treating them as the same concept.

## Guardrails

v1.34 must never:

- infer a response decision for a snapshot that contains no recorded v1.33 review;
- infer that a response remained selected between staff submissions;
- rewrite Plan A/B/C history;
- recalculate the frozen Tactical Twin;
- re-resolve an old state through current code;
- turn decision counts into tactical effectiveness;
- predict match outcomes.

## Exit criterion

Given an append-only Live Session history containing several v1.33 reviews, v1.34 must reconstruct them in minute + snapshot order, preserve the exact prepared branch / staff decision / selected response / active Plan A/B/C values recorded at each point, expose descriptive summary counts and remain language-neutral in its reconstructed machine data. Snapshots without a recorded Twin review must be ignored rather than interpreted. Dedicated regression tests must prove ordering, counts, source immutability, Plan A/B/C separation and ES/EN invariance.
