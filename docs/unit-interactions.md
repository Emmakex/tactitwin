# Unit Interactions

TactiTwin v0.6 adds an event-based interaction layer for pairs and lineup networks.

The purpose is to move beyond isolated player profiles without pretending that event data can measure causal "chemistry". TactiTwin therefore uses the term **unit interaction** and always exposes the observed sample behind each relationship.

## Pair clock

For each imported match, TactiTwin reconstructs the set of players on the pitch from:

- `Starting XI`
- `Substitution`
- red card / second-yellow dismissals
- match clock, including extra time and excluding penalty shoot-outs

Shared minutes are accumulated only while both players are simultaneously active. A player replaced at minute 60 and the substitute entering for them do not receive false shared minutes with one another.

## Direct interaction evidence

While a pair shares the pitch, the engine records:

- completed passes between the two players
- directional passes A -> B and B -> A
- progressive passes (>=15 StatsBomb x-coordinate units forward)
- passes entering the final third
- passes entering the penalty area
- shot assists
- goal assists
- possessions in which both players record an event
- shared possessions ending in a shot
- xG produced by those shared possessions
- pressure -> teammate ball-recovery sequences within an 8-second event window

The pressure/recovery metric is deliberately named a **sequence**, not synchronized pressing. Event data does not prove that both actions were coordinated tactically.

## Normalization

Pair rates are normalized per 90 **shared** minutes, not per team match. This prevents a long-serving pair from ranking higher solely because it accumulated more playing time.

The profile also reports pass-direction balance:

`min(A->B, B->A) / max(A->B, B->A)`

This is descriptive only. A one-way relationship may be completely appropriate for the roles involved.

## Multi-match confidence

Pair profiles aggregate across the selected recent-match sample.

Current confidence thresholds:

- **high**: >=360 shared minutes and >=4 observed matches
- **medium**: >=180 shared minutes and >=2 observed matches
- **low**: otherwise

Raw shared minutes and match count are always returned with the confidence label.

## Signals

TactiTwin exposes four transparent interaction signals:

### Connectivity

Completed direct passes per 90 shared minutes.

### Progressive link

A descriptive combination of progressive passes, final-third entries and box entries between the pair.

### Chance chain

Shared-possession xG plus a small shot-assist component. It indicates that both players were involved in possessions producing threat; it is not an assist or causal xG attribution model.

### Defensive sequence

Pressure -> teammate recovery sequences per 90 shared minutes.

These signals are not merged into one opaque chemistry score.

## Lineup network

For any 2-11 selected players, the network layer returns:

- possible vs observed pairs
- confidence distribution
- under-observed pairs (<90 shared minutes)
- shared-minute-weighted network signals
- leaders for connectivity, progression, chance chain and defensive sequences
- full pair evidence

Leaders require at least 90 shared observed minutes.

## Lineup comparison

`Lineup & Formation What-if` now adds unit interaction evidence when comparing baseline and candidate XIs.

The comparison identifies:

- outgoing/incoming players
- historical pair relationships removed by the change
- historical pair relationships introduced by the change
- new pairs with <90 shared observed minutes
- deltas in network connectivity, progressive links, chance-chain involvement and defensive sequences

The unit recommendations remain separate in methodology from:

1. individual player-profile evidence,
2. nominal formation structure,
3. score/manpower/opponent context.

## WordPress surfaces

### Unit Lab

`TactiTwin > Unit Lab`

Choose a team and two players to inspect:

- shared sample
- confidence
- direct passing
- progression
- chance-chain involvement
- pressure/recovery sequences
- pass-direction balance
- detailed totals and per-90 shared-minute values
- technical JSON evidence

### Lineup Lab

Unit-network comparison is included in the Lineup What-if result and its tactical recommendations.

## REST endpoints

### Pair profile

`GET /wp-json/tactitwin/v1/analysis/pair-interaction`

Parameters:

- `team_id`
- `player_a_id`
- `player_b_id`
- `limit`

### Unit network

`POST /wp-json/tactitwin/v1/analysis/unit-network`

Inputs:

- `team_id`
- `player_ids` (2-11)
- `limit`

### Network comparison

`POST /wp-json/tactitwin/v1/analysis/unit-network-compare`

Inputs:

- `team_id`
- `baseline_player_ids`
- `candidate_player_ids`
- `limit`
- `lang`

## Current boundary

This layer does **not** claim to measure causal chemistry. Event data misses many decisive interactions:

- off-ball rotations
- coordinated pressing shape
- marking exchanges
- cover shadows
- line distances
- synchronized movements
- communication
- physical condition and fatigue

Those require tracking, video-derived positioning and/or physical data. TactiTwin keeps these limitations explicit rather than inferring them invisibly from pass counts.
