# v1.29 — Tactical Twin Lab

## Goal

v1.29 turns the two tactical-twin building blocks introduced in v1.27 and v1.28 into one coach-facing WordPress workspace.

The Lab answers two preparation questions side by side:

1. **Which tactical response package places the most emphasis on the current matchup priorities?**
2. **How does the same matchup look across explicit fictional minute / score / manpower states?**

The Lab is an orchestration and presentation layer. It does not add another tactical model.

## One shared source scan

`TactiTwin_Tactical_Twin_Lab_Service` resolves the two team profiles once and builds one `TactiTwin_Matchup_Scan`.

That exact scan is then passed to:

- `TactiTwin_Tactical_Response_Matrix` from v1.27;
- `TactiTwin_Match_State_Scenario_Tree` from v1.28.

This prevents the two surfaces from silently describing different source scans inside the same Lab result.

## Planning-context inheritance

The WordPress `Twin Lab` submenu reads the existing per-user `TactiTwin_Planning_Context`.

When an active imported match + team perspective exists:

- the Lab uses its canonical `team_source_id` and `opponent_source_id`;
- manual team selectors are hidden;
- the active context remains the source of truth;
- staff can return to Match Workspace to change the active match.

When no active planning context exists, Twin Lab preserves a manual team/opponent fallback.

The Lab does not create a second planning-context store.

## WordPress surface

The generated Lab shows:

### Preparation summary

- top-fit v1.27 tactical response;
- highest-risk v1.28 match-state branch;
- highest-opportunity branch;
- source-match coverage;
- count of low-coverage branches.

These values are references to existing v1.27/v1.28 outputs, not a new combined score.

### Tactical response comparison

All four v1.27 response packages are shown with:

- rank;
- name and description;
- fit value;
- dominant matchup driver;
- number of explicit trade-offs.

The response ranking is unchanged from v1.27.

### Match-state scenario tree

All seven v1.28 branches are shown with:

- branch name and purpose;
- exact minute / score / player-count hypothesis;
- historical risk;
- historical opportunity;
- descriptive stress orientation;
- first existing What-if instruction.

Branch outputs are unchanged from v1.28.

### Technical evidence

The complete Lab payload remains available in an expandable technical-evidence section, preserving the full response matrix and scenario tree for audit.

## Print workflow

The page provides the existing browser print flow so the unified preparation view can be printed or saved as PDF without introducing a server-side PDF dependency.

## Method boundaries

v1.29 does not:

- create a new tactical score;
- combine response fit and branch risk into a hidden score;
- re-rank v1.27 response packages;
- alter v1.28 branch calculations;
- assign probabilities to match-state branches;
- predict scoreline, win/draw/loss or expected goals;
- claim causal tactical effectiveness;
- activate Plan A, B or C;
- create a new database schema or persistence model;
- replace Match Workspace as the canonical fixture selector.

The Lab is a preparation workspace over already-defined, explainable components.

## Regression guarantees

`tests/test-tactical-twin-lab.php` verifies that:

- the Lab exposes all four v1.27 response packages;
- the Lab exposes all seven v1.28 branches;
- response and branch summaries exactly reference their source outputs;
- response matrix and scenario tree share the same team/opponent identity;
- the source Full Matchup Scan is not mutated;
- response ranking and branch numeric outputs remain ES/EN invariant;
- the method declares no new score, output prediction or plan activation;
- invalid source scans are rejected.

## Product direction

v1.29 is the first coach-facing surface that feels like a tactical twin rather than a collection of separate analysis tools: one active match, multiple tactical responses and multiple explicit match futures on the same evidence base.

The next useful step is not to add probabilities by assumption. It is to measure **response robustness across branches** with transparent rules, keeping the baseline response fit and branch evidence separate and auditable.
