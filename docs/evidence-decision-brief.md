# v1.14 — Evidence Decision Brief

## Purpose

v1.14 converts the already eligible, already ranked and already stress-tested historical evidence into a compact staff-facing brief.

It answers five descriptive questions:

1. Which historical precedent is currently first in the existing v1.12 evidence order?
2. What is the strongest visible alternative?
3. Why does the primary precedent fit the current preparation context?
4. What historical signals support it?
5. What conditions or evidence limitations could make it a poor analogy?

v1.14 does **not** create another ranking model.

## Upstream dependencies

The brief consumes only fields already produced by earlier layers:

- v1.8 exact-context historical precedent retrieval;
- v1.10 evidence quality;
- v1.11 opponent-context similarity;
- v1.12 evidence suitability and baseline order;
- v1.13 ranking robustness.

The first item in the existing v1.12 order becomes the primary precedent. The second visible item becomes the alternative. v1.14 never changes those positions.

## Brief structure

Each brief exposes:

- `primary`
  - group/scenario identity;
  - historical destination plan/mode;
  - minute/score/manpower context;
  - scenario relevance;
  - suitability;
  - evidence quality;
  - opponent similarity;
  - v1.13 rank range and top-rank frequency;
  - concise `why_it_fits` statements;
  - historical sample maturity and cleaner/comparable counts;
  - up to three historical median deltas;
  - precedent-specific invalidation/caution flags.
- `alternative`
  - same auditable evidence fields as the primary precedent.
- `comparison`
  - suitability gap;
  - whether the alternative becomes first in any transparent v1.13 weight profile;
  - descriptive alternative class: `strong`, `plausible`, or `reserve`.
- `ranking_context`
  - overall v1.13 robustness level;
  - top stability;
  - baseline top margin;
  - number of unique leaders across weight profiles.
- `what_could_invalidate`
  - combined evidence/context limitations that staff should keep visible.

## Alternative classification

The alternative classification is descriptive navigation only.

- `strong`: the alternative is first under at least one v1.13 weight profile, or its suitability is within 8 points of the primary;
- `plausible`: it is within 15 suitability points of the primary;
- `reserve`: otherwise.

This label does not activate a plan and does not create a recommendation.

## Invalidation / caution flags

v1.14 surfaces existing limitations instead of hiding them. Examples include:

- evidence quality below 50/100;
- opponent similarity below 50/100;
- missing suitability components;
- fewer than half of context-comparable historical cases classified as cleaner;
- candidate losing first place under some transparent v1.13 weight profiles;
- overall shortlist sensitivity to weight changes;
- live minute/score/manpower context moving away from the exact stored context;
- uncaptured differences in personnel, fatigue, injuries, competition incentives or off-ball structure;
- historical association remaining non-causal.

## Match Plan behavior

The Match Plan receives one independent brief per current tactical scenario.

Scenario order is untouched. A scenario brief can only summarize the historical contexts already present inside that scenario.

## Plan A/B/C behavior

Plan A/B/C briefs are built **after** v1.9 destination-plan filtering.

Therefore:

- Plan A never borrows a primary precedent whose historical destination was Plan B or C;
- Plan C never borrows a Plan A/B precedent;
- Plan B still requires the same currently armed/live scenario when that filter applies.

The brief is generated from the filtered list without changing its v1.12 order.

## Method guardrails

v1.14 explicitly declares:

- `uses_existing_rank_order=true`
- `creates_new_tactical_score=false`
- `changes_evidence_order=false`
- `changes_tactical_scores=false`
- `changes_recommendations=false`
- `changes_active_plan=false`
- `causal_model=false`

The brief is a communication layer for staff, not an autonomous coaching decision engine.

## No schema change

v1.14 operates entirely on in-memory historical-evidence payloads and requires no database migration.
