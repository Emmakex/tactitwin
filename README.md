# TactiTwin

**Football Tactical Intelligence & Match Simulation**

TactiTwin is a football tactical analysis and match-scenario simulation project. The first deliverable is a WordPress plugin that consumes public/open football data, normalizes it into an internal model, and turns it into reproducible tactical analysis, scenario simulations and match-plan recommendations for coaches and players.

## Product direction

`public/open data -> normalized football model -> tactical analysis -> scenario engine -> lineup decisions -> match plan -> Plan A/B/C -> live staff observations -> persistent match-day timeline -> post-match review -> full-match evidence -> time-aligned segment evidence -> before/after transition deltas`

TactiTwin is not intended to predict a final score with certainty. It is a decision-support system for exploring tactical risks, opportunities and "what if" scenarios before, during and after a match while retaining evidence, confidence and method boundaries.

## Phase 1 — WordPress MVP

The first phase focuses on:

- Public/open football-data connectors.
- Teams, players, opponents and matches.
- A provider-independent normalized data layer.
- Tactical metrics and opponent profiling.
- Scenario simulation with rules, weights and scores.
- Match-plan recommendations with explainable evidence.
- Historical score-state and manpower conditioning.
- Lineup and unit what-if comparison.
- Plan A / B / C contingency preparation.
- Explicit staff-confirmed live observations.
- Persistent match-day sessions and decision timelines.
- Descriptive post-match prepared / observed / activated audit.
- Full-match event-evidence comparison against the immutable live record.
- Time-aligned event evidence inside reconstructed A/B/C intervals.
- Symmetric before/after event windows around recorded tactical transitions.
- WordPress administration UI and REST endpoints.

Initial data-source targets:

- StatsBomb Open Data for event-level analysis where available.
- football-data.org for fixtures, results, standings and competition context.
- OpenFootball / football.json as an open fallback/import source.
- Manual coach inputs for information public APIs cannot know.

## Current tactical workflow

The current WordPress plugin supports:

- Build-up vs High Press.
- Opponent Side Bias.
- Defensive Transition After Loss.
- Attack vs Low Block.
- Exploit Opponent Weak Side.
- Full Matchup Scan across all five scenarios.
- Bilingual Match Plan generation in English and Spanish.
- Game State / What-if planning from an exact hypothetical minute and scoreline.
- Player, unit, phase-unit and lineup comparison.
- Opponent-conditioned lineup matchup.
- Plan A / B / C contingency playbook.
- Live Playbook with explicit staff-confirmed opponent-behaviour inputs.
- Persistent Live Sessions with append-only tactical snapshots.
- A/B/C timeline and transition reconstruction.
- Descriptive Session Review separating what was prepared, observed and activated.
- Evidence Review linking a Live Session to an imported match and comparing staff observations with transparent full-match event proxies.
- Segment Evidence comparing the same signals inside the exact A/B/C intervals reconstructed from staff snapshots.
- Transition Deltas comparing matched event windows immediately before and after each recorded tactical transition.

### Game State / What-if

The What-if Lab accepts a hypothetical minute and exact scoreline, for example minute 80 at 1-0. TactiTwin then conditions the tactical response on historically observed behaviour while the selected team was leading, drawing or trailing and on the opponent's reciprocal score state.

Current score-state evidence includes observed minutes, shots, xG, progressive actions, final-third entries, box entries and pressing activity normalized per 90 minutes. The exact hypothetical scoreline is retained as context, while the historical sample is currently grouped by leading/drawing/trailing rather than exact past scoreline.

### Match-day decision workflow

The current match-day workflow is:

`Match Plan -> Contingency Playbook -> Live Playbook -> Live Session -> Session Review -> Evidence Review -> Segment Evidence -> Transition Deltas`

Plan B is never activated merely because historical data predicted an opponent behaviour. A supported manual observation from the staff can activate Plan B when Plan C is not already required by score/manpower context. Plan C keeps precedence for the configured score/manpower contingencies.

Live Sessions persist the exact playbook JSON submitted at each staff update. Post-match review therefore reconstructs the historical decision state from immutable snapshots rather than recalculating the past with newer code or newer data.

### Objective Post-Match Evidence

