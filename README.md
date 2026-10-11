# TactiTwin

**Football Tactical Intelligence & Match Simulation**

TactiTwin is a football tactical analysis and match-scenario simulation project. The first deliverable is a WordPress plugin that consumes public/open football data, normalizes it into an internal model, and turns it into reproducible tactical analysis, scenario simulations and match-plan recommendations for coaches and players.

## Product direction

`public/open data -> normalized football model -> tactical analysis -> scenario engine -> lineup decisions -> match plan -> Plan A/B/C -> live staff observations -> persistent match-day timeline -> post-match review -> full-match evidence -> time-aligned segment evidence -> before/after transition deltas -> context-aware transition review`

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
- Context annotation for goals, manpower and substitutions around those transitions.
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
- Context-aware transition review that flags goals, dismissals, recorded manpower/score changes and substitutions that may confound a before/after comparison.

### Match-day decision workflow

`Match Plan -> Contingency Playbook -> Live Playbook -> Live Session -> Session Review -> Evidence Review -> Segment Evidence -> Transition Deltas -> Context-Aware Review`

Plan B is never activated merely because historical data predicted an opponent behaviour. A supported manual observation from the staff can activate Plan B when Plan C is not already required by score/manpower context. Plan C keeps precedence for configured score/manpower contingencies.

Live Sessions persist the exact playbook JSON submitted at each staff update. Post-match review therefore reconstructs the historical decision state from immutable snapshots rather than recalculating the past with newer code or newer data.

### Evidence layers

**v1.3 — Objective Post-Match Evidence** compares sampled staff observations with transparent full-match event proxies. It returns `supported`, `partially-supported`, `not-supported` or `insufficient` rather than a coaching-quality score.

**v1.4 — Time-Aligned Segment Evidence** slices events into the same A/B/C intervals reconstructed from Live Session snapshots. Transition-minute events belong to the new segment, and pre-session events remain explicitly unassigned.

**v1.5 — Before/After Transition Deltas** surrounds every recorded tactical transition with symmetric event windows, currently 10 minutes before and after by default. It compares progression, final-third/box access, shots/xG, build-up security, opponent pressure/territory and defensive-transition signals. Coverage is `good`, `caution` or `limited`.

**v1.6 — Context-Aware Transition Review** preserves v1.5 deltas unchanged and adds context from Live Session snapshots plus normalized match events. It detects score/manpower changes, goals, substitutions and direct-red/second-yellow incidents. Each transition is labelled:

- `stable`,
- `changed` (personnel/substitution context), or
- `materially-changed` (score/manpower context).

Interpretability is then exposed separately as:

- `cleaner`,
- `confounded`,
- `highly-confounded`, or
- `insufficient`.

A cleaner comparison still does not establish causality; it only means fewer configured context confounders were detected.

## Method boundaries

The current version deliberately does not claim to model full team block height, compactness, every off-ball pressure, causal coaching impact or tracking-derived player geometry unless the necessary data exists.

Before/after deltas can remain confounded by fatigue, opponent instructions, injuries, off-ball structural changes and match randomness even when v1.6 reports stable configured context. No evidence layer currently claims causal coaching impact.

## Key REST surfaces

Examples include:

- `GET /wp-json/tactitwin/v1/planning/what-if`
- `GET /wp-json/tactitwin/v1/planning/contingency-playbook`
- `POST /wp-json/tactitwin/v1/planning/live-playbook`
- persistent Live Session create/update/read/close routes
- `GET /wp-json/tactitwin/v1/planning/live-sessions/{session_uuid}/review`
- `GET /wp-json/tactitwin/v1/planning/live-sessions/{session_uuid}/matching-matches`
- `POST /wp-json/tactitwin/v1/planning/live-sessions/{session_uuid}/link-match`

The post-match review response contains `objective_evidence`, `segment_evidence`, `transition_window_deltas` and `context_aware_transition_review` when a linked match with events is available.

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
- **v1.6 — Context-Aware Transition Review**

## Repository workflow

Development flows through feature branches and pull requests into `main`, with automated syntax and regression checks before merge.

## Status

Active coach-usable MVP development. The current product spans pre-match tactical analysis, lineup decision support, contingency planning, explicit live staff observation, persistent match-day audit trails, full-match and time-aligned post-match evidence, before/after transition review and explicit context/confounder annotations.
