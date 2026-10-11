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
- **v1.24 Fixture Lifecycle & Workspace Progress**, so the Workspace derives the real state of the active fixture from existing artifacts, distinguishes persistent/temporary/on-demand states and recommends the next operational step without inventing a preparation score.

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
