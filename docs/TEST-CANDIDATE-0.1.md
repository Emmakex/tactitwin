# TactiTwin Test Candidate 0.1 — End-to-End WordPress Test

## Purpose

This is the first product-level validation of the complete TactiTwin workflow inside a real WordPress installation. It is not a release certification.

## Environment

- WordPress test/staging site only.
- PHP 8.1+.
- Administrator account.
- Network access to the public/open data sources used by TactiTwin.
- Fresh install preferred for the first run.

## Install

1. Download the latest `tactitwin-test-candidate-<commit>` artifact from the **TactiTwin Test Candidate** GitHub Actions workflow.
2. Extract the Actions artifact once. It contains:
   - `tactitwin-test-candidate.zip`
   - `SHA256SUMS.txt`
3. Verify the SHA-256 when possible.
4. In WordPress: Plugins -> Add New -> Upload Plugin.
5. Upload `tactitwin-test-candidate.zip`.
6. Activate TactiTwin.
7. Confirm that the TactiTwin admin menu loads without a fatal error.

## Test scenario

Use one real match dataset available through the public/open-data path. The objective is to validate the workflow, not the quality of one club's result.

### A. Data and fixture

- Import or prepare enough public/open match data for two teams.
- Confirm both teams appear in TactiTwin.
- Select one canonical imported match and one team perspective.
- Confirm the Match Workspace shows the selected fixture consistently.

### B. Pre-match preparation

- Generate the Full Matchup Scan.
- Generate Match Plan.
- Open Tactical Twin Lab.
- Verify the four response packages are visible.
- Verify all seven match-state branches are visible.
- Verify response robustness and switch candidates are visible.
- Check ES and EN surfaces at least once.

### C. Staff Brief and freeze

- Generate the Match-Day Staff Brief from the active fixture.
- Verify the prepared Tactical Twin block is visible before approval.
- Approve the Staff Brief.
- Record the snapshot UUID and SHA-256 shown by the UI.
- Start a Live Session from that approved Staff Brief.
- Confirm the Live Session shows the frozen Twin origin and does not regenerate a new one.

### D. Live match simulation

Record several manual match states. Recommended first laboratory sequence:

1. 15', 0-0, 11v11.
2. 60', 0-0, 11v11.
3. 62', 1-0, 11v11 -> defer the prepared Twin response review.
4. 68', 1-0, 11v11 -> confirm or reject the prepared response.
5. 74', 1-1, 11v11 -> review the newly resolved branch.
6. 80', 1-1, 11v10 -> verify numerical-superiority structural mapping.

At each state:

- save a Live Session snapshot;
- check that Plan A/B/C remains independently visible;
- check the frozen Twin branch shown for the state;
- record confirm / reject / defer where a prepared branch exists;
- confirm that unsupported states do not force a branch.

### E. Response Decision Timeline

- Verify all recorded Twin response decisions appear in chronological order.
- Confirm defer -> confirm/reject sequences are preserved.
- Confirm the response decision and active Plan A/B/C can differ without one rewriting the other.
- Confirm staff notes are visible.
- Confirm the timeline does not invent rows for snapshots where no Twin review was recorded.

### F. Close and post-match

- Close the Live Session.
- Open Session Review.
- Link/import objective match evidence when available.
- Review full-match, segment and transition evidence.
- Generate the post-match review.
- Sign off the post-match review when all required evidence is present.
- Verify Match Workspace lifecycle reaches the expected final state.

## Pass criteria

The first Test Candidate passes when:

- plugin installs and activates without fatal errors;
- public/open data can reach the normalized model;
- one canonical fixture flows through planning, Twin, Staff Brief approval and Live Session handoff;
- the approved Twin is frozen and remains the same during the Live Session;
- live staff decisions are append-only and do not silently change Plan A/B/C;
- Response Decision Timeline reconstructs the recorded decisions correctly;
- the session closes and post-match review/sign-off can complete with valid evidence;
- no cross-fixture or stale-approval integrity guard is bypassed.

## What to capture when something fails

For every failure record:

- WordPress page/surface;
- exact action performed;
- visible error message;
- current fixture/team perspective;
- session UUID or Staff Brief snapshot UUID when applicable;
- screenshot;
- relevant JSON/technical block if shown.

Do not work around a failed integrity guard during this test. A guard failure is itself a test result to diagnose.
