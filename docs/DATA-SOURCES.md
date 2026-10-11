# Data Sources

TactiTwin phase 1 must work with public/open data first and isolate provider-specific schemas behind adapters.

## StatsBomb Open Data

Primary use:

- event-level historical analysis where competitions are available
- passes, shots, carries, recoveries and possession context
- building reproducible tactical metrics from open event data

Integration approach:

- import published open-data JSON
- normalize provider event identifiers and coordinates
- retain source attribution/provenance
- never expose raw provider schema to the tactical engine

## football-data.org

Primary use:

- competitions
- teams
- fixtures
- results
- standings
- current competition context

Integration approach:

- authenticated API connector
- rate-limit aware cache
- configurable API token stored through WordPress settings
- graceful degradation when quota is exhausted

## OpenFootball / football.json

Primary use:

- open fixture/result imports
- historical fallback
- development fixtures and tests

Integration approach:

- static JSON import
- source-version metadata
- use as fallback/context source, not as an event-data substitute

## Coach/manual data

Public APIs cannot know key private context. TactiTwin must support manual inputs such as:

- availability
- expected role
- tactical instruction
- fitness/fatigue assessment
- preferred formation
- planned substitutions
- qualitative opponent notes

Manual data must be clearly separated from externally sourced evidence.

## Provider-independent contract

Every connector should map into a shared record envelope containing at least:

- internal ID
- source name
- source ID
- source timestamp/version when available
- competition/season/match linkage
- normalized payload
- import timestamp

This allows new paid or proprietary providers to be added later without rewriting the tactical and simulation engines.
