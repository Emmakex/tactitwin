# TactiTwin Test Candidate 0.1.6

## Purpose

Close the second pre-match temporal leakage path found during the first real Match Plan test.

The data-preparation layer already excluded the active fixture and future matches, but `TactiTwin_Profile_Service` rebuilt team profiles from the latest imported event matches without reusing that scope. Once the target fixture itself had events imported for later post-match use, it could therefore re-enter the tactical profile.

## Fix

- expose one normalized matchup scope from `TactiTwin_Data_Preparation_Service`
- reuse the same scope while building both team and opponent tactical profiles
- enforce `match_date < active fixture date`
- enforce `source_match_id <> active target match`
- keep manual/unbounded workflows unchanged when no matching planning context exists

## Regression

`tests/test-profile-temporal-scope.php` simulates an active Bayer Leverkusen vs Augsburg fixture and verifies that both profile queries receive the exact cutoff date and target-match exclusion.

## Runtime retest

Keep the current WordPress data and active fixture. Install 0.1.6, open Match Plan and regenerate with 5 recent matches. Do not re-import the target fixture or competition.
