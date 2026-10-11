# Lineup & Formation What-if

TactiTwin v0.5 adds a pre-match and in-match workspace for comparing two complete XIs and two nominal formations while retaining the opponent, score, minute and manpower context already available in the What-if engine.

## Purpose

The feature answers questions such as:

- What changes if we move from 4-3-3 to 4-2-3-1?
- What do we gain and lose if one starter is replaced by a more vertical player?
- Does the candidate XI improve recent progression or pressing while reducing ball security?
- Which players are being moved into roles that differ from their recent historical position?

It is a decision-support comparison, not a deterministic prediction of the match result.

## Evidence layers

### 1. Recent player profiles

The existing player-profile engine supplies minutes, position history and event metrics normalized per 90 minutes. The lineup comparator aggregates outfield-player profiles for:

- progression
- ball security
- attacking threat
- pressing activity
- defensive activity

The aggregate is descriptive. A +1.0 delta does not mean the team itself will improve by +1.0 after the change.

### 2. Nominal formation model

Supported v0.5 formations:

- 4-3-3
- 4-2-3-1
- 4-4-2
- 3-4-2-1
- 3-5-2
- 5-4-1

Each formation defines 11 nominal roles and a transparent structural vector for width, central density, rest defence, front-line presence, occupation between lines and double-pivot reference.

The structural vector is rule-based. It does not claim to measure the team's real shape without tracking/video.

### 3. Role compatibility

Each selected player is compared with the nominal role assigned by the formation. Historical event-data positions are grouped into role families such as goalkeeper, centre-back, fullback, wingback, defensive midfield, central midfield, attacking midfield, winger, wide midfield and striker.

Exact role-family matches score highest. Explicit adjacent-role relationships are also supported, for example fullback <-> wingback and central midfield <-> defensive midfield. Low-fit assignments are surfaced individually.

## Match context

Lineup comparisons retain the current contextual What-if output:

- minute
- exact score
- leading/drawing/trailing state
- 11v11 / superiority / inferiority context
- opponent risk and opportunity indexes
- Full Matchup Scan references

The lineup profile does not overwrite those contextual scores. It is an additional evidence layer.

## Coverage

Lineup confidence uses the selected players' recent minutes:

- high: minimum selected-player sample >= 180 minutes and average >= 450 minutes
- medium: minimum >= 90 minutes and average >= 180 minutes
- low: otherwise

Low coverage produces a warning and the deltas should remain low-weight until confirmed with video or coaching observation.

## Recent XI helper

`GET /wp-json/tactitwin/v1/planning/recent-xi?team_id=...` reads the latest imported StatsBomb `Starting XI` event, returning the provider formation when it maps to a supported TactiTwin formation and the 11 identifiable players.

## Lineup endpoint

`POST /wp-json/tactitwin/v1/planning/lineup-what-if`

Key inputs:

- `team_id`
- `opponent_id`
- `baseline_player_ids` (11 ids)
- `candidate_player_ids` (11 ids)
- `baseline_formation`
- `candidate_formation`
- `minute`
- `our_score`
- `opponent_score`
- `our_players`
- `opponent_players`
- `limit`
- `lang`

## WordPress workspace

`TactiTwin > Lineup Lab` provides:

- team/opponent selection
- match-state context
- baseline XI and formation
- candidate XI and formation
- load-latest-XI helper
- copy-baseline-to-candidate helper
- role-fit evidence
- player-profile deltas
- structural formation deltas
- tactical recommendations and technical JSON evidence

## Current boundary

Event data does not fully observe off-ball movement, spacing, synchronized pressing, marking, cover shadows, communication, real defensive distances or physical state. The formation model is therefore nominal and explainable by design. Tracking/video and physical-load data are future evidence layers rather than hidden assumptions in v0.5.
