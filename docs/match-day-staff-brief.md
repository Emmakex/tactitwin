# v1.16 — Match-Day Staff Brief

## Purpose

v1.16 turns the already validated TactiTwin preparation stack into a compact match-day surface for coaches and analysts.

It does not add another tactical model. It composes existing Match Plan, Plan A/B/C, historical-evidence brief and evidence-conflict outputs into a single staff sheet that can be read quickly on screen or printed / saved as PDF through the browser.

## WordPress surface

A new submenu is available at:

`TactiTwin -> Staff Brief`

The staff selects:

- language;
- our team;
- opponent;
- recent-match sample size (1–10).

TactiTwin automatically prepares missing event data where available, then builds the existing precedent-aware Match Plan and Contingency Playbook before composing the Staff Brief.

## Pre-match reference context

The v1.16 Staff Brief is a **pre-match reference sheet**. Its Contingency Playbook is evaluated at:

- minute: 1;
- score: 0–0;
- manpower: 11v11.

This does not assert that the actual match will remain in that state. It establishes a deterministic neutral starting reference for Plan A/B/C.

The generated brief explicitly exposes this reference context.

Plan B is not automatically activated by the staff brief. Existing live-observation guardrails remain unchanged. Plan C remains governed by the existing score/manpower context rules.

## Composition sources

The pure composer `TactiTwin_Match_Day_Staff_Brief` receives:

1. a precedent-aware Match Plan;
2. a precedent-aware Contingency Playbook for the same team/opponent matchup.

It rejects mismatched inputs whose team or opponent source IDs differ.

## Output structure

### Header

- team vs opponent;
- Match Plan headline;
- pre-match reference context;
- initial active plan;
- event-sample coverage.

### Top tactical priorities

The first three existing Match Plan priorities are shown in their original order.

v1.16 does not recalculate or reorder priorities.

### Plan A / B / C

Each plan card keeps existing fields where available:

- plan ID/name;
- status and mode;
- purpose;
- scenario and risk score;
- trigger / activation rule;
- up to five existing instructions;
- up to four existing exit conditions.

The limits are presentation-only truncation. Source order is preserved.

### Historical evidence inside each plan

The Staff Brief reuses the already filtered Plan A/B/C evidence from v1.14/v1.15:

- primary precedent;
- alternative;
- suitability;
- evidence quality;
- opponent similarity;
- evidence-conflict state.

It never moves evidence from one destination plan to another.

### Material evidence watch

Material v1.15 conflict signals visible inside the plan-specific briefs are collected into one staff-attention section. Every signal retains its source Plan A/B/C attribution.

No new conflict score is created.

### Match checks

The sheet includes short existing Match Plan checklists for:

- opening 15 minutes;
- halftime.

### Decision tree

The existing Contingency Playbook decision tree is shown without modifying order or target plans.

## Print / Save as PDF

The WordPress surface provides a `Print / Save as PDF` button using the browser print dialog.

Print CSS hides WordPress administration chrome, technical payloads and on-screen controls so the resulting page is suitable for a staff meeting or bench reference.

v1.16 does not introduce a server-side PDF dependency.

## Method guardrails

The Staff Brief declares:

- `type=presentation-only-match-day-staff-brief`;
- `pre_match_reference_context=true`;
- `creates_new_tactical_score=false`;
- `reorders_priorities=false`;
- `changes_plan_activation=false`;
- `changes_evidence_order=false`;
- `changes_recommendations=false`.

The sheet is a presentation layer, not a second source of tactical truth.

## Security / WordPress behavior

The admin action requires:

- `manage_options` capability;
- WordPress nonce verification.

Results and errors use per-user transients, consistent with the existing TactiTwin admin flows.

## No schema change

v1.16 requires no database migration. It operates on existing imported match data and in-memory outputs from the current services.