A Live Session can be linked to a compatible imported match. Once event data exists for that match, TactiTwin can compare sampled staff observations with transparent full-match event proxies for opponent high press, low/compact block, transition threat after losses, opponent lateral bias and access to a persisted weak-side target.

The result uses explicit `supported`, `partially-supported`, `not-supported` and `insufficient` states. This layer does **not** replace the live staff record, does not claim event data is tracking, and does not score coaching quality or causality.

### Time-Aligned Segment Evidence

v1.4 slices the linked event stream into the same intervals reconstructed from the Live Session timeline. A transition at minute `M` follows an explicit boundary rule: the old plan ends before minute `M` and the new plan receives events stamped with minute `M`.

Each interval keeps Plan A/B/C, mode, active scenario, event count, staff observations and the five event-derived signals with segment-specific sample gates. Events before the first snapshot remain outside the reconstructed plan timeline rather than being silently assigned to Plan A.

### Before/After Transition Deltas

v1.5 surrounds every recorded tactical transition with symmetric event windows, currently 10 minutes before and 10 minutes after by default.

The comparison includes progression, final-third/box entries, shots/xG, build-up security, opponent pressing/territory and defensive-transition signals. Metrics are normalized per 90 where appropriate, while channel shares use percentage-point changes.

Coverage is explicitly classified as `good`, `caution` or `limited`. Limited windows are not marked comparable. Scenario-specific focus metrics highlight what changed around a transition without claiming that the tactical intervention caused the change.

The Post-Match Review service reuses one linked match summary/event bundle across full-match evidence, segment evidence and transition deltas to avoid repeated reads of the same event set.

## Method boundaries

The current version deliberately does not claim to model full team block height, compactness, every off-ball pressure, causal coaching impact or tracking-derived player geometry unless the necessary data exists.

v1.3 is the full-match evidence layer. v1.4 adds minute-aligned evidence for A/B/C intervals. v1.5 adds descriptive symmetric before/after comparisons around recorded transitions.

Before/after deltas remain confounded by score, manpower, substitutions, fatigue, opponent changes and match randomness. They do **not** establish causal coaching impact.

## Key REST surfaces

Examples include:

- `GET /wp-json/tactitwin/v1/planning/what-if`
- `GET /wp-json/tactitwin/v1/planning/contingency-playbook`
- `POST /wp-json/tactitwin/v1/planning/live-playbook`
- persistent Live Session create/update/read/close routes
- `GET /wp-json/tactitwin/v1/planning/live-sessions/{session_uuid}/review`
- `GET /wp-json/tactitwin/v1/planning/live-sessions/{session_uuid}/matching-matches`
- `POST /wp-json/tactitwin/v1/planning/live-sessions/{session_uuid}/link-match`

The post-match review response contains `objective_evidence`, `segment_evidence` and `transition_window_deltas` when a linked match with events is available.

## Planned modules

- `wordpress-plugin/` — installable WordPress plugin.
- `core/` — shared domain model and contracts.
- `data-connectors/` — external-source adapters and normalization.
- `tactical-engine/` — metrics, profiles, risks and opportunities.
- `simulation-engine/` — tactical what-if scenarios.
- `reports/` — match-plan and analysis outputs.
- `docs/` — architecture, roadmap and data-source documentation.
- `tests/` — automated tests and fixtures.

## Product milestones

- **v0.1 — Core + Data Layer**
- **v0.2 — Analysis Engine**
- **v0.3 — Scenario Engine + Game State What-if**
- **v0.4 — Match Planner**
- **v0.5 — Tactical Assistant**
- **v0.6–v0.8 — Unit / lineup / opponent-conditioned decision support**
- **v0.9 — Contingency Playbook Plan A/B/C**
- **v1.0 — Explicit Live Observation activation**
- **v1.1 — Persistent Live Session Timeline**
- **v1.2 — Descriptive Post-Match Review**
- **v1.3 — Objective Post-Match Event Evidence**
- **v1.4 — Time-Aligned Segment Evidence**
- **v1.5 — Before/After Transition Window Deltas**

## Repository workflow

Development should flow through feature branches and pull requests into `main`, with automated checks before merge.

## Status

Active coach-usable MVP development. The current product spans pre-match tactical analysis, lineup decision support, contingency planning, explicit live staff observation, persistent match-day audit trails, full-match and time-aligned post-match evidence, and descriptive before/after transition review.
