# TactiTwin Test Candidate 0.1.7 — Reviewed Twin Freeze

## Goal

Guarantee that Staff Brief approval freezes the exact Tactical Twin Lab payload that the staff reviewed whenever a compatible Twin Lab result is still available.

## Compatibility contract

A reviewed Twin Lab result is reused only when all of these match the Staff Brief generation request:

- our team source ID
- opponent source ID
- requested recent-match sample
- language
- active fixture key, when an active fixture exists

If any of those differ, Staff Brief generation keeps the existing deterministic fallback and builds a fresh Twin Lab.

## Audit provenance

The frozen Staff Brief records whether its Tactical Twin came from:

- `reviewed-twin-lab-transient`
- `recomputed-fallback`

The existing Tactical Twin content SHA-256 and the outer Staff Brief snapshot hash keep protecting the exact approved payload after attachment.

## Runtime retest

1. Keep Bayer Leverkusen vs Augsburg active.
2. Generate Twin Lab with Spanish and sample 5.
3. Open Staff Brief without changing fixture, language or sample.
4. Generate Staff Brief.
5. Confirm the Frozen Tactical Twin section matches the Twin Lab just reviewed.
6. Approve the Staff Brief and continue to Live Session.

No competition or event re-import is required.
