# Live Session Timeline

## Purpose

v1.1 adds persistent match-day sessions on top of the v1.0 Live Playbook.

The stateless `Live Playbook` remains useful for quick tactical checks. `Live Sessions` is the persistent workflow for a real match: every staff update becomes an auditable snapshot of the match context, observed opponent behaviours and active Plan A/B/C decision.

## Database schema

Schema version: `0.2.0`.

### `{$wpdb->prefix}tactitwin_live_sessions`

One row per match-day working session.

Key fields:

- `session_uuid`
- optional `source_match_id`
- `team_source_id`
- `opponent_source_id`
- language
- status (`active` / `closed`)
- title
- creator
- started / updated / closed timestamps

### `{$wpdb->prefix}tactitwin_live_snapshots`

Append-only decision snapshots.

Key fields:

- `session_uuid`
- minute
- score
- player counts
- active plan (`A`, `B`, `C`)
- active mode
- selected scenario
- live activation status
- observations JSON
- staff note
- complete playbook JSON
- creator and timestamp

High-volume/live history is intentionally stored in custom tables rather than CPT/postmeta.

## Upgrade safety

A schema guard runs on WordPress `init` and calls `TactiTwin_DB::install()` only when the stored schema version differs from the code version.

This matters because WordPress does not rerun a plugin activation hook on a normal plugin update. It also guarantees that a REST request can use the new live-session tables before an administrator has visited the plugin dashboard.

## Session workflow

1. Start a session with team/opponent and optional source match/title.
2. TactiTwin computes the v1.0 Live Playbook.
3. Persist the first snapshot.
4. Each match-day update recomputes the live playbook from current minute/score/player counts/observations.
5. Append a new immutable snapshot.
6. Rebuild the timeline from stored snapshots.
7. Close the session after the match.

Closed sessions remain queryable but reject new snapshots.

A session UUID cannot be reused for a different team/opponent pair.

## Timeline reconstruction

`TactiTwin_Live_Timeline` is a pure projector over stored snapshots.

It returns:

- snapshot count
- first / last saved minute
- current plan and mode
- snapshot count by plan
- scenario occurrence counts
- plan/mode/scenario transitions
- contiguous segments

A transition is recorded when plan, mode or selected scenario changes. Therefore a Plan B adaptation changing from `escape_high_press` to `secure_transition` is preserved even though the plan letter remains B.

### Critical boundary

Segments represent the interval **between staff submissions**. They do not claim that TactiTwin continuously observed the match between snapshots.

## WordPress workspace

New submenu:

`TactiTwin -> Live Sessions`

Functions:

- start a new persistent session
- save successive live snapshots
- update minute / score / player counts
- retain live observation checkboxes and staff notes
- close a session
- reopen historical sessions in read-only mode
- inspect recent sessions
- see snapshot table and A/B/C transitions
- see the latest live tactical decision

`TactiTwin -> Live Playbook` remains available as the stateless scratchpad.

## REST API

### Create/update and persist a live session snapshot

`POST /wp-json/tactitwin/v1/planning/live-sessions/update`

Accepts the v1.0 context/observation parameters plus:

- `session_uuid` (optional; omit to create a new session)
- `source_match_id` (optional)
- `session_title` (optional)

Returns:

- current playbook
- session
- newly stored snapshot
- reconstructed timeline

### Read one session

`GET /wp-json/tactitwin/v1/planning/live-sessions/{session_uuid}`

Returns session metadata, summary and snapshots.

### Close one session

`POST /wp-json/tactitwin/v1/planning/live-sessions/{session_uuid}/close`

### List recent sessions

`GET /wp-json/tactitwin/v1/planning/live-sessions`

Optional filters:

- `limit` (1–100)
- `team_id`

## Audit value

The persistent timeline creates the foundation for a later post-match review:

- which Plan B behaviours were actually observed
- when A -> B or B -> C occurred
- which score/manpower changes drove a decision
- whether the same opponent behaviour recurred
- whether a planned contingency was used or never triggered

The stored full playbook JSON preserves the evidence shown to staff at each decision point instead of recomputing history later with newer code or different data.

## Method boundary

A snapshot is a staff-submitted observation state. Persistence improves auditability; it does not make manual observations more objective or convert them into continuous tracking data.
