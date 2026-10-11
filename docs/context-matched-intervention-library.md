# v1.7 — Context-Matched Intervention Library

## Purpose

v1.7 turns the per-match transition review introduced in v1.5/v1.6 into a cross-match tactical memory for one team.

The library answers a descriptive question:

> When we made the same kind of tactical intervention in materially similar recorded contexts, what event-level deltas tended to appear around the intervention?

It does **not** answer:

> Did the intervention cause the change?

## Source data

The library reuses existing persisted data and creates no new source of truth:

1. closed Live Sessions;
2. linked imported matches;
3. v1.5 symmetric before/after transition deltas;
4. v1.6 context and interpretability annotations.

No schema change is required.

## Matching dimensions

Historical interventions are only placed in the same group when all of the following dimensions match:

- scenario ID;
- destination Plan A/B/C;
- destination mode;
- minute band (`01-30`, `31-60`, `61-90`, `91-120`);
- score state (`leading`, `drawing`, `trailing`);
- manpower state (`superior`, `equal`, `inferior`).

This deliberately favors smaller, more coherent groups over larger but misleading samples.

## Evidence tiers

Each intervention retains the v1.6 interpretability class.

### Primary sample

If a group contains at least two `cleaner` comparisons, aggregate signals use only those cases.

### Fallback sample

If fewer than two cleaner cases exist but at least two context-comparable cases exist, the aggregate can use:

- `cleaner`;
- `confounded` comparisons affected by substitutions/personnel changes.

The result is explicitly labelled as a fallback basis.

### Excluded from aggregate deltas

These cases remain visible but do not enter the signal calculation:

- `highly-confounded`;
- `insufficient`;
- any row where `context_comparable=false`.

A goal, dismissal, material score/manpower change or insufficient event coverage therefore cannot distort the aggregate median.

## Aggregated metrics

For each scenario-focus metric in the selected signal sample, v1.7 reports:

- observation count;
- median delta;
- mean delta;
- minimum and maximum delta;
- positive / negative / flat counts;
- positive / negative percentages;
- descriptive direction:
  - `mostly-up`;
  - `mostly-down`;
  - `mixed`;
  - `insufficient`.

The engine intentionally does not translate positive/negative into good/bad automatically. For some metrics a lower value may be tactically desirable, while others are contextual rather than directional objectives.

## Sample maturity

Maturity describes only sample volume:

- `insufficient`: 0–1 signal cases;
- `emerging`: 2–3;
- `developing`: 4–5;
- `established`: 6+.

These labels are **not statistical confidence intervals** and are not claims of effectiveness.

## WordPress surface

`TactiTwin -> Intervention Library`

The workspace supports:

- team selection;
- optional scenario filtering;
- number of closed linked sessions processed;
- number of context-matched groups;
- comparable vs excluded intervention counts;
- sample maturity and signal basis;
- metric medians and directional consistency;
- technical case-level evidence.

REST:

`GET /wp-json/tactitwin/v1/planning/intervention-library`

Parameters:

- `team_id` required;
- `scenario_id` optional;
- `lang` optional;
- `limit` optional, capped at 100 sessions.

## Method boundaries

The library remains observational. Even matched groups can differ in:

- opponent quality;
- exact scoreline despite the same leading/drawing/trailing state;
- fatigue and match load;
- injuries;
- substitutions;
- opponent tactical changes;
- pitch/weather;
- tournament incentives;
- unobserved off-ball structure.

Coach interventions are also selected rather than randomized. Repetition across comparable historical cases is useful tactical evidence, but it is not a causal treatment-effect estimate.
