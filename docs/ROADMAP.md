# TactiTwin Roadmap

## Current implemented decision-support line

The repository has already evolved beyond the original first-pass roadmap into an auditable pre-match / live / post-match workflow.

Implemented milestones now include:

- v1.10 evidence quality and recency calibration;
- v1.11 opponent tactical-context distance;
- v1.12 evidence suitability ranking;
- v1.13 evidence ranking robustness;
- v1.14 Evidence Decision Brief;
- v1.15 Evidence Conflict Signals;
- v1.16 Match-Day Staff Brief;
- v1.17 immutable approved Staff Brief snapshot + Live Session handoff;
- v1.18 structural Staff Brief version diff;
- v1.19 immutable approval revision chain + stale-review protection;
- v1.19.1 snapshot-identity binding for approval revisions;
- v1.20 exact fixture identity, so approval history, diffs and handoff are scoped to one football match rather than only a team/opponent pairing;
- v1.21 imported Match Target Resolution, so a Staff Brief can select a canonical persisted match and inherit its provider identity, participants and schedule context automatically;
- v1.22 Match-Centric Planning Context, so one imported match + team perspective is reused across Scenario Lab, Match Plan, What-if, Live Playbook and Staff Brief instead of being reselected independently;
- v1.23 Match Workspace, so the active match becomes the WordPress operational home, the full preparation/live/review flow is navigable from one surface, and Lineup, Personnel and Unit Lab join the shared planning context;
- v1.24 Fixture Lifecycle & Workspace Progress, so the Workspace derives the real state of the active fixture from existing artifacts, distinguishes persistent/temporary/on-demand states and recommends the next operational step without inventing a preparation score;
- v1.25 Post-Match Review Sign-off, so a closed session review can be frozen into an immutable, versioned staff sign-off bound to the exact fixture, source Staff Brief, Live Session and objective evidence used at review time;
- v1.26 Signed Review Learning Integration, so Intervention Library and Historical Precedents can distinguish unsigned calculated history from integrity-valid staff-reviewed history without turning sign-off into an effectiveness score or altering default precedent ranking;
- v1.27 Tactical Response Matrix, so one Full Matchup Scan can be stress-tested against several explicit tactical response packages while preserving the original scenario scores, recommendations and evidence boundaries;
- v1.28 Match-State Scenario Tree, so pre-match preparation can evaluate seven explicit minute/score/manpower branches through the existing Context What-if engine without assigning probabilities or silently re-ranking response packages;
- v1.29 Tactical Twin Lab, so coaches can inspect the v1.27 response matrix and v1.28 scenario tree together on one shared Full Matchup Scan in a WordPress workspace that inherits the active match context when available;
- v1.30 Response Robustness Across Match States, so the four existing response packages can be compared against all seven explicit match-state demands and staff can see leader changes and rank stability without creating outcome probabilities or an aggregate robustness score;
- v1.31 Response Switch Playbook, so v1.30 leader changes become explicit staff review points with target response, state trigger, existing validation signals, compatibility margin and coverage caution while keeping every tactical switch manual;
- **v1.32 Frozen Twin Handoff**, so the exact Tactical Twin reviewed before kickoff is attached to the Staff Brief, frozen by the existing approval SHA-256 chain, tracked by structural version diff and recovered from the Live Session origin without recalculation.

### v1.32 exit criterion

For one generated Staff Brief, TactiTwin must attach and visibly expose the exact Tactical Twin Lab preparation before approval, using the same team, opponent, sample size and language. Staff Brief approval must freeze that complete Twin inside the existing immutable Staff Brief snapshot and SHA-256 integrity boundary, with no new persistence table. A changed baseline response, robust response or response-switch map must change the compact Twin signature, appear as `tactical-twin-preparation-changed` in the Staff Brief structural version diff and therefore participate in the existing revision/stale-review guard before reapproval. A Live Session created from the approved brief must resolve the same frozen Twin through its existing `staff_brief_snapshot_uuid` and display it without recalculating tactical models. Twin attachment integrity and matchup identity must be verified before display, switch candidates must remain manual review points, and legacy approved snapshots without Tactical Twin preparation must remain integrity-valid. Dedicated regression tests must prove freeze persistence, outer snapshot tamper detection, version-diff tracking, matchup rejection and legacy compatibility.

### v1.31 exit criterion

