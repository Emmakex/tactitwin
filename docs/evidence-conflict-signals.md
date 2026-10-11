# v1.15 — Evidence Conflict Signals

## Purpose

v1.15 adds a transparent conflict/tension layer on top of the v1.14 Evidence Decision Brief.

It does not ask which precedent should rank first. v1.12 already provides the baseline evidence order, v1.13 tests the sensitivity of that order, and v1.14 summarizes the result for staff.

v1.15 asks a narrower question:

> Are the existing evidence dimensions telling materially different stories that the staff should notice before treating the historical analogy as straightforward?

The output is a set of named signals, not a conflict score.

## Signals

### 1. High quality / low opponent similarity

`high-quality-low-similarity`

Triggered when:

- evidence quality >= 70/100; and
- opponent-context similarity < 45/100.

Interpretation: the historical evidence base can be relatively strong while the historical opponent is a weak tactical analogy for the current rival.

Severity: `material`.

### 2. High opponent similarity / low quality

`high-similarity-low-quality`

Triggered when:

- opponent-context similarity >= 70/100; and
- evidence quality < 45/100.

Interpretation: the historical opponent may look tactically similar, but the underlying historical evidence is weak or poorly supported.

Severity: `material`.

### 3. High suitability / sensitive ranking

`high-suitability-sensitive-ranking`

Triggered when suitability is >= 70/100 and the primary precedent is not robustly dominant under the transparent v1.13 weight profiles. The signal can be triggered by a moderate/sensitive overall robustness classification, low top-rank frequency, or the primary precedent losing first place under at least one tested profile.

Interpretation: a high aggregate suitability value should not be read as an uncontested historical analogue.

Severity: `material`.

### 4. Primary / alternative near tie

`primary-alternative-near-tie`

Triggered when the suitability gap between the first two visible precedents is <= 5 points.

Interpretation: small changes in assumptions or newly available context may reasonably make the alternative more relevant for staff review.

Severity: `watch`.

### 5. Opposed historical deltas

`opposed-historical-deltas`

The primary and alternative historical precedents are compared only on shared named metrics. A metric is reported when:

- both historical median deltas have absolute magnitude >= 0.5; and
- their signs are opposite.

v1.15 does not infer that positive is always better or negative is always worse. It reports only that the two historical analogies moved in opposite directions on the same metric.

Severity: `material`.

### 6. Missing primary evidence axis

`primary-evidence-axis-missing`

Triggered when the primary brief lacks any of:

- current scenario relevance;
- evidence quality;
- opponent similarity.

Interpretation: the staff should know that the primary analogy was formed with an incomplete evidence surface.

Severity: `watch`.

## Output

Every brief receives `conflict_signals` with:

- `status`;
- `signal_count`;
- `material_count`;
- `watch_count`;
- `highest_severity` (`none`, `watch`, `material`);
- individual named signals;
- explicit thresholds;
- explanatory note.

Each individual signal contains:

- stable signal ID;
- severity;
- localized title;
- localized explanation;
- the numerical or metric evidence that triggered it.

## Match Plan

Signals are calculated independently inside each current Match Plan scenario after v1.14 has produced the scenario-specific brief.

Scenario order never changes.

The catalog exposes counts of scenarios containing any configured tension and scenarios containing at least one material tension.

## Plan A/B/C

Signals are calculated after destination-plan filtering and, for Plan B, after the active/current scenario filter.

This preserves evidence isolation:

- Plan A tensions only use Plan A precedents;
- Plan B tensions only use Plan B precedents for the relevant scenario;
- Plan C tensions only use Plan C precedents.

## UI

The staff-facing brief shows one of three descriptive states:

- no configured tension;
- review;
- material.

When signals exist, staff can expand the brief to inspect each named tension and the exact evidence that triggered it.

There is deliberately no aggregate percentage or synthetic conflict score.

## Guardrails

v1.15 explicitly declares:

- `creates_conflict_score=false`
- `changes_evidence_order=false`
- `changes_suitability_scores=false`
- `changes_tactical_scores=false`
- `changes_recommendations=false`
- `changes_active_plan=false`
- `causal_model=false`

A conflict signal is a prompt for human review, not a veto, recommendation, tactical instruction or model of causal impact.

## No schema change

v1.15 operates on the existing in-memory v1.14 brief and requires no database migration.
