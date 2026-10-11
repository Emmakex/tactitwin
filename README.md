# TactiTwin

**Football Tactical Intelligence & Match Simulation**

TactiTwin is a football tactical analysis and match-scenario simulation project. The first deliverable is a WordPress plugin that consumes public/open football data, normalizes it into an internal model, and turns it into reproducible tactical analysis, scenario simulations and match-plan recommendations for coaches and players.

## Product direction

`public/open data -> normalized football model -> tactical analysis -> scenario engine -> lineup decisions -> match plan -> Plan A/B/C -> live staff observations -> persistent match-day timeline -> post-match review -> full-match evidence -> time-aligned segment evidence -> before/after transition deltas -> context-aware transition review -> context-matched intervention library -> historical precedent retrieval -> precedent-aware match planning -> evidence-quality calibration -> opponent-context distance`

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
- Cross-match intervention memory grouped by comparable tactical context.
- Exact-context historical precedent retrieval inside What-if preparation.
- Historical precedent evidence inside Match Plan and Plan A/B/C while preserving context boundaries.
- Historical evidence quality/recency calibration.
- Transparent historical-opponent tactical-context similarity using as-of profiles.
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
- Intervention Library grouping repeated interventions across closed linked sessions when scenario, destination plan/mode, minute band, score state and manpower state match.
- Historical Tactical Precedents inside What-if, using exact minute-band + score-state + manpower-state matching and current matchup relevance.
- Pre-match precedent catalog inside Match Plan, keeping minute/score/manpower contexts separate rather than averaging them together.
- Exact-context precedent distribution into Plan A/B/C by historical destination plan, with Plan B additionally requiring the current armed/live scenario.
- Evidence-quality calibration from recency, signal volume, cleaner share, directional stability and exact-opponent continuity.
- Event-derived opponent-context distance across pressing, defensive-depth proxy, attack channels, transition threat and progression/territory.

### Match-day decision workflow

`Match Plan -> Pre-match Precedent Catalog -> Evidence Quality + Opponent Similarity -> Contingency Playbook -> Exact Plan A/B/C Precedents -> Live Playbook -> Live Session -> Session Review -> Evidence Review -> Segment Evidence -> Transition Deltas -> Context-Aware Review -> Intervention Library -> Historical Precedents`

Plan B is never activated merely because historical data predicted an opponent behaviour. A supported manual observation from the staff can activate Plan B when Plan C is not already required by score/manpower context. Plan C keeps precedence for configured score/manpower contingencies.

Live Sessions persist the exact playbook JSON submitted at each staff update. Post-match review therefore reconstructs the historical decision state from immutable snapshots rather than recalculating the past with newer code or newer data.

### Evidence layers

**v1.3 — Objective Post-Match Evidence** compares sampled staff observations with transparent full-match event proxies. It returns `supported`, `partially-supported`, `not-supported` or `insufficient` rather than a coaching-quality score.

**v1.4 — Time-Aligned Segment Evidence** slices events into the same A/B/C intervals reconstructed from Live Session snapshots. Transition-minute events belong to the new segment, and pre-session events remain explicitly unassigned.

**v1.5 — Before/After Transition Deltas** surrounds every recorded tactical transition with symmetric event windows, currently 10 minutes before and after by default. It compares progression, final-third/box access, shots/xG, build-up security, opponent pressure/territory and defensive-transition signals. Coverage is `good`, `caution` or `limited`.

**v1.6 — Context-Aware Transition Review** preserves v1.5 deltas unchanged and adds context from Live Session snapshots plus normalized match events. It detects score/manpower changes, goals, substitutions and direct-red/second-yellow incidents. Interpretability is exposed as `cleaner`, `confounded`, `highly-confounded` or `insufficient`.

**v1.7 — Context-Matched Intervention Library** creates a descriptive tactical memory across multiple closed linked sessions. Historical interventions are grouped only when scenario, destination plan/mode, minute band, score state and manpower state match. Cleaner cases form the primary aggregate when the sample is sufficient; highly-confounded and insufficient cases remain visible but are excluded from aggregate medians.

