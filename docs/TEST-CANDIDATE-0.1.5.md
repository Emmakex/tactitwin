# Test Candidate 0.1.5

Runtime hotfix discovered during the first end-to-end WordPress test.

## Fixed

The shared planning-context selector could show the active home team as both **Our team** and **Opponent** when StatsBomb numeric team IDs were used.

Root cause: numeric-string IDs used as PHP array keys were coerced to integers, so a strict identity comparison failed while deriving the opponent.

The selector now builds explicit home/away perspective records and preserves source IDs as strings.

## Retest

With the existing active fixture:

- Context: Bayer Leverkusen vs Augsburg
- Our team: Bayer Leverkusen
- Opponent: Augsburg
- Recent matches: 5

Do not re-import data. Install over the previous Test Candidate and open Match Plan again before generating the plan.