For one valid Twin Lab result, TactiTwin must generate one switch-review item for every v1.28 match-state branch. Each item must preserve the v1.27 baseline response, derive its target only from the corresponding v1.30 branch leader, reuse existing v1.28 decision triggers and recommendations as validation context, expose the first-vs-second branch compatibility margin without presenting it as confidence, and carry low general/manpower historical coverage forward as an explicit caution. A branch whose leader differs from baseline is a `switch-candidate`; otherwise it is `retain-baseline`. Every item must declare `automatic_activation=false` and `manual_confirmation_required=true`. v1.31 must not alter v1.27/v1.28/v1.30 outputs, activate Plan A/B/C, predict outcomes or create a new persistence model. ES/EN decisions must remain mathematically invariant and dedicated regression tests must prove representative targets, coverage caution and source immutability.

### v1.30 exit criterion

For one valid Twin Lab result, all four v1.27 response packages must be evaluated against all seven v1.28 branches using documented branch tactical-emphasis profiles over the same five scenario dimensions. Every branch profile must sum to `1.0`, and branch compatibility must be calculated transparently as coverage of state demand by the unchanged response emphasis and unchanged scenario priorities. The original v1.27 baseline ranking must remain intact. v1.30 must expose each branch leader, whether that leader differs from baseline, first-place and Top-2 counts, mean/best/worst rank and rank range for every response. Robustness must remain a rank-stability diagnosis rather than a new aggregate 0–100 robustness score. The layer must not alter v1.28 branch outputs, assign branch probabilities, predict outcomes, activate Plan A/B/C or create new persistence. ES/EN mathematics must remain invariant and dedicated regression tests must prove the documented switch behavior and guardrails.

### v1.29 exit criterion

For one active imported match + team perspective, a coach can open Twin Lab and generate one unified preparation view containing all four v1.27 tactical response packages and all seven v1.28 match-state branches from the same Full Matchup Scan. The WordPress surface must inherit the existing `TactiTwin_Planning_Context` when present, retain manual team/opponent fallback when no active match exists, and expose top response, highest-risk/highest-opportunity branches, coverage warnings, the detailed response/branch tables and full technical evidence. The Lab must remain presentation/orchestration only: it creates no combined tactical score, does not re-rank response packages, does not change branch outputs, predicts no outcome, activates no Plan A/B/C and introduces no new persistence/schema. ES/EN numeric outputs and ordering must remain invariant and dedicated regression coverage must prove that the response matrix and scenario tree share the same source identity and do not mutate the source scan.

### v1.28 exit criterion

For one team/opponent matchup, TactiTwin must evaluate seven explicit preparation branches: opening 0-0, second-half 0-0, protecting 1-0, chasing 0-1, late 0-0, 11v10 and 10v11. Every branch must reuse the existing score-state/manpower Context What-if engine and expose its hypothesis, risk/opportunity outputs, historical coverage, recommendations and decision triggers. The tree may add only descriptive cross-branch diagnostics such as `risk - opportunity`; it must not assign occurrence probability, predict a final score, change upstream tactical scores/recommendations, activate a plan or re-rank v1.27 Tactical Response Matrix packages by branch. ES/EN numeric results and branch order must remain invariant, and the tree must be available through an authenticated REST endpoint with dedicated regression coverage.

### v1.27 exit criterion

For one valid Full Matchup Scan, TactiTwin must compare four deterministic tactical response packages against the same five existing scenario dimensions. The comparison must reuse the Match Plan scenario-priority formula (`0.60*risk + 0.40*opportunity`), expose each response's weights, contributions, dominant driver, existing instructions and underweighted high-priority trade-offs, and provide language-invariant ES/EN numeric output. Response fit must remain a prioritisation index only: it must never mutate the source scan, change tactical scores or recommendations, activate Plan A/B/C, predict a scoreline or win probability, or be presented as causal tactical effectiveness. The matrix must be available through an authenticated REST endpoint and covered by dedicated regression tests.

### v1.26 exit criterion

Intervention Library must preserve its existing all-history behavior by default while offering an explicit signed-review-only learning scope. Signed filtering must happen before group aggregation so sample counts, maturity and deltas genuinely describe the selected population. Every case/group must expose signed-review provenance, integrity-invalid signed reviews must never be silently treated as unsigned, and Historical Precedents must carry that provenance forward while keeping the v1.8 `retrieval_score`, exact-context eligibility and default ordering unchanged. Staff sign-off remains workflow/provenance validation, not evidence of causal or tactical effectiveness.

### v1.25 exit criterion

