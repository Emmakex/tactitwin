# v1.8 — Historical Precedent Retrieval

## Purpose

v1.8 connects the cross-match intervention memory introduced in v1.7 to the What-if Lab.

For a current hypothetical match state, TactiTwin can now retrieve historical intervention groups from the same team that occurred in the same recorded context.

The precedents are an **explanatory evidence layer**. They do not alter the existing What-if risk/opportunity scores, recommendations or decision triggers.

## Exact context matching

The current What-if hypothesis is converted into three matching dimensions:

- minute band: `01-30`, `31-60`, `61-90`, `91-120`;
- score state: `leading`, `drawing`, `trailing`;
- manpower state: `superior`, `equal`, `inferior`.

Only v1.7 groups matching all three dimensions are candidates.

v1.8 deliberately has **no fuzzy fallback**. A 61-90 precedent is not used for a 31-60 hypothesis, and an 11v10 precedent is not used for 11v11.

## Scenario relevance

Exact-context candidates are joined to the current Full Matchup Scan by `scenario_id`.

A historical group that is not represented in the current scan is not returned as a precedent.

The current scenario relevance is:

`max(current risk score, current opportunity score)`

This value only helps order precedents.

## Retrieval ranking

Each exact-context candidate receives a retrieval score composed of:

- 60% current scenario relevance;
- 20% historical sample maturity;
- 10% historical signal volume;
- 10% cleaner-case share among context-comparable cases.

This is a retrieval/ranking score only. It is not a tactical-effect score and does not modify the scenario engine.

## Returned evidence

For each precedent TactiTwin exposes:

- scenario ID/name;
- destination Plan A/B/C;
- destination mode;
- exact matched context;
- current risk/opportunity and scan rank;
- retrieval score;
- historical sample maturity/basis;
- v1.7 aggregated focus metrics;
- historical case count.

The existing aggregate metric meanings are preserved. v1.8 does not translate an upward/downward delta into good/bad.

## What-if integration

`TactiTwin_Precedent_Aware_What_If_Service` reuses one set of current matchup profiles to build:

1. Full Matchup Scan;
2. Context What-if;
3. v1.7 intervention library;
4. v1.8 exact-context precedent retrieval.

The final What-if response adds:

`historical_precedents`

The existing risk, opportunity, recommendations and triggers remain unchanged by the precedent layer.

REST continues to use:

`GET /wp-json/tactitwin/v1/planning/what-if`

A new optional `precedent_limit` parameter controls how many historical groups are returned (1–10, default 5).

## WordPress surface

The existing `TactiTwin -> What-if Lab` now displays a Historical Tactical Precedents section showing:

- scenario / historical intervention;
- destination plan/mode;
- current scenario relevance;
- historical sample maturity;
- cleaner/context-comparable case counts;
- selected aggregate median deltas and descriptive direction.

If no exact precedents exist, the UI says so rather than broadening the match automatically.

## Guardrails

The result contains explicit method flags:

- `reweights_tactical_scores = false`;
- `changes_recommendations = false`.

Historical precedent retrieval therefore cannot silently feed back into tactical scoring in v1.8.

## Method boundaries

Historical precedents are observational and selected rather than randomized.

Even an exact match on minute band, score state and manpower state can differ in:

- opponent quality;
- exact scoreline;
- player identities and substitutions;
- fatigue / recent load;
- injuries;
- pitch and weather;
- tournament incentives;
- unobserved tactical structure.

Repeated historical association is useful decision-support evidence, but it is not a causal estimate of what a tactical intervention will do in the current match.
