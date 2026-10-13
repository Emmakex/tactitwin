# TactiTwin MVP Release Candidate — 0.9.0-rc1

## Purpose

This document defines the functional boundary of the first coach-usable TactiTwin MVP before production installation tests and the dedicated UX refinement pass.

The release candidate is intentionally **function-first**. Visual hierarchy, density, navigation ergonomics and final coach-facing UX will be improved from evidence gathered during production testing rather than by redesigning every screen before the complete workflow is available.

## Functional MVP line

### Data and identity

- WordPress plugin installation and schema upgrades.
- StatsBomb Open Data competition/season and event import.
- Provider-independent normalized competitions, teams, players, matches and events.
- Canonical imported match target.
- Exact fixture identity and persistent per-user planning context.
- Pre-match temporal scope that excludes the target fixture and later matches.
- Per-side evidence coverage with exact match IDs/date windows and limiting side.

### Analysis and preparation

- Team/player/unit/phase-unit analysis.
- Five explainable tactical scenarios and Full Matchup Scan.
- Bilingual ES/EN Match Plan.
- Score/minute/manpower What-if.
- Personnel, unit and lineup comparison.
- Tactical Response Matrix.
- Match-State Scenario Tree.
- Response Robustness Across Match States.
- Response Switch Playbook.
- Tactical Twin Lab.
- Plan A/B/C contingency preparation.

### Historical evidence

- Context-matched Intervention Library.
- Exact-context Historical Precedents.
- Evidence quality and recency.
- Opponent-context similarity.
- Evidence suitability ranking.
- Ranking robustness.
- Evidence Decision Brief.
- Evidence Conflict Signals.
- Signed-review provenance filtering without changing default tactical ranking.

### Match-day workflow

- Match Workspace.
- Fixture lifecycle and deterministic next action.
- Printable Match-Day Staff Brief.
- Exact-fixture Staff Brief binding.
- Structural version diff.
- Explicit approval and immutable SHA-256 snapshot.
- Approval revision chain and stale-review protection.
- Frozen Tactical Twin handoff.
- Live Session handoff.
- Live Playbook and explicit staff observations.
- Persistent append-only tactical snapshots/timeline.
- Manual live Twin switch confirmation and response-decision timeline.

### Post-match workflow

- Session Review.
- Objective full-match evidence.
- Time-aligned Segment Evidence.
- Transition-window deltas.
- Context-aware transition review.
- Immutable versioned post-match staff sign-off.
- Signed-review learning integration.

### Delivery / operations

- PHP syntax and complete regression suite on CI.
- Installable Test Candidate ZIP from `main`.
- Plugin version `0.9.0-rc1`.
- Build manifest, release manifest and ZIP SHA-256.
- Authenticated runtime readiness endpoint: `/wp-json/tactitwin/v1/system/readiness`.
- Browser print / Save-as-PDF for staff-facing reporting.

## Readiness semantics

Runtime readiness is **not** tactical readiness and is not a product-quality score.

A runtime is blocked only by technical issues such as:

- unsupported PHP version;
- schema mismatch;
- database-table/query failure.

Missing football data is reported separately as a data warning because a clean installation can be technically healthy before the first import.

Pre-match fixture readiness is separate again: both teams require at least three eligible event-data matches before the active fixture to move the lifecycle planning-data stage from `partial` to `ready`.

## Explicitly deferred to production tests / UX pass

The following are not release-candidate functional blockers:

- final visual design system;
- reducing admin-screen density;
- reorganizing large technical tables and evidence panels;
- progressive disclosure / advanced-detail patterns;
- final terminology simplification for coaching staff;
- responsive/mobile refinement beyond current functional layout;
- shortcut/navigation optimizations discovered from real workflow use;
- final empty/loading/error-state polish;
- visual prioritization of primary vs secondary actions.

These should be changed only after walking the complete RC through real production fixtures so UX decisions are based on observed friction.

## Post-MVP / non-blocking future scope

- football-data.org adapter.
- OpenFootball/football.json adapter.
- proprietary provider adapters.
- tracking/positional feeds.
- wearables/physical data.
- probabilistic outcome models.
- external Python/Node compute service.
- multi-agent simulation.
- multi-club SaaS/roles/billing.

None of these is required to validate the first open-data coach workflow.

## Release-candidate exit criterion

The MVP code line is considered ready for production testing when:

1. v1.39 lifecycle readiness and v1.40 release hardening are merged to `main`;
2. CI syntax + all regression tests pass on the exact final `main` SHA;
3. Test Candidate packaging succeeds on that SHA;
4. the generated artifact exposes `0.9.0-rc1`, schema version, commit identity and ZIP SHA-256;
5. runtime preflight reports no technical blocker on the production WordPress environment;
6. no known functional blocker remains in the pre-match → approval → live → post-match chain.

After this point, production testing becomes the deliberate source of UX improvements and final release fixes.
