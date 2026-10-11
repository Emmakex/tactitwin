# v1.11 — Opponent Context Distance

TactiTwin v1.11 adds a transparent opponent-style similarity axis to historical tactical precedents.

It answers a different question from v1.10:

- **v1.10 evidence quality:** how strong, recent and clean is the historical precedent?
- **v1.11 opponent similarity:** how similar was the historical opponent's observed tactical profile to the opponent we are preparing for now?

The two dimensions are intentionally not combined into one score.

## Historical integrity: as-of profiles

A historical opponent is reconstructed only with imported event-data matches at or before the date of the precedent match.

For a precedent on date `D`:

1. read the historical opponent ID from the immutable Live Session/intervention record;
2. read the linked source match date;
3. select the last N imported event-data matches for that opponent where `match_date <= D`;
4. aggregate the historical team profile;
5. compare that historical profile with the current opponent's recent profile.

Future matches are excluded. This avoids evaluating an old intervention using tactical behaviour the historical opponent only developed later.

## Style components

The composite similarity score is 0–100 and uses five transparent components.

### Pressing — 25%

- high pressures per match
- high recoveries per match
- share of pressures occurring high

### Defensive block proxy — 15%

- share of defensive actions in the defensive/deep zone
- defensive actions per match

This is an event-derived depth proxy, not an exact block-height measurement.

### Attack-channel distribution — 20%

- left final-third share
- central final-third share
- right final-third share

The channel distance uses the distribution directly rather than collapsing it to the dominant side.

### Transition threat — 20%

- quick progressions created after opponent losses
- quick shots created after opponent losses
- quick xG created after opponent losses

### Progression and territory — 20%

- progressive actions per match
- final-third entries per match
- box entries per match
- shots per match

## Scalar distance

For scalar features, v1.11 uses an explicit reference scale per feature:

`similarity = 100 - min(100, abs(current - historical) / reference_scale * 100)`

The reference scale is returned with every feature so the comparison is inspectable.

## Channel distance

For left/central/right shares:

`distance = 0.5 * (|L1-L2| + |C1-C2| + |R1-R2|)`

`similarity = 100 - distance`

Because the three shares sum to approximately 100, the resulting channel similarity is naturally bounded near 0–100.

## Same club does not imply same style

`exact_opponent=true` is exposed separately.

It does **not** force tactical similarity to 100. The same club can change coach, shape, pressing behaviour, personnel and transition profile over time. v1.11 still compares its historical `as-of` profile with its current profile.

## Group aggregation

Each v1.7 historical context group can contain multiple signal cases. v1.11:

- respects the same v1.7 signal basis (`cleaner` or `context-comparable`);
- reconstructs each case's historical opponent profile as-of that match date;
- computes similarity per reconstructable case;
- returns the mean similarity across available signal cases;
- exposes cases that cannot be reconstructed as `unavailable`;
- never treats missing profiles as zero similarity.

## Similarity levels

- `very-high` >= 80
- `high` >= 65
- `medium` >= 50
- `low` >= 35
- `very-low` < 35

These labels describe tactical-style proximity only.

## Product integration

The similarity payload is attached by `group_key` to:

- What-if historical precedents;
- Match Plan context-preserving precedent catalog;
- Plan A/B/C precedent items;
- Live Playbook precedent items.

It remains a separate axis next to v1.10 `evidence_quality`.

A precedent can therefore be, for example:

- high quality / high opponent similarity;
- high quality / low opponent similarity;
- low quality / high opponent similarity;
- low quality / low opponent similarity.

This is more informative than a single blended score.

## Guardrails

v1.11 explicitly reports:

- `strength_model=false`
- `embedding_model=false`
- `changes_tactical_scores=false`
- `changes_recommendations=false`
- `changes_active_plan=false`
- `quality_similarity_combined_score=false`

Opponent similarity does not re-rank v1.8 retrieval scores or v1.9 support ordering in this version.

## Limitations

Opponent context distance is not a team-strength rating. Similar styles do not imply equal player quality or competitive level.

Event data cannot recover exact block height, compactness, line distances or all off-ball behaviour. Samples may also be small or unrepresentative.

v1.11 therefore means:

> This historical opponent behaved more or less similarly to the current opponent on the event-derived tactical dimensions we can currently observe.

It does not mean:

> These opponents are equally strong, or the historical intervention will have the same effect.
