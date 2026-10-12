# Reviewed Tactical Twin freeze

When a coach generates a Tactical Twin Lab result, TactiTwin keeps that reviewed result in the existing short-lived per-user Twin Lab transient.

During Staff Brief generation, the Reviewed Twin Freeze bridge runs before the existing Frozen Twin fallback. It reuses the exact reviewed Lab payload only when matchup, sample, language and active fixture identity still match. The existing Frozen Twin handoff then sees a valid signed attachment and does not recalculate it.

If no compatible reviewed result exists, the existing deterministic fallback remains in force. A later filter annotates that attachment as `recomputed-fallback` for auditability.

This changes no tactical scores, response rankings, scenario-tree outputs, switch candidates or Plan A/B/C state. It adds no database table. Staff Brief approval continues to freeze the complete brief through the existing snapshot/hash chain.
