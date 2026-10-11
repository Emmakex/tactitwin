# TactiTwin

**Football Tactical Intelligence & Match Simulation**

TactiTwin is a football tactical analysis and match-scenario simulation project. The first deliverable is a WordPress plugin that consumes public/open football data, normalizes it into an internal model, and turns it into reproducible tactical analysis, scenario simulations and match-plan recommendations for coaches and players.

## Product direction

`public/open data -> normalized football model -> tactical analysis -> scenario engine -> lineup decisions -> match plan -> Plan A/B/C -> evidence suitability/robustness -> evidence decision brief -> evidence conflict signals -> match-day staff brief -> imported match target -> match-centric planning context -> exact fixture identity -> version diff -> approval revision chain -> approved immutable brief snapshot -> live session handoff -> live staff observations -> persistent match-day timeline -> post-match review -> full-match evidence -> time-aligned segment evidence -> before/after transition deltas -> context-aware transition review -> context-matched intervention library -> historical precedent retrieval`

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
- Evidence-suitability ranking over already eligible historical precedents.
- Deterministic sensitivity analysis of evidence-ranking weights.
- Compact staff-facing evidence decision briefs that preserve the existing evidence order.
- Named evidence-conflict signals that expose disagreements between existing evidence dimensions without creating another score.
- A printable Match-Day Staff Brief that composes existing tactical and evidence outputs into one bench/meeting surface.
- Imported-match targeting from the normalized match store.
- One shared match-centric planning context across the main WordPress planning surfaces.
- Exact fixture identity for Staff Brief versioning and approval scope.
- Explicit Staff Brief approval with immutable SHA-256 snapshots and direct Live Session handoff.
- Structural Staff Brief version comparison before reapproval.
- Immutable approval revision lineage with reviewed-diff integrity and stale-review protection.
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
- A dedicated pre-match Staff Brief surface using a neutral 0–0, minute-1, 11v11 reference context.
- Browser print / Save-as-PDF output for the Staff Brief without adding a server-side PDF dependency.
- Selection of a canonical imported match as the planning target, with automatic participant/opponent resolution.
- A persistent per-user match context reused across Scenario Lab, Match Plan, What-if, Live Playbook and Staff Brief.
- Optional exact target-fixture binding from provider match ID or scheduled fixture metadata.
- Explicit approval/freeze of the exact Staff Brief source Match Plan and Contingency Playbook.
- Immutable approved Staff Brief snapshots with UUID + SHA-256 integrity checks.
- Structural comparison against the latest approved Staff Brief for the same exact fixture when fixture identity is available.
- Immutable approval revision records linking each new approval to the predecessor actually reviewed inside the same fixture scope.
- Required acknowledgement when tracked Staff Brief changes exist.
- Stale-review protection that rejects approval if the parent snapshot or reviewed diff changed after page render.
- Optional staff approval notes stored inside the immutable revision record.
- One-to-one idempotent handoff from an approved Staff Brief into a Live Session.
- Automatic inheritance of a fixture provider match ID into Live Session handoff when available.
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
- Evidence-suitability ordering that combines current relevance, evidence quality and opponent similarity only after exact-context eligibility has been established.
- Ranking-robustness diagnostics that show whether evidence priority changes under alternative transparent weight profiles.
- Evidence Decision Briefs that expose a primary precedent, strongest visible alternative, fit reasons, historical support and invalidation risks without creating a new tactical score.
- Evidence Conflict Signals for high-quality/low-similarity analogies, high-similarity/low-quality evidence, fragile high-suitability leaders, near ties, opposed historical deltas and missing evidence axes.

### Match-day decision workflow

`Imported Match Target -> Match-Centric Planning Context -> Match Plan -> Pre-match Precedent Catalog -> Evidence Quality + Opponent Similarity -> Evidence Suitability Ranking -> Ranking Robustness -> Evidence Decision Brief -> Evidence Conflict Signals -> Plan A/B/C -> Match-Day Staff Brief -> Exact Fixture Identity -> Version Diff -> Explicit Approval -> Approval Revision Chain -> Immutable Snapshot -> Live Session Handoff -> Live Playbook -> Session Review -> Evidence Review -> Segment Evidence -> Transition Deltas -> Context-Aware Review -> Intervention Library -> Historical Precedents`

Plan B is never activated merely because historical data predicted an opponent behaviour. A supported manual observation from the staff can activate Plan B when Plan C is not already required by score/manpower context. Plan C keeps precedence for configured score/manpower contingencies.

