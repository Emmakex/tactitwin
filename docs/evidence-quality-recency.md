# v1.10 — Evidence Quality & Recency Calibration

TactiTwin v1.10 adds a descriptive quality layer to historical tactical precedents.

The purpose is to distinguish **having a precedent** from **having a precedent supported by stronger historical evidence**. The calibration does not change tactical risk/opportunity scores, Match Plan priorities, recommendations or the active Plan A/B/C.

## Quality score

Each historical context group receives `quality_score` from 0 to 100.

Weights:

- **25% recency** — median age of the signal cases using the persisted Live Session `closed_at` timestamp.
- **25% signal-sample volume** — number of cases actually used by the v1.7 signal basis.
- **20% cleaner-context share** — cleaner cases as a share of context-comparable cases.
- **20% directional stability** — consistency of the observed metric direction across repeated cases.
- **10% exact-opponent continuity** — share of signal cases played against the current exact opponent.

The components stay visible individually so the total is auditable.

## Recency bands

Median age of the signal cases:

- `<= 30 days` → 100
- `<= 90 days` → 85
- `<= 180 days` → 70
- `<= 365 days` → 50
- `<= 730 days` → 25
- `> 730 days` → 10
- missing persisted dates → 0 and `status=unavailable`

TactiTwin never assumes that an undated case is recent.

## Quality levels

- `high` >= 75
- `medium` >= 55
- `low` >= 35
- `very-low` < 35

These labels describe historical evidence quality only.

## Signal sample

v1.10 respects the v1.7 sample basis:

- if `signal_basis=cleaner`, only cleaner context-comparable cases drive recency, volume and opponent continuity;
- if `signal_basis=context-comparable`, cleaner + substitution-confounded context-comparable cases are used;
- insufficient groups may be displayed, but their quality naturally remains limited by sample volume and stability.

## Directional stability

For each aggregate metric with at least two observations, v1.10 takes the largest share among `up`, `down` and `flat` outcomes. The component score is the mean consistency across usable metrics.

This measures repeatability of direction, not whether the direction was tactically desirable.

Example: consistently reducing a metric and consistently increasing a metric can both be stable. Tactical meaning remains in the metric and scenario interpretation.

## Exact-opponent continuity

This component answers only:

> How much of this historical signal was observed against the exact opponent we are preparing for now?

It is deliberately **not** an opponent-strength or opponent-style similarity model.

A future opponent-similarity layer would require validated features such as playing style, tactical structure, competition strength, squad quality and/or external ratings. v1.10 does not invent those features.

## Product integration

The quality payload is attached to:

- v1.8 exact-context precedents in What-if;
- v1.9 context-preserving Match Plan precedent catalog;
- Plan A/B/C historical precedent items;
- Live Playbook precedent items.

WordPress planning panels display the quality label and score alongside each precedent.

## Guardrails

v1.10 explicitly reports:

- `changes_tactical_scores=false`
- `changes_recommendations=false`
- `changes_active_plan=false`
- `opponent_strength_model=false`

The pre-existing v1.8 retrieval score and v1.9 support ordering remain unchanged. Evidence quality is attached as a separate dimension rather than silently rewriting historical ranking logic.

## Method limitations

A high quality score does not prove causal coaching impact. Historical interventions remain observational, selected by staff and subject to unmeasured factors such as player availability, fatigue, exact opponent shape, game importance, substitutions and off-ball behaviour unavailable in event-only data.

The v1.10 score should therefore be read as:

> How much trust should we place in the historical precedent as repeated, recent and contextually clean evidence?

not:

> How likely is this tactical intervention to succeed?
