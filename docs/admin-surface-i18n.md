# v1.37 — Admin Surface I18n Sweep

## Goal

Remove the most visible ES/EN mixing from the legacy WordPress admin surfaces without touching tactical calculations or duplicating translations across individual admin classes.

v1.36 owns menu labels. v1.37 covers legacy page copy that already passes through the `tactitwin` WordPress text domain.

## Central runtime bridge

`TactiTwin_Admin_Translations` installs a domain-scoped `gettext` filter.

It only changes strings when:

- the text domain is exactly `tactitwin`;
- the current TactiTwin/WordPress language resolves to Spanish.

English remains unchanged and unrelated plugins/domains are never touched.

## First sweep

The initial central catalog covers the copy visible in the first end-to-end test on:

- Data & Imports
- Scenario Lab
- Match Plan
- shared preparation form labels
- core permission message

This includes page titles, introductions, field labels, action buttons, scenario selector names and technical-evidence headings.

## Import notices

Competition and match-import success notices were previously constructed dynamically in English. v1.37 translates the stored per-user admin notice before persistence when WordPress is in Spanish, while preserving provider names, IDs and numeric counts.

## Boundaries

This bridge is an incremental compatibility layer, not a replacement for the domain-level `TactiTwin_I18n` catalog used by tactical outputs.

It does not:

- translate other WordPress/plugin domains;
- alter source/provider names;
- alter tactical outputs;
- alter stored match/event data;
- change page slugs, callbacks or permissions;
- create a database migration.

## Exit criterion

In a Spanish WordPress admin session, the main shell copy for Data & Imports, Scenario Lab and Match Plan must render in Spanish while the same source strings remain unchanged in English. The translation layer must be strictly scoped to the `tactitwin` text domain, unknown strings must fall back unchanged, and dynamic import notices must preserve all IDs/counts while translating the surrounding copy. Dedicated regressions must prove ES translation, EN invariance and domain isolation.