The v1.16 Staff Brief uses minute 1, 0–0 and 11v11 only as a neutral **pre-match reference context**. It does not assert that the match will remain in that state and it cannot activate Plan B or Plan C. Live activation remains governed by the existing observation, score and manpower rules.

With v1.17, staff can explicitly approve that brief. Approval freezes the exact source Match Plan and Contingency Playbook in an immutable snapshot with a SHA-256 content digest. Live Session handoff uses the frozen playbook rather than recalculating the pre-match plan, and the session keeps the approved snapshot UUID as its origin.

With v1.18, a newly generated Staff Brief is compared structurally with the latest approved snapshot before reapproval. The diff reports literal changed fields/groups and never converts them into a tactical or severity score.

With v1.19, the approval stores the predecessor actually reviewed plus a language-invariant SHA-256 digest of the reviewed v1.18 machine diff. If the predecessor or diff changes before the approval request is processed, the approval is rejected and the staff must review the current state again.

With v1.19.1, the revision is bound to both the exact approved snapshot UUID and its exact frozen content digest, preventing revision swaps even when two approved snapshots contain identical tactical content.

With v1.20, a Staff Brief can be bound to one exact fixture. Provider match IDs form the preferred identity; when no provider ID exists, a deterministic scheduled composite can use date plus available competition/season/kick-off/week context. Version diff, approval history and revision numbering stay inside that fixture scope, so two separate matches against the same opponent cannot share one new fixture-bound approval chain. Legacy snapshots remain valid and isolated in their original matchup-only scope.

With v1.21, the Staff Brief can select a real match already stored in `tactitwin_matches`. TactiTwin resolves the selected team perspective and opponent from that persisted row before tactical calculation, then derives the v1.20 fixture identity from the stored provider match identity. Browser fields are convenience only; the server-side normalized match record remains authoritative. Manual fixture binding remains available as a fallback.

With v1.22, that imported match becomes a persistent per-user planning context. Scenario Lab, Match Plan, What-if, Live Playbook and Staff Brief all receive the same re-resolved team/opponent pair from the canonical match row. Staff Brief additionally receives the same `target_match_id`, so the v1.21 resolver and v1.20 fixture-integrity chain remain the source of truth for approval and Live Session handoff.

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

**v1.12 — Evidence Suitability Ranking** combines three already-explainable signals only to order eligible historical evidence: 45% current scenario relevance, 35% v1.10 evidence quality and 20% v1.11 opponent similarity. Missing components are removed and remaining weights are renormalized. What-if reranks up to 10 exact-context candidates before applying the requested result limit. Match Plan reranks historical contexts only within each existing scenario and never changes scenario order.

**v1.13 — Evidence Ranking Robustness** tests the v1.12 evidence order under five deterministic weight profiles: baseline, balanced, relevance-heavy, quality-heavy and similarity-heavy. It reports top-rank stability, alternative top candidates, rank ranges and the baseline top margin while preserving the original v1.12 order. Match Plan runs the sensitivity analysis independently within each scenario.

**v1.14 — Evidence Decision Brief** converts the already ranked evidence into a compact descriptive staff brief. The existing first-ranked item is the primary precedent and the second visible item is the alternative. The brief explains exact context, relevance, suitability, quality, opponent similarity, rank robustness, sample support and historical median deltas, and surfaces explicit invalidation/caution flags. Plan A/B/C briefs are generated only after destination-plan and Plan-B-scenario filters have been applied.

**v1.15 — Evidence Conflict Signals** adds named, deterministic tension flags to the v1.14 brief. It detects high-quality evidence with weak opponent similarity, strong opponent similarity with weak evidence quality, high suitability with fragile rank leadership, near-tied primary/alternative precedents, materially opposed shared historical deltas and missing primary evidence axes. The UI exposes `none`, `watch` or `material` states plus the exact triggering evidence; there is no aggregate conflict score.

### Staff surface

**v1.16 — Match-Day Staff Brief** is a presentation-only WordPress surface. It composes the already calculated Match Plan and precedent-aware Plan A/B/C into a single printable sheet with the top three tactical priorities, plan instructions/triggers, primary and alternative historical precedents, evidence tensions, opening-15-minute checks, halftime checks and the existing decision tree. It uses a 0–0, minute-1, 11v11 pre-match reference context and does not create a new tactical score, reorder priorities, change evidence order or activate a plan.

