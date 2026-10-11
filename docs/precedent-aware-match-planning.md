# v1.9 — Precedent-Aware Match Planning

## Purpose

v1.9 moves TactiTwin's historical intervention memory from a standalone/What-if evidence surface into the Match Plan and Plan A/B/C workflow.

The key constraint remains unchanged: historical precedent is explanatory evidence, not an automatic tactical decision-maker.

## Pre-match Match Plan

A pre-match plan does not have one unique minute, score state or manpower state. Therefore v1.9 must not collapse all historical intervention groups for a scenario into one synthetic average.

Instead, `TactiTwin_Pre_Match_Precedent_Catalog`:

1. takes the v1.7 intervention library,
2. keeps only scenarios that are present in the current Full Matchup Scan,
3. preserves destination plan/mode, minute band, score state and manpower state for every group,
4. orders contexts only so better-supported samples are easier to inspect,
5. never aggregates metric deltas across incompatible contexts.

The resulting Match Plan response adds:

```text
historical_precedent_catalog
```

Each current scenario can therefore expose several separate historical contexts, for example:

```text
Build-up vs High Press
  01-30 | drawing | equal | -> Plan B
  31-60 | drawing | equal | -> Plan B
  61-90 | leading | equal | -> Plan A
  61-90 | trailing | inferior | -> Plan C
```

The metric medians remain those of each v1.7 context group. v1.9 does not recompute one cross-context median.

## Support ordering

Within one current scenario, historical context groups are ordered using only sample-support properties:

- 50% maturity label,
- 30% signal-case volume,
- 20% cleaner/comparable share.

This score is named `support_order_score` deliberately. It is not an intervention-effect score and does not imply that the first context is tactically superior.

## Exact-context Plan A/B/C evidence

The Contingency Playbook already has an exact current context:

- minute,
- score state,
- manpower state.

v1.9 therefore reuses the exact-context v1.8 retrieval result and distributes those historical groups into Plan A/B/C according to their historical destination plan.

Rules:

- Plan A receives only historical transitions whose `to_plan` is `A`.
- Plan C receives only historical transitions whose `to_plan` is `C`.
- Plan B receives only historical transitions whose `to_plan` is `B` **and** whose scenario matches the currently relevant Plan B scenario.

For a live Playbook, the active live-observation Plan B scenario takes precedence over the pre-match armed Plan B scenario when filtering precedent evidence.

This is important because a live observation can legitimately activate a different Plan B scenario from the one that had the highest pre-match risk.

## REST surfaces

### Match Plan

`GET /wp-json/tactitwin/v1/planning/match-plan`

New optional argument:

```text
precedent_context_limit=1..10
```

Default: `3` contexts per current scenario.

The response adds `historical_precedent_catalog`.

### Contingency Playbook

`GET /wp-json/tactitwin/v1/planning/contingency-playbook`

New optional argument:

```text
precedent_limit=1..10
```

Default: `5`.

The response adds top-level `historical_precedents` and a `historical_precedents` block inside each Plan A/B/C.

### Live Playbook

`POST /wp-json/tactitwin/v1/planning/live-playbook`

Also accepts `precedent_limit=1..10` and returns the same Plan A/B/C precedent distribution after live observation has been applied.

## WordPress administration

v1.9 adds a non-invasive admin display layer:

- Match Plan shows historical context groups under the existing plan.
- Live Playbook shows exact-context evidence separately for Plan A, B and C.

The existing Match Plan and Live Playbook renderers are not rewritten; the new panel is appended from `TactiTwin_Precedent_Planning_Admin`.

## Immutable guardrails

Every v1.9 evidence object states:

```text
reweights_tactical_scores = false
changes_recommendations = false
```

Additional Match Plan guardrail:

```text
cross_context_metric_aggregation = false
```

Attaching historical precedent must not change:

- Match Plan priority scores,
- Match Plan phases,
- decision triggers,
- Contingency Playbook active plan,
- Plan A/B/C instructions,
- live-observation activation,
- existing recommendations.

## Method boundary

Historical interventions were chosen by coaches/staff rather than randomized. Matching stored context dimensions does not control for all variables, including:

- opponent quality,
- exact scoreline inside the same score state,
- player identities,
- injuries,
- fatigue,
- substitutions,
- competition incentives,
- off-ball structural changes not represented in event data.

v1.9 therefore supports questions such as:

> "What happened in our previous comparable interventions?"

but not:

> "What will this intervention cause?"

## Regression requirements

v1.9 tests must prove that:

1. pre-match scenario contexts remain separate,
2. scenarios outside the current matchup scan are excluded,
3. Plan A/B/C only receives matching historical destination plans,
4. Plan B additionally requires the matching current scenario,
5. a live-activated Plan B scenario overrides the pre-match B scenario for precedent filtering,
6. recommendations and active-plan state remain unchanged after evidence attachment,
7. language changes labels but not grouping/order logic.

## Schema impact

None.

v1.9 reuses:

- v1.7 context-matched intervention groups,
- v1.8 exact-context precedent retrieval,
- the existing Match Plan,
- Contingency Playbook,
- Live Playbook.
