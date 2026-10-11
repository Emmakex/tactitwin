# TactiTwin

**Football Tactical Intelligence & Match Simulation**

TactiTwin is a football tactical analysis and match-scenario simulation project. The first deliverable is a WordPress plugin that consumes public/open football data, normalizes it into an internal model, and turns it into reproducible tactical analysis, scenario simulations and match-plan recommendations for coaches and players.

## Product direction

`public/open data -> normalized football model -> tactical analysis -> scenario engine -> recommendations -> match plan`

TactiTwin is not intended to predict a final score with certainty. It is a decision-support system for exploring tactical risks, opportunities and "what if" scenarios before a match.

## Phase 1 — WordPress MVP

The first phase focuses on:

- Public/open football-data connectors.
- Teams, players, opponents and matches.
- A provider-independent normalized data layer.
- Tactical metrics and opponent profiling.
- Scenario simulation with rules, weights and scores.
- Match-plan recommendations with explainable evidence.
- WordPress administration UI and REST endpoints.

Initial data-source targets:

- StatsBomb Open Data for event-level analysis where available.
- football-data.org for fixtures, results, standings and competition context.
- OpenFootball / football.json as an open fallback/import source.
- Manual coach inputs for information public APIs cannot know.

## Planned modules

- `wordpress-plugin/` — installable WordPress plugin.
- `core/` — shared domain model and contracts.
- `data-connectors/` — external-source adapters and normalization.
- `tactical-engine/` — metrics, profiles, risks and opportunities.
- `simulation-engine/` — tactical what-if scenarios.
- `reports/` — match-plan and analysis outputs.
- `docs/` — architecture, roadmap and data-source documentation.
- `tests/` — automated tests and fixtures.

## Initial roadmap

- **v0.1 — Core + Data Layer**
- **v0.2 — Analysis Engine**
- **v0.3 — Scenario Engine**
- **v0.4 — Match Planner**
- **v0.5 — Tactical Assistant**
- **v1.0 — Coach-usable MVP**

## Repository workflow

Development should flow through feature branches and pull requests into `main`, with automated checks before merge.

## Status

Repository bootstrap in progress.