**v1.17 — Match-Day Brief Snapshot & Session Handoff** adds explicit staff approval and persistence. Approval creates an immutable snapshot containing the compact Staff Brief plus the exact source Match Plan and Contingency Playbook, protected by a SHA-256 content digest. Handoff verifies integrity, creates one Live Session for that snapshot and writes the frozen playbook as the first append-only session snapshot. Repeated handoff requests reopen the same session instead of duplicating it.

**v1.18 — Staff Brief Version Diff** compares a generated Staff Brief with the latest approved version, or compares two integrity-valid approved snapshots. It exposes stable change IDs, literal before/after values and changed sections without creating a score, assigning tactical severity or mutating either version. With v1.20 fixture identity present, both sides must refer to the same exact fixture.

**v1.19 — Staff Brief Approval Revision Chain** seals repeated approvals into an immutable lineage. Each new v1.19 snapshot records its revision number, reviewed parent UUID/content digest, reviewed v1.18 machine diff + digest, stable change IDs and optional staff note. Changed revisions require explicit acknowledgement, and approval is rejected if the parent or reviewed diff became stale before persistence.

**v1.19.1 — Approval Revision Snapshot Binding** binds each sealed revision to the exact approved snapshot UUID in addition to its content digest, closing the same-content/different-snapshot swap case without changing tactical behavior.

**v1.20 — Exact Fixture Identity** adds a deterministic `fixture_key` to new fixture-bound Staff Briefs, approved snapshots and revision records. Provider match ID is preferred; otherwise scheduled metadata can create a composite identity. Snapshot storage is indexed by fixture, version diffs and revision parents cannot cross fixture boundaries, and provider match IDs flow automatically into Live Session handoff when available. Existing snapshots without fixture identity remain valid legacy records.

**v1.21 — Imported Match Target Resolution** adds a canonical imported-match selector to the Staff Brief workflow. The normalized match row supplies provider identity and schedule context, while server-side participant validation resolves the correct opponent before Match Plan generation. It is an input-resolution layer only and does not alter tactical calculations.

**v1.22 — Match-Centric Planning Context** promotes that canonical match target into a shared per-user planning context. The same match + team perspective is reused by Scenario Lab, Match Plan, What-if, Live Playbook and Staff Brief. Every submitted action re-resolves the normalized match row server-side, and manual selection explicitly clears the shared context.

v1.12 does not use the legacy v1.8 `retrieval_score` or v1.9 `support_order_score` inside its formula, avoiding double counting of historical maturity, volume and cleaner-share evidence already represented by v1.10. Legacy scores and original ranks remain visible for audit.

v1.13 is a sensitivity diagnostic, not a second ranking engine. A stable result means the evidence priority is less dependent on the tested weighting assumptions; it does not imply causal certainty or guarantee that repeating an intervention will produce the same result.

v1.14 creates no new score and performs no re-ranking. The alternative label is descriptive evidence navigation only. The brief cannot activate a plan or change any tactical recommendation.

v1.15 creates no conflict score and performs no re-ranking. A conflict signal is a prompt for staff review when existing evidence dimensions disagree, not a veto, tactical recommendation or causal conclusion.

v1.16 is a presentation layer, not a second source of tactical truth. Its trimming of priorities, instructions and checks is display-only and always preserves the existing upstream order.

v1.17 is an audit/persistence layer. Approval freezes existing outputs; handoff never recalculates them and cannot change tactical scores, recommendations, evidence order or Plan A/B/C activation logic.

v1.18 is a deterministic audit diff. Its `change_count` is a literal number of tracked structural changes, not a tactical importance or severity score.

v1.19/v1.19.1 form an approval-lineage and integrity layer. They record which predecessor and exact machine diff were reviewed and bind the revision to the approved snapshot; they do not authorize tactical actions or change any upstream calculation.

v1.20 is a fixture-scope/audit layer. It answers which exact match a new Staff Brief revision belongs to and does not change tactical calculations, recommendations, evidence ordering or plan activation.

v1.21 is a canonical input-resolution layer. It chooses which persisted match the Staff Brief is preparing for and validates the team/opponent relationship before analysis; it does not create a new tactical signal or score.

v1.22 is a workflow-consistency layer. It reuses one canonical match target across planning surfaces and revalidates it server-side before each calculation; it does not change any tactical score, recommendation, evidence order or plan activation.

No layer currently claims causal coaching impact.

## Method boundaries

The current version deliberately does not claim to model full team block height, compactness, every off-ball pressure, causal coaching impact or tracking-derived player geometry unless the necessary data exists.

