# Test Candidate runtime notes

## Pre-match temporal cutoff

When a Match Workspace fixture is active and the requested team/opponent pair matches that exact context, TactiTwin's data preparation layer now enforces a strict historical sample boundary:

- only matches with `match_date < active_fixture.match_date` are eligible;
- the active fixture `source_match_id` is explicitly excluded;
- the same scope is applied to both our team and the opponent;
- unrelated manual matchup analysis is not silently constrained by the active workspace fixture;
- the applied temporal scope is exposed in preparation coverage metadata for auditability.

This prevents historical test fixtures from leaking their own events, or future events, into pre-match tactical analysis.