**v1.8 — Historical Precedent Retrieval** connects that intervention memory to the What-if Lab. It retrieves only groups that exactly match the current minute band, score state and manpower state, joins them to scenarios in the current Full Matchup Scan and orders them using current scenario relevance plus historical sample maturity/volume/cleaner share. There is no fuzzy fallback.

**v1.9 — Precedent-Aware Match Planning** carries that memory into the Match Plan and Plan A/B/C workflow. Pre-match planning keeps every historical minute/score/manpower context separate for each current scenario. During Contingency and Live Playbook use, exact-context precedents are assigned only to the same historical destination plan; Plan B also requires the same currently armed or live-activated scenario.

**v1.10 — Evidence Quality & Recency Calibration** adds a separate 0–100 quality signal using recency, signal-sample volume, cleaner-context share, directional stability and exact-opponent continuity. Missing dates receive no recency credit. This score calibrates trust in historical evidence only.

**v1.11 — Opponent Context Distance** adds a second independent evidence axis. Historical opponents are reconstructed from event data only up to each precedent match date and compared with the current rival across pressing, defensive-depth proxy, attack-channel distribution, transition threat and progression/territory. The same club is not forced to 100 similarity because tactical style can change over time.

v1.10 and v1.11 are intentionally not combined into one score. A precedent can be high-quality but tactically dissimilar, or lower-quality but highly similar.

No layer currently claims causal coaching impact.

## Method boundaries

The current version deliberately does not claim to model full team block height, compactness, every off-ball pressure, causal coaching impact or tracking-derived player geometry unless the necessary data exists.

Even exact-context historical precedents can differ in opponent quality, fatigue, exact scoreline, player identities, injuries, substitutions, tournament incentives and unobserved tactical structure. Coach interventions are selected rather than randomized, so repeated historical association is useful tactical evidence but not a treatment-effect estimate.

Opponent-context similarity is not a team-strength rating and does not use opaque embeddings. Similar event-derived tactical profiles can still hide major differences in player quality, competition strength or off-ball geometry.

## Key REST surfaces

Examples include:

- `GET /wp-json/tactitwin/v1/planning/match-plan`
- `GET /wp-json/tactitwin/v1/planning/what-if`
- `GET /wp-json/tactitwin/v1/planning/contingency-playbook`
- `POST /wp-json/tactitwin/v1/planning/live-playbook`
- persistent Live Session create/update/read/close routes
- `GET /wp-json/tactitwin/v1/planning/live-sessions/{session_uuid}/review`
- `GET /wp-json/tactitwin/v1/planning/live-sessions/{session_uuid}/matching-matches`
- `POST /wp-json/tactitwin/v1/planning/live-sessions/{session_uuid}/link-match`
- `GET /wp-json/tactitwin/v1/planning/intervention-library?team_id={team_id}`

What-if accepts optional `precedent_limit` (1–10, default 5). Match Plan accepts `precedent_context_limit` (1–10, default 3). Contingency and Live Playbook accept `precedent_limit` (1–10, default 5).

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
- **v1.7 — Context-Matched Intervention Library**
- **v1.8 — Historical Precedent Retrieval**
- **v1.9 — Precedent-Aware Match Planning**
- **v1.10 — Evidence Quality & Recency Calibration**
- **v1.11 — Opponent Context Distance**

## Repository workflow

Development flows through feature branches and pull requests into `main`, with automated syntax and regression checks before merge. The CI workflow cancels obsolete runs per branch/event so micro-commits do not accumulate stale validation jobs.

## Status

Active coach-usable MVP development. The current product spans pre-match tactical analysis, lineup decision support, contingency planning, explicit live staff observation, persistent match-day audit trails, full-match and time-aligned post-match evidence, before/after transition review, explicit context/confounder annotations, a cross-match intervention memory, exact-context precedent retrieval, precedent-aware Match Plan / Plan A/B/C evidence, evidence-quality calibration and transparent opponent-context similarity.