Even exact-context historical precedents can differ in opponent quality, fatigue, exact scoreline, player identities, injuries, substitutions, tournament incentives and unobserved tactical structure. Coach interventions are selected rather than randomized, so repeated historical association is useful tactical evidence but not a treatment-effect estimate.

Opponent-context similarity is not a team-strength rating and does not use opaque embeddings. Similar event-derived tactical profiles can still hide major differences in player quality, competition strength or off-ball geometry.

Evidence suitability is an evidence-navigation score. It may reorder already eligible historical precedents, but it cannot change tactical scores, active-plan logic, recommendations or the underlying historical metrics. Exact-context matching remains a hard upstream requirement and v1.12 does not introduce fuzzy fallback.

Ranking robustness preserves the baseline suitability order and only measures sensitivity to predefined alternative weights. It does not estimate statistically optimal weights, modify suitability values or authorize tactical actions.

The Evidence Decision Brief is a communication layer. It reads the existing order and evidence fields, summarizes them and exposes caveats. It does not create a new confidence/tactical score, reorder evidence, infer causal effects or authorize tactical actions.

Evidence Conflict Signals compare existing evidence axes and historical deltas with fixed, documented thresholds. They do not decide which axis is more important, compute a hidden aggregate confidence score, change the v1.12 ranking or authorize a plan change.

The Match-Day Staff Brief only composes existing outputs for rapid staff consumption. The pre-match reference context is not a forecast, and browser print/PDF output does not create a separate persisted tactical state.

Approved Staff Brief snapshots and approval-revision SHA-256 digests provide integrity and historical provenance, not digital signatures or causal certainty. They detect content/revision changes relative to stored digests; they do not prove who physically approved the brief beyond the authenticated WordPress user record.

For new fixture-bound Staff Briefs, revision lineage is scoped to the deterministic `fixture_key`. This prevents a different match against the same opponent from entering the chain. Legacy snapshots created without fixture identity remain matchup-scoped and are intentionally kept separate rather than being guessed or retroactively assigned to a fixture.

Fixture identity is an audit key, not proof that upstream fixture metadata is authentic. Provider validation and source provenance remain separate responsibilities.

An imported match target is trusted only as a persisted normalized TactiTwin record. v1.21 re-resolves that row server-side and verifies participants before analysis; it does not trust browser-populated fixture fields as canonical input.

The v1.22 planning context stores only a user-scoped working selection. On every supported admin submission, TactiTwin re-resolves the current normalized match record before passing team/opponent IDs to the tactical module. The local `tactitwin_matches.id` is a locator, not a cross-installation football identity.

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

Staff Brief approval, version audit, fixture binding and Live Session handoff are currently authenticated WordPress admin actions rather than new REST endpoints.

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
- **v1.12 — Evidence Suitability Ranking**
- **v1.13 — Evidence Ranking Robustness**
- **v1.14 — Evidence Decision Brief**
- **v1.15 — Evidence Conflict Signals**
- **v1.16 — Match-Day Staff Brief**
- **v1.17 — Match-Day Brief Snapshot & Session Handoff**
- **v1.18 — Staff Brief Version Diff**
- **v1.19 — Staff Brief Approval Revision Chain**
- **v1.19.1 — Approval Revision Snapshot Binding**
- **v1.20 — Exact Fixture Identity**
- **v1.21 — Imported Match Target Resolution**
- **v1.22 — Match-Centric Planning Context**

## Repository workflow

Development flows through feature branches and pull requests into `main`, with automated syntax and regression checks before merge. The CI workflow cancels obsolete runs per branch/event so micro-commits do not accumulate stale validation jobs.

## Status

Active coach-usable MVP development. The current product spans canonical imported-match targeting, a shared per-user match-centric planning context, pre-match tactical analysis, lineup decision support, contingency planning, a printable and exact-fixture-bindable Match-Day Staff Brief, immutable staff-approved pre-match snapshots, fixture-scoped structural preapproval version diffs, immutable approval revision lineage, stale-review protection, direct Live Session handoff, explicit live staff observation, persistent match-day audit trails, full-match and time-aligned post-match evidence, before/after transition review, explicit context/confounder annotations, a cross-match intervention memory, exact-context precedent retrieval, precedent-aware Match Plan / Plan A/B/C evidence, evidence-quality calibration, transparent opponent-context similarity, auditable evidence-suitability ranking, deterministic ranking-robustness diagnostics, compact staff-facing evidence decision briefs and explicit evidence-conflict signals.