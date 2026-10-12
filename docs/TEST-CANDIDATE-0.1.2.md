# TactiTwin Test Candidate 0.1.2

This candidate includes the pre-match temporal leakage guard discovered during the first real WordPress end-to-end test.

## Fixed

For an active Match Workspace fixture, data preparation now:

- excludes the active fixture by `source_match_id`;
- uses only matches strictly earlier than the fixture date;
- applies the same historical cutoff to our team and opponent;
- exposes the applied scope in preparation metadata;
- leaves unrelated manual matchup analysis unscoped.

## Retest point

Continue from the already imported Bayer Leverkusen vs Augsburg fixture (`statsbomb / 3895348`, 2024-05-18). The imported target-match events may remain in the database; pre-match preparation must not use them.
