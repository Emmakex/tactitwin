# v1.13 — Evidence Ranking Robustness

## Purpose

v1.13 adds a deterministic sensitivity diagnostic on top of the v1.12 Evidence Suitability Ranking.

It answers:

> Does the same historical precedent remain the best evidence when the transparent v1.12 component weights are changed within reasonable alternative profiles?

The baseline v1.12 order remains authoritative for evidence presentation. v1.13 does not replace or rewrite it.

## Why this layer exists

v1.12 uses a deliberate but still product-defined weighting:

- 45% current scenario relevance;
- 35% evidence quality;
- 20% opponent similarity.

A precedent can rank first because it is genuinely dominant across all three dimensions, or because a close contest happens to favor the chosen baseline weights.

v1.13 exposes that distinction instead of hiding it.

## Weight profiles

The sensitivity analysis evaluates five transparent profiles:

| Profile | Relevance | Quality | Similarity |
| --- | ---: | ---: | ---: |
| Baseline | 45% | 35% | 20% |
| Balanced | 34% | 33% | 33% |
| Relevance-heavy | 60% | 25% | 15% |
| Quality-heavy | 30% | 50% | 20% |
| Similarity-heavy | 35% | 25% | 40% |

These are deterministic scenarios, not random Monte Carlo draws.

## Missing components

The same missing-data rule used by v1.12 applies inside every profile:

- unavailable components are excluded;
- remaining weights are renormalized;
- missing similarity is not treated as zero similarity.

This prevents a missing provider/profile from becoming an artificial tactical penalty.

## What-if analysis

The complete v1.12 shortlist of up to 10 exact-context candidates is analyzed before the user's final `precedent_limit` is applied.

For every profile v1.13 recomputes an alternative evidence-ordering score from the same three component values. It records:

- top candidate;
- top score;
- runner-up candidate;
- runner-up score.

For every candidate it records:

- best rank;
- worst rank;
- rank spread;
- mean rank;
- top-rank count;
- top-rank frequency;
- rank under each profile.

The baseline item order in the returned payload is not changed.

## Match Plan analysis

Robustness is calculated independently inside each current Match Plan scenario.

Scenario order remains the Full Matchup Scan order.

Historical contexts inside a scenario are first suitability-ranked by v1.12. v1.13 then tests that baseline order across the five weight profiles, annotates the candidates and only afterwards applies `contexts_per_scenario`.

## Top stability

`top_stability_pct` is the percentage of configured weight profiles in which the baseline v1.12 first-ranked candidate remains first.

With five profiles, the possible values are 0%, 20%, 40%, 60%, 80% and 100%.

The payload also exposes:

- `baseline_top_key`;
- `baseline_top_stability_count`;
- `unique_top_count`;
- `baseline_top_margin`;
- all profile-specific top/runner-up results.

## Robustness labels

Current deterministic labels are:

- `single-candidate`: only one evidence candidate exists;
- `very-stable`: baseline top remains top in 100% of profiles and no alternative candidate becomes first;
- `stable`: baseline top remains first in at least 80% of profiles;
- `moderate`: baseline top remains first in at least 60% of profiles;
- `sensitive`: baseline top remains first in fewer than 60% of profiles.

These labels describe ranking sensitivity only. They do not describe tactical confidence or causal certainty.

## Baseline margin

`baseline_top_margin` is the difference between the first and second recomputed baseline suitability values.

A high top-stability percentage with a very small margin can still deserve human inspection: the ordering may be stable across the configured profiles while the top two cases remain numerically close.

## Guardrails

v1.13 explicitly exposes:

```text
baseline_order_is_preserved = true
changes_suitability_scores = false
changes_tactical_scores = false
changes_recommendations = false
changes_active_plan = false
changes_historical_metrics = false
```

It does not:

- widen exact-context matching;
- introduce fuzzy fallback;
- modify v1.12 suitability scores;
- change scenario order;
- change Plan A/B/C activation;
- convert observational evidence into a causal effect estimate.

## Interpretation examples

### Stable

If one precedent is first under baseline, balanced, relevance-heavy, quality-heavy and similarity-heavy profiles:

```text
top_stability_pct = 100
robustness_level = very-stable
```

This means its evidence priority is not dependent on the specific baseline weighting among the tested alternatives.

### Sensitive

If the baseline top wins only two of five profiles and different candidates win when relevance or similarity receives more weight:

```text
top_stability_pct = 40
robustness_level = sensitive
unique_top_count = 3
```

The staff can then inspect the competing precedents rather than treating the first row as decisively superior.

## Limitations

- Five predefined profiles do not explore every possible weighting.
- The profiles are product-defined sensitivity scenarios, not statistically estimated coefficients.
- Robustness inherits all uncertainty and missingness from v1.10, v1.11 and v1.12.
- Stable ranking does not prove that the historical intervention will work again.
- Sensitive ranking is not evidence that all candidates are equally useful; it only indicates dependence on weighting assumptions.

## No schema change

v1.13 operates on the in-memory v1.12 shortlist and requires no database migration.