For one closed Live Session created from an approved fixture-bound Staff Brief, staff can inspect the current post-match review and sign off the exact review/evidence payload they saw. TactiTwin must reject sign-off if the session is still active, the fixture chain is missing, the provider match differs, objective evidence is unavailable, or the review hash changed after display. The resulting artifact must be immutable, SHA-256 integrity-bound, versioned without overwriting previous revisions, associated with the authenticated WordPress user, and exposed by Match Workspace as a persistent `signed_off` lifecycle state. The product must explicitly state that this is an application-level staff sign-off rather than a cryptographic digital signature.

### v1.24 exit criterion

For one active fixture, Match Workspace must expose the state and provenance of: target selection, planning-data coverage, Match Plan, Staff Brief, Live Session, imported match evidence and post-match review availability. Approved snapshots and sessions must remain fixture-scoped; temporary Match Plan output must never be presented as fixture-bound persistence; events from another fixture must not satisfy evidence readiness; review availability must not be mislabelled as a persisted completion record; and the Workspace must expose one deterministic next action without converting lifecycle states into a percentage or score.

### v1.23 exit criterion

Opening TactiTwin lands on a Match Workspace where staff can select one imported match + team perspective, see its canonical fixture context and navigate the full pre-match / match-day / post-match workflow. Lineup and Personnel must inherit the canonical team/opponent pair, Unit Lab must inherit the canonical team, and roster selectors must refresh when context changes. Live Sessions must remain handoff-derived; Session Review, Evidence Review, Segment Evidence and Transition Deltas must remain session-derived; Intervention Library must remain cross-session historical; and the former import dashboard must remain available under Data & Imports.

### v1.22 exit criterion

A coach can choose one canonical imported match and team perspective once, then move across the main planning surfaces without re-entering the matchup. Every supported admin action must re-resolve the stored match server-side before tactical calculation, keep manual team selection available as an explicit fallback, and pass the canonical match row into Staff Brief so v1.21/v1.20 fixture integrity continues unchanged.

### v1.21 exit criterion

A coach can select an imported match in the Staff Brief screen instead of manually copying fixture identifiers. TactiTwin must verify that the chosen team participates in that match, resolve the correct opponent before tactical calculation, build the v1.20 fixture identity from the persisted match record, and preserve the existing manual fixture workflow as a fallback.

### v1.20 exit criterion

Two different matches against the same opponent must never share one Staff Brief revision chain when fixture identity is available. The exact fixture must remain integrity-bound through Staff Brief generation, version comparison, approval snapshot and Live Session handoff, while older snapshots remain valid.

## v0.1 — Core + Data Layer

Goal: make the WordPress plugin installable and prove one public-data path end to end.

- WordPress bootstrap and admin shell
- REST namespace and health endpoint
- plugin database schema
- normalized domain contracts
- source provenance model
- StatsBomb Open Data importer
- football-data.org connector
- OpenFootball importer
- caching and rate-limit handling
- fixture/team/player/match persistence
- first end-to-end import test

Exit criterion: import a real match/context from public/open sources into TactiTwin's normalized model and inspect it in WordPress.

## v0.2 — Analysis Engine

- team tactical profile
- player involvement/profile
- progression by zone
- shot/xG-compatible fields where source data permits
- side bias
- possession loss/recovery profile
- transition exposure
- opponent comparison dashboard

Exit criterion: produce a reproducible pre-match team-vs-opponent analysis from normalized data.

## v0.3 — Scenario Engine

- scenario registry
- configurable assumptions
- deterministic rules
- weighted risk/opportunity scoring
- confidence and evidence coverage
- first 10 tactical scenarios
- scenario comparison

Exit criterion: run at least three distinct `what-if` scenarios for the same match and explain the evidence behind each recommendation.

## v0.4 — Match Planner

- match preparation workspace
- selected XI / expected XI
- formation and role inputs
- prioritized tactical risks
- attacking and defensive recommendations
- game-state plans
- export/print-ready report

Exit criterion: a coach can move from selecting a match to a complete pre-match plan without leaving WordPress.

## v0.5 — Tactical Assistant

- natural-language query layer over TactiTwin evidence
- constrained tactical questions
- scenario creation from coach intent
- answer citations/evidence links inside the product

Exit criterion: questions such as "what if we press with two forwards?" produce scenario-backed answers rather than free-form guesses.

## v1.0 — Coach-usable MVP

- stable import workflow
- useful tactical dashboard
- explainable scenarios
- match planner
- report export
- operational documentation
- automated test coverage and CI release checks

## Later phases

- paid/proprietary provider adapters
- tracking/positional data
- physical/wearable data
- probabilistic models
- external Python/Node compute service
- multi-agent simulation
- multi-club/team SaaS capabilities
