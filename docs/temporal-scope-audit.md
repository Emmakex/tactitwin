# Temporal scope audit metadata

When TactiTwin prepares a matchup for the exact active Match Workspace fixture, the preparation result exposes a `temporal_scope` block that records the historical cutoff date, excluded provider match ID, fixture key, and whether target/future matches were excluded.

This keeps the anti-leakage guard auditable without altering tactical scores or recommendations.
