# v1.12 — Evidence Suitability Ranking

## Purpose

v1.12 adds an evidence-ordering layer after exact-context historical retrieval, v1.10 Evidence Quality and v1.11 Opponent Context Distance.

It answers one narrow question:

> Among historical precedents that are already valid for the current tactical context, which evidence is most suitable to inspect first?

Suitability is an evidence-navigation score. It is not a tactical score, recommendation score, win probability, treatment-effect estimate or coaching-quality score.

## Upstream eligibility remains unchanged

A historical precedent can enter the v1.12 candidate pool only after the existing exact-context rules have already accepted it.

For What-if retrieval, the hard context dimensions remain:

- minute band;
- score state;
- manpower state.

v1.12 does not add fuzzy matching and does not widen those boundaries.

For Match Plan, scenario identity remains preserved. Suitability can reorder historical contexts only inside the same current scenario; it cannot reorder the current Full Matchup Scan scenarios.

## Suitability formula

When all three components are available:

```text
suitability =
    0.45 × current scenario relevance
  + 0.35 × v1.10 evidence quality
  + 0.20 × v1.11 opponent similarity
```

The weights deliberately do not include the legacy v1.8 `retrieval_score` or v1.9 `support_order_score`.

Those legacy scores already contain historical maturity, sample volume and cleaner-share information. Reusing them inside v1.12 would double-count evidence-quality concepts that v1.10 now models explicitly.

## Missing components

Missing evidence is not silently imputed as zero.

If one component is unavailable, its weight is removed and the remaining weights are renormalized.

Example:

```text
scenario relevance = 80
quality = 80
opponent similarity = unavailable
```

Available weight is `0.45 + 0.35 = 0.80`, so:

```text
(80×0.45 + 80×0.35) / 0.80 = 80
```

The payload exposes:

- `available_weight`;
- `missing_components`;
- each component's base weight;
- each component's effective renormalized weight.

## What-if ranking

The What-if service retrieves up to 10 exact-context candidates before the final requested result limit is applied.

Pipeline:

1. exact-context v1.8 retrieval;
2. v1.10 evidence-quality attachment;
3. v1.11 opponent-similarity attachment;
4. v1.12 suitability scoring;
5. suitability ordering;
6. requested `precedent_limit` applied.

Each item retains:

- original `retrieval_score`;
- `original_retrieval_rank`;
- `suitability_rank`;
- `ranking_changed`;
- the complete independent v1.10 quality object;
- the complete independent v1.11 similarity object.

Tie-break order is:

1. suitability score;
2. legacy retrieval score;
3. original retrieval rank;
4. stable group key.

## Match Plan ranking

Each current scenario is kept in its original Full Matchup Scan order.

Within a scenario, up to 10 historical context candidates are enriched and suitability-ranked before `contexts_per_scenario` is applied.

Each context retains:

- original `support_order_score`;
- `original_support_rank`;
- `suitability_rank`;
- `ranking_changed`.

v1.12 does not aggregate historical metrics across contexts and does not move evidence between scenarios.

## Plan A/B/C

Plan A/B/C consumes the enriched What-if precedent items. Existing destination-plan and Plan-B scenario filters remain unchanged.

Suitability metadata therefore follows the historical evidence into the contingency workflow without changing:

- active-plan selection;
- score/manpower precedence;
- live-observation activation rules;
- tactical recommendations.

## Guardrails

v1.12 explicitly exposes:

```text
exact_context_only = true
fuzzy_context_fallback = false
changes_tactical_scores = false
changes_recommendations = false
changes_active_plan = false
changes_historical_metrics = false
may_reorder_historical_evidence = true
```

The combined score is allowed to answer only: **which already-eligible historical evidence should be inspected first?**

## Interpretation

A high suitability score means that, relative to other eligible historical precedents, the case combines:

- strong relevance of the current tactical scenario;
- stronger historical evidence quality;
- greater similarity between the historical and current opponent styles.

It does not mean that repeating the old intervention will cause the same outcome.

## Limitations

- Historical interventions remain observational and non-randomized.
- Opponent similarity is a style-context measure, not a team-strength rating.
- Event data cannot reproduce full tracking geometry or all off-ball structure.
- A candidate pool capped at 10 is a product shortlist, not an exhaustive statistical search.
- Suitability inherits uncertainty and missingness from v1.10 and v1.11.
- Exact-context filtering can intentionally return no evidence even when nearby contexts exist.

## No schema change

v1.12 is computed from existing in-memory precedent payloads and does not require a database migration.
