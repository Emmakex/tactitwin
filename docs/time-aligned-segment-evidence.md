# TactiTwin v1.4 — Time-Aligned Segment Evidence

## Purpose

v1.4 compares imported match events with the same A/B/C intervals reconstructed from a persistent Live Session. It adds temporal context to v1.3 without rewriting the original staff audit trail.

The central question is no longer only:

> Did the full match contain evidence of the behaviour the staff observed?

It can now also ask:

> What event evidence was present during the exact interval in which Plan A, B or C was active?

## Inputs

A time-aligned review requires:

- a Live Session with snapshots;
- a linked imported match;
- normalized event data for that match;
- the same team/opponent pair in the session and imported match.

The engine reuses:

- `TactiTwin_Live_Timeline` for A/B/C segments;
- `TactiTwin_Match_Metrics` for event-derived tactical metrics;
- `TactiTwin_Transition_Metrics` for short-window loss/transition evidence;
- persisted scenario fingerprints for weak-side targets.

## Segment boundary rule

Live snapshots are currently minute-granular.

For a transition at minute `M`:

- the previous non-final segment is `[from, M)`;
- the new segment starts at `M`;
- the final segment is `[from, to]` so events in the final stored minute are retained.

Therefore an event at the exact transition minute belongs to the new plan. It is never counted twice.

Events before the first Live Session snapshot remain outside the reconstructed plan intervals. They are reported as unobserved pre-session minutes rather than silently assigned to Plan A.

## Segment-specific evidence

Full-match count thresholds from v1.3 are not reused directly inside shorter intervals.

v1.4 uses segment-specific sample gates and normalized rates.

### High press

Minimum sample:

- segment duration >= 8 minutes;
- opponent pressures >= 3.

Supported proxy:

- opponent high pressures per 90 >= 18;
- high-pressure share >= 25%.

Partial support:

- high pressures per 90 >= 12; or
- high-pressure share >= 18%.

### Low block

Minimum sample:

- segment duration >= 8 minutes;
- opponent defensive actions >= 4.

Supported proxy:

- deep defensive actions per 90 >= 18;
- deep defensive-action share >= 45%.

Partial support:

- deep actions per 90 >= 12 and share >= 35%; or
- share >= 40%.

### Transition threat after loss

Supported proxy:

- opponent shots within 10 seconds after our loss >= 2; or
- xG conceded within 10 seconds >= 0.25; or
- at least 3 transition turnovers with progression rate >= 35%.

Partial support:

- at least one immediate shot; or
- xG >= 0.10; or
- at least 2 transition turnovers with at least one immediate opponent progression.

If there is too little turnover exposure, the result remains insufficient.

### Opponent side bias

Supported proxy:

- at least 5 opponent final-third actions;
- left/right gap >= 20 percentage points;
- dominant lateral share >= 40%.

Partial support:

- lateral gap >= 12 points with sufficient actions.

### Weak-side access

Requires a structured target channel persisted in the playbook before/during the match.

Supported proxy:

- at least 5 of our final-third actions in the segment;
- prepared target channel share >= 35%.

Partial support:

- target share >= 25% with enough actions.

Legacy sessions without a persisted target stay `insufficient`.

## Output

Each segment contains:

- plan (`A`, `B`, `C`);
- mode;
- active scenario;
- from/to minute;
- explicit interval boundary rule;
- snapshot IDs;
- event count;
- staff observations inside the segment;
- all five event-derived signals;
- active-scenario evidence when applicable;
- compact team/opponent metrics for auditability.

The Post-Match Review endpoint now includes `segment_evidence` in addition to v1.3 `objective_evidence`.

WordPress adds:

`TactiTwin -> Segment Evidence`

## Method boundary

This layer is descriptive.

It does **not** claim that:

- Plan B caused an improvement;
- Plan C caused a match outcome;
- a staff observation was right or wrong;
- event data is equivalent to tracking data.

A segment with supported evidence only means that the configured event proxy was present during that reconstructed plan interval.

## Limitations

1. Live snapshots currently have minute rather than second resolution.
2. Segments describe intervals between submissions; they do not imply continuous staff observation.
3. A transition sequence that crosses a segment boundary can be split and undercounted.
4. Event-derived low-block/high-press signals are proxies, not exact line-height or compactness measurements.
5. Causality is outside the current model.

## Next step

A future layer can compare *before vs after* a plan transition using matched windows while keeping the same causal guardrail. That may help describe whether observable match dynamics changed after an intervention without claiming that the intervention caused the change.
