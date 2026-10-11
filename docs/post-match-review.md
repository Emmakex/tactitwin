# Post-Match Review

## Purpose

v1.2 converts the persistent Live Session timeline into a descriptive post-match audit.

The review answers three separate questions:

1. **What was prepared?**
2. **What did the staff explicitly observe?**
3. **What did TactiTwin actually activate?**

It deliberately does not score whether a coach was “right” or “wrong” and does not claim that a plan change caused the final result.

## Inputs

The review is computed from a stored Live Session and its immutable snapshots.

Each snapshot already contains:

- minute
- score
- player counts
- active Plan A/B/C
- active mode
- selected live scenario
- manual observations
- staff note
- full playbook JSON shown at that moment

## Prepared layer

The first stored playbook is used to reconstruct the prepared state:

- number of Plan A instructions
- pre-match priority scenarios
- primary armed Plan B
- Plan B risk, mode and trigger

This is historical evidence from the saved snapshot, not a newly recomputed match plan.

## Observed layer

All supported manual observation flags are counted across snapshots:

- high press
- low/compact block
- transition threat after loss
- side bias
- weak-side availability

For each mapped scenario the review records:

- number of snapshots where it was observed
- first observed minute
- distinct observed scenarios
- number of snapshots containing at least one supported observation

If multiple behaviours were checked in one snapshot, all remain part of the observed layer even though only one may have been selected as the active Plan B priority.

## Activated layer

The review reconstructs what the engine actually used:

- Plan A/B/C snapshot counts
- activated Plan B scenarios
- first activation minute by Plan B scenario
- first Plan B minute
- Plan C modes
- first Plan C minute
- all plan/mode/scenario transitions

A mode or scenario change inside Plan B counts as a meaningful transition even when the plan letter remains B.

## Prepared vs observed vs activated classification

The primary pre-match Plan B is classified descriptively:

- `prepared-and-activated`
- `prepared-observed-not-activated`
- `different-adaptation-activated`
- `prepared-not-observed`
- `adaptation-without-primary-plan-b`
- `no-primary-plan-b`

The review also surfaces:

- primary Plan B observed: yes/no
- primary Plan B activated: yes/no
- observed scenarios outside the prepared priority set
- activated scenarios outside the prepared priority set
- whether Plan C was used

These labels describe the stored timeline. They are not performance grades.

## Sampling warnings

A live timeline is only as complete as the staff submissions.

The review emits warnings when:

- fewer than three snapshots exist
- the first snapshot is after minute 20
- a gap between snapshots exceeds 30 minutes
- the session is still open

Sampling status is:

- `good`
- `caution`
- `limited`

A sampling warning means tactical events may be missing from the stored timeline; it does not mean the staff performed poorly.

## Staff notes

All non-empty staff notes are preserved with minute and snapshot ID. They are shown alongside the structured review but are not converted into hidden scores.

## WordPress UI

New submenu:

`TactiTwin -> Session Review`

The workspace provides:

- recent session browser
- final stored score/context
- prepared Plan B
- number of distinct observed behaviours
- transition count
- sampling status
- prepared/observed/activated interpretation
- observed scenario list with first-seen minute
- activated Plan B scenario list with first activation minute
- unprepared observed scenarios
- decision-transition table
- staff notes
- technical JSON evidence

Active sessions can also be reviewed, but the result is marked provisional through a sampling warning.

## REST API

`GET /wp-json/tactitwin/v1/planning/live-sessions/{session_uuid}/review`

Optional:

- `lang=es|en`

The default language comes from the stored session.

## Method boundary

The review is a descriptive audit of prepared, observed and activated tactical states.

It does **not** estimate causal effect on match outcome. It does **not** fill in periods where the staff did not submit snapshots. Manual observations remain manual evidence unless a future source explicitly identifies automated tracking/video input.
