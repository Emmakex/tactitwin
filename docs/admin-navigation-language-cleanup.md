# v1.36 — Admin Navigation & Menu Language Cleanup

## Goal

Turn the growing WordPress admin surface into one deterministic coach-facing navigation without changing any page slug, callback, workflow action or tactical behavior.

The first real Test Candidate run exposed two navigation problems:

1. several match-day/post-match entries could appear more than once in the TactiTwin submenu;
2. a Spanish WordPress session still showed a mixed ES/EN menu because each admin class owned its own menu label.

v1.36 makes navigation presentation a single responsibility.

## Canonical registry

`TactiTwin_Admin_Navigation` owns the visible order and ES/EN menu label for every canonical TactiTwin admin slug.

Workflow order:

1. Match Workspace
2. Data & Imports
3. Scenario Lab
4. Match Plan
5. Tactical Twin Lab
6. What-if Lab
7. Lineup Lab
8. Personnel Lab
9. Unit Lab
10. Staff Brief
11. Live Playbook
12. Live Sessions
13. Session Review
14. Evidence Review
15. Segment Evidence
16. Transition Deltas
17. Intervention Library

The page slugs remain unchanged.

## Late normalization

Individual admin classes continue registering their own WordPress callbacks. At `admin_menu` priority `999`, the navigation normalizer:

- keeps the first registered row for every canonical slug;
- removes duplicate visible rows for the same slug;
- replaces only the visible menu label using the central ES/EN catalog;
- sorts canonical pages into workflow order;
- preserves unknown/third-party TactiTwin child pages after the canonical entries in their original order.

This deliberately does **not** use CSS or DOM hiding. WordPress receives one normalized submenu array.

## Language

Menu labels use `TactiTwin_I18n::current_language()`, which resolves the WordPress locale and normalizes it to the supported `es` / `en` set.

Spanish examples:

- `Match Workspace` → `Espacio del partido`
- `Data & Imports` → `Datos e importaciones`
- `Match Plan` → `Plan de partido`
- `Live Sessions` → `Sesiones en vivo`
- `Evidence Review` → `Revisión de evidencia`

This milestone standardizes navigation labels. A later surface-copy sweep may continue replacing legacy English copy inside older admin page bodies without coupling that work to menu integrity.

## Boundaries

v1.36 does not:

- rename page slugs;
- change admin callbacks;
- change permissions;
- alter planning context;
- alter Staff Brief/Live Session handoff;
- alter tactical scores, recommendations or evidence;
- create a database migration.

## Exit criterion

Given an admin submenu containing duplicated Live Sessions, Session Review, Evidence Review, Segment Evidence and Transition Deltas rows, normalization must produce exactly one row per slug, deterministic workflow ordering and complete ES/EN labels while preserving the original registered row/callback for the first occurrence. Unknown extension pages must remain reachable after canonical pages. Existing canonical URLs must remain unchanged. Dedicated regression tests must prove uniqueness, order, bilingual catalog parity and registration of the late normalizer.
