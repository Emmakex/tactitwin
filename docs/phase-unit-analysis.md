# Phase-conditioned Unit Analysis

TactiTwin v0.7 conditions observed pair and XI-network interactions on explicit event phases. The goal is to answer **where** a player combination has historically connected, not only whether the pair has connected overall.

This layer remains event-based. It does not infer a team’s true positional structure, block height or synchronized movement without tracking/video.

## Supported phases

### Build-up

A completed pass is assigned to build-up when its start location has StatsBomb `x < 40`.

Observed pair evidence includes:

- completed passes
- progressive passes
- entries generated from the connection
- assists when present

### Progression

A completed pass is assigned to progression when `40 <= x < 80`.

This phase is particularly useful for midfield and flank combinations moving the ball toward the attacking third.

### Final third

A completed pass is assigned to the final-third phase when its start location has `x >= 80`.

Additional evidence includes:

- penalty-area entries
- shot assists
- goal assists
- possessions in which both players participate and the team reaches `x >= 80` before producing a shot
- xG from those shared shot possessions

### High press

A high-press sequence is recorded when:

1. a team player records a `Pressure` event at `x >= 72`, and
2. a different teammate records a `Ball Recovery` within 8 seconds.

One pressure event can support only one recovery sequence. The metric is deliberately called a **pressure→recovery sequence**, not proof of coordinated pressing.

## Shared-minute denominator

All phase rates are normalized per 90 shared on-pitch minutes. TactiTwin reuses the v0.6 shared-pitch clock reconstructed from:

- Starting XI
- substitutions
- dismissals
- normal/extra-time clock

Penalty shoot-outs are excluded.

The phase signal therefore answers: *how often did this type of interaction appear while these players actually shared the pitch?*

It does not attempt to estimate seconds spent continuously in a tactical phase because event data alone does not provide a reliable phase-duration denominator.

## Phase signals

Signals stay separate; there is no combined tactical-chemistry score.

### Build-up signal

Completed build-up passes per 90 shared minutes plus a transparent progressive-pass component.

### Progression signal

Progressive passes plus weighted final-third and penalty-area entries.

### Final-third signal

Shared-possession xG plus weighted shot assists and box entries.

### High-press signal

Pressure→recovery sequences per 90 shared minutes.

## Multi-match confidence

Phase pair profiles reuse the v0.6 shared-sample thresholds:

- **high**: >=360 shared minutes and >=4 matches
- **medium**: >=180 shared minutes and >=2 matches
- **low**: otherwise

Confidence applies to the historical pair sample. A large shared sample does not imply that every individual phase signal is equally frequent.

## XI phase network

For 2–11 selected players, TactiTwin returns:

- possible/observed pair coverage
- under-observed pairs (<90 shared minutes)
- shared-minute-weighted signals for build-up, progression, final third and high press
- the leading observed pair in each phase, requiring at least 90 shared minutes
- full pair phase evidence

## Lineup & Formation What-if integration

A baseline XI and candidate XI now carry three distinct interaction/evidence layers:

1. **Individual player profiles** — what each player has recently done.
2. **Global unit interactions** — which pair connections were observed while players shared the pitch.
3. **Phase-conditioned unit interactions** — where those connections occurred: build-up, progression, final third or high press.

Nominal formation structure remains a fourth, rule-based layer. These layers are not silently collapsed into one score.

The phase comparison can therefore produce interpretations such as:

- stronger observed build-up connections in the candidate XI;
- weaker progression network despite better individual attacking profiles;
- greater final-third historical involvement but new/under-observed pair relationships;
- stronger high-press pressure→recovery sequences.

## REST endpoints

### Pair phase profile

`GET /wp-json/tactitwin/v1/analysis/pair-phase-interaction`

Parameters:

- `team_id`
- `player_a_id`
- `player_b_id`
- `limit`

### Phase unit network

`POST /wp-json/tactitwin/v1/analysis/phase-unit-network`

Inputs:

- `team_id`
- `player_ids` (2–11)
- `limit`

### Compare phase networks

`POST /wp-json/tactitwin/v1/analysis/phase-unit-network-compare`

Inputs:

- `team_id`
- `baseline_player_ids`
- `candidate_player_ids`
- `limit`
- `lang`

## Current boundary

The phase labels are explicit event-location rules, not tracking-derived tactical states. In particular, v0.7 does not claim to infer:

- actual build-up shape (2+3, 3+2, etc.)
- block height and compactness
- line distances
- off-ball occupation
- cover shadows
- synchronized pressing direction
- physical readiness/fatigue

Those require tracking, video-derived positioning and/or physical data. Future TactiTwin layers can replace or enrich these event rules when those data sources are available.
