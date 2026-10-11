# TactiTwin v1.5 — Before/After Transition Window Deltas

## Purpose

v1.5 describes how selected event-derived metrics changed around each tactical transition recorded by the Live Session timeline.

For every A/B/C, mode or scenario transition at minute `M`, TactiTwin compares a symmetric event window before the recorded change with a symmetric event window after it.

The default comparison is:

- before: `[M-10, M)`
- after: `[M, M+10)`

The transition-minute events belong only to the after window.

This is a descriptive intervention audit. It is **not** a causal-effect estimate.

## Why this layer exists

v1.3 answers whether a tactical signal existed across the whole match.

v1.4 answers what event evidence existed inside each reconstructed Plan A/B/C interval.

v1.5 answers a narrower question:

> What observable event metrics changed immediately before vs immediately after the staff recorded a tactical transition?

This is useful for post-match learning while preserving the distinction between temporal association and causality.

## Inputs

The review requires:

- a persistent Live Session;
- at least one recorded timeline transition;
- a linked imported match;
- normalized event data for that match;
- matching team/opponent identities.

The Post-Match Review service loads the linked match summary and normalized event stream once and reuses that bundle for:

- v1.3 full-match objective evidence;
- v1.4 time-aligned segment evidence;
- v1.5 transition-window deltas.

This avoids repeated reads of the same match event set.

## Window definition

Default window size: `10 minutes` on each side.

The engine accepts 5–20 minute windows internally, but the current product review uses 10 minutes by default.

For a transition at minute `M`:

- before uses `[M-window, M)`;
- after uses `[M, M+window)`.

An event at minute `M` belongs only to the after window.

Windows close to match boundaries are clipped safely. Coverage then reflects the reduced duration and event count.

## Metrics

Numeric metrics are normalized per 90 minutes when appropriate so equivalent windows can be compared on the same rate scale.

Current comparison set includes:

### Our attacking/progression metrics

- progressive actions per 90;
- final-third entries per 90;
- box entries per 90;
- shots per 90;
- xG per 90;
- own-third turnovers per 90;
- build-up escape rate.

### Opponent pressure/territory metrics

- high pressures per 90;
- high-pressure share;
- final-third actions per 90;
- shots per 90;
- xG per 90.

### Defensive-transition metrics

- our transition turnovers per 90;
- opponent progressions within 10 seconds after our losses per 90;
- opponent shots within 10 seconds after our losses per 90;
- xG conceded within 10 seconds after our losses per 90.

### Channel distributions

- our final-third channel shares;
- opponent final-third channel shares.

Channel values are compared as percentage-point deltas rather than per-90 rates.

## Scenario focus

The complete metric set remains available for auditability, while the UI highlights metrics related to the scenario activated after the transition.

Examples:

### Build-up vs High Press

- own-third turnovers;
- build-up escape rate;
- progressive actions;
- opponent high pressures.

### Attack vs Low Block

- box entries;
- shots;
- xG;
- final-third entries.

### Defensive Transition After Loss

- opponent progressions after loss;
- opponent shots after loss;
- transition xG conceded;
- transition turnovers.

If a new plan/mode has no scenario-specific mapping, generic progression/territorial metrics are shown.

## Coverage gate

A before/after comparison is assigned one of three coverage states.

### Good

- both windows contain the full requested duration; and
- both windows contain at least 15 normalized events.

### Caution

- both windows contain at least 6 minutes; and
- both contain at least 8 events.

### Limited

Anything below the caution gate.

Limited comparisons are explicitly marked `comparable = false`.

This prevents an early or very late transition with sparse evidence from being presented as a robust before/after comparison.

## Output

Each transition contains:

- transition minute;
- from/to plan;
- from/to mode;
- from/to scenario;
- triggering snapshot ID;
- requested window size;
- before interval, duration and event count;
- after interval, duration and event count;
- coverage status;
- full before metrics;
- full after metrics;
- numeric deltas (`after - before`);
- scenario-focused metric subset.

Post-Match Review adds:

`transition_window_deltas`

WordPress adds:

`TactiTwin -> Transition Deltas`

## Interpretation boundary

A positive or negative delta means only that the observed event rate/value changed between the two windows.

TactiTwin does **not** currently say:

- the tactical change caused the delta;
- the staff decision was correct or incorrect;
- the delta increased the probability of winning;
- the same change would reproduce the same result in another match.

## Confounders

Before/after windows may differ for reasons unrelated to the tactical intervention, including:

- score state;
- red cards / manpower;
- substitutions;
- fatigue;
- opponent tactical changes;
- injuries;
- set-piece sequences;
- match randomness;
- the staff snapshot being recorded after the real-world change began.

A future layer should annotate these context changes directly and downgrade comparisons where the context changed materially.

## Known limitations

1. Snapshot timing is minute-granular.
2. A turnover/transition sequence crossing minute `M` can be split between windows.
3. Event data does not provide full tracking geometry.
4. Per-90 normalization helps compare rates but does not remove contextual confounding.
5. Before/after temporal association is not causal inference.

## Next methodological step

The natural follow-up is a **context-aware transition review** that annotates score changes, manpower changes, substitutions and other material context shifts inside each before/after window. That should happen before attempting any stronger statistical interpretation of intervention effects.
