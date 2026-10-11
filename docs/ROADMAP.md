# TactiTwin Roadmap

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
