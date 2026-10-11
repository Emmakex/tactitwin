# TactiTwin Architecture

## Goal

Build a provider-independent football intelligence system whose first delivery surface is a WordPress plugin.

## Core flow

1. Fetch public/open football data.
2. Normalize every provider into TactiTwin domain objects.
3. Compute tactical metrics and team/player profiles.
4. Run scenario rules and weighted simulations.
5. Produce explainable recommendations and match-plan outputs.

## Main layers

### WordPress surface

The WordPress plugin owns administration screens, configuration, permissions, persistence orchestration and REST endpoints.

### Data connectors

Each provider adapter translates its external schema into TactiTwin's normalized model. Tactical code must never depend directly on a provider response.

Initial adapters:

- StatsBomb Open Data
- football-data.org
- OpenFootball / football.json

### Normalized domain model

Initial entities:

- Competition
- Season
- Team
- Player
- Match
- Lineup
- Event
- Pass
- Shot
- Carry
- Duel
- Recovery
- Possession
- TacticalProfile
- Scenario
- ScenarioResult
- Recommendation

Every normalized record should retain source provenance and source identifiers so an analyst can trace a recommendation back to evidence.

### Tactical engine

Computes reusable metrics and profiles such as:

- progression by zone
- final-third entries
- shot quality
- possession losses
- recoveries
- pressure proxies
- side bias
- transition exposure
- player involvement
- passing relationships

### Simulation engine

Runs deterministic and probabilistic `what-if` scenarios from normalized evidence, rules, configurable weights and assumptions.

The initial engine should favor explainability over opaque predictions.

### Reporting

Outputs should include:

- risk/opportunity score
- evidence used
- tactical recommendation
- confidence/coverage indicator
- scenario assumptions
- suggested coaching response

## WordPress persistence

Phase 1 should keep its own normalized data locally and cache external responses. Provider payloads may change; the internal contract should not.

Large event datasets should use dedicated plugin tables rather than WordPress posts/meta.

## Evolution path

The domain and simulation contracts must be written so the heavy computation can later move to a Python/Node service without changing the product model or WordPress user experience.
