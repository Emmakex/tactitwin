# TactiTwin v1.6 — Context-Aware Transition Review

## Purpose

v1.6 adds context annotations to the descriptive before/after transition deltas introduced in v1.5.

The underlying deltas are preserved unchanged. v1.6 does not rescore or rewrite them. Instead it asks:

> Did score, manpower or personnel materially change inside the same before/after comparison?

This helps staff avoid over-interpreting a tactical delta when the two windows are structurally different for other reasons.

## Sources

Context is built from two independent sources.

### Live Session snapshots

The snapshot immediately before the recorded transition is compared with the snapshot that records the transition.

Fields:

- our score;
- opponent score;
- our players;
- opponent players.

This detects recorded score-state and manpower changes around the intervention.

### Normalized event stream

Both v1.5 windows are inspected for:

- goals;
- substitutions;
- direct red cards;
- second yellow cards.

Red/second-yellow detection uses the original normalized event `raw_json`, following the same card semantics used by the manpower engine.

## Context status

Each transition receives one context status.

### `stable`

No goal, dismissal, recorded score/manpower change or substitution was detected in the compared windows.

### `changed`

At least one substitution occurred, but no score/manpower material change was detected.

A substitution is not treated as positive or negative. It only means personnel context differs.

### `materially-changed`

At least one of these occurred:

- goal in either window;
- direct red / second yellow in either window;
- recorded score change between snapshots;
- recorded manpower change between snapshots.

## Interpretability

Context status is combined with the original v1.5 coverage gate.

### `cleaner`

- v1.5 comparison is comparable; and
- context is stable.

This means only that fewer configured context confounders were detected. It is still not causal evidence.

### `confounded`

- v1.5 comparison is comparable; and
- personnel context changed via substitution(s), without a material score/manpower change.

### `highly-confounded`

- v1.5 comparison is comparable; and
- score/manpower materially changed.

These rows set `context_comparable = false` because the tactical transition cannot be cleanly separated from the structural context shift with the current method.

### `insufficient`

The original v1.5 window coverage is not comparable.

Stable context cannot upgrade insufficient underlying evidence.

## Output

`Post-Match Review` now also returns:

`context_aware_transition_review`

Each transition retains all v1.5 data and adds:

- recorded snapshot context before/after;
- score-changed flag;
- manpower-changed flag;
- before-window goals/substitutions/dismissals;
- after-window goals/substitutions/dismissals;
- context status;
- interpretability status;
- `context_comparable`;
- bilingual warnings.

The existing `TactiTwin -> Transition Deltas` workspace automatically prefers the v1.6 context-aware layer when available and shows the confounder warnings next to the metric deltas.

## Method boundary

Context annotation improves post-match auditability, but it does not solve causal inference.

A cleaner comparison does **not** mean:

- the tactical intervention caused the observed delta;
- the decision was correct;
- the same intervention would reproduce the result;
- all relevant context is controlled.

## Remaining unobserved context

Important factors may still be missing or only partially observed:

- injuries without an event representation;
- tactical instructions not entered by staff;
- off-ball structure changes;
- fatigue;
- weather/pitch variation;
- psychological effects;
- opponent instructions;
- substitutions made just outside the selected windows;
- actual tactical change beginning before the staff snapshot.

## Next step

The next methodological improvement should use these annotations to filter or group historical interventions by comparable context, rather than immediately fitting a causal model. Repeated observations across matches with similar score/manpower/personnel conditions can then form an evidence library for recurring tactical decisions.
