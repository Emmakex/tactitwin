# TactiTwin

**Football Tactical Intelligence & Match Simulation**

TactiTwin is a football tactical analysis and match-scenario simulation project. The first deliverable is a WordPress plugin that consumes public/open football data, normalizes it into an internal model, and turns it into reproducible tactical analysis, scenario simulations and match-plan recommendations for coaches and players.

## Product direction

`public/open data -> normalized football model -> tactical analysis -> scenario engine -> recommendations -> match plan -> game-state what-if`

TactiTwin is not intended to predict a final score with certainty. It is a decision-support system for exploring tactical risks, opportunities and "what if" scenarios before and during match preparation.

## Phase 1 — WordPress MVP

The first phase focuses on:

- Public/open football-data connectors.
- Teams, players, opponents and matches.
- A provider-independent normalized data layer.
- Tactical metrics and opponent profiling.
- Scenario simulation with rules, weights and scores.
- Match-plan recommendations with explainable evidence.
- Historical score-state conditioning for leading/drawing/trailing What-if scenarios.
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

### Game State / What-if

The What-if Lab accepts a hypothetical minute and exact scoreline, for example minute 80 at 1-0. TactiTwin then conditions the tactical response on historically observed behaviour while the selected team was leading, drawing or trailing and on the opponent's reciprocal score state.

Current score-state evidence includes observed minutes, shots, xG, progressive actions, final-third entries, box entries and pressing activity normalized per 90 minutes. The exact hypothetical scoreline is retained as context, while the historical sample is currently grouped by leading/drawing/trailing rather than exact past scoreline.

The current version deliberately does not claim to model cards, substitutions, home/away splits, exact opponent-specific score states, line height or compactness unless the necessary data exists. Tracking/video-derived positional data is planned for the latter spatial signals.

REST endpoint:

`GET /wp-json/tactitwin/v1/planning/what-if`

Parameters: `team_id`, `opponent_id`, `our_score`, `opponent_score`, `minute`, `limit`, `lang`.

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
- **v0.3 — Scenario Engine + Game State What-if**
- **v0.4 — Match Planner**
- **v0.5 — Tactical Assistant**
- **v1.0 — Coach-usable MVP**

## Repository workflow

Development should flow through feature branches and pull requests into `main`, with automated checks before merge.

## Status

Active MVP development. The current product already combines normalized event data, explainable tactical scenarios, a Full Matchup Scan, a bilingual Match Plan and historical score-state What-if planning.
