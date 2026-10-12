# v1.30 — Response Robustness Across Match States

## Goal

v1.30 answers a narrower and more useful coaching question than “which response has the highest baseline fit?”:

> Which tactical response remains competitive across several explicitly prepared match states, and in which states should staff expect the preferred response to change?

It builds directly on:

- v1.27 Tactical Response Matrix — four explicit response packages over the same five matchup scenarios;
- v1.28 Match-State Scenario Tree — seven explicit minute/score/manpower preparation branches;
- v1.29 Tactical Twin Lab — one WordPress workspace and one shared Full Matchup Scan.

## What v1.30 does

For each of the seven v1.28 branches, v1.30 defines a transparent tactical-emphasis profile across the same five scenario dimensions used by v1.27:

1. build-up vs high press;
2. opponent side bias;
3. defensive transition after loss;
4. attack vs low block;
5. exploit opponent weak side.

Every profile sums to `1.0`. These values are authored preparation assumptions. They are not probabilities, learned model parameters or estimates of causal effectiveness.

### Branch emphasis profiles

| Branch | High press | Side bias | Transition | Low block | Weak side |
| --- | ---: | ---: | ---: | ---: | ---: |
| Opening neutral | 0.25 | 0.15 | 0.25 | 0.15 | 0.20 |
| Second-half neutral | 0.20 | 0.15 | 0.25 | 0.15 | 0.25 |
| Protect lead | 0.15 | 0.15 | 0.40 | 0.10 | 0.20 |
| Chase game | 0.15 | 0.10 | 0.15 | 0.25 | 0.35 |
| Late draw | 0.20 | 0.15 | 0.25 | 0.15 | 0.25 |
| Numerical superiority | 0.10 | 0.10 | 0.15 | 0.25 | 0.40 |
| Numerical inferiority | 0.20 | 0.20 | 0.40 | 0.05 | 0.15 |

The profiles express preparation intent only. For example, protecting a lead assigns more attention to defensive transition, while numerical superiority assigns more attention to low-block manipulation and weak-side access.

## Branch compatibility index

For one response package inside one branch, v1.30 calculates a state-specific compatibility index:

```text
100 * sum(
    scenario_priority * min(response_weight, state_demand_weight)
) / sum(
    scenario_priority * state_demand_weight
)
```

Where:

- `scenario_priority` is the unchanged v1.27 / Match Plan priority: `0.60*risk + 0.40*opportunity`;
- `response_weight` is the unchanged emphasis of the v1.27 response package;
- `state_demand_weight` is the documented branch emphasis above.

The use of `min(response_weight, state_demand_weight)` means a response receives credit only for the share of branch demand it actually covers. Over-weighting one dimension cannot compensate for leaving another demanded dimension uncovered.

The resulting `state_fit` is a branch compatibility index. It is **not**:

- win probability;
- expected scoreline;
- probability that the branch occurs;
- expected points;
- causal treatment effect;
- proof that a tactical response will work.

## Robustness is rank stability, not another score

v1.30 deliberately does not create an aggregate 0–100 robustness score.

For each response, the system records:

- number of branches ranked first;
- number of branches ranked in the Top 2;
- mean rank across all seven branches;
- best rank;
- worst rank;
- rank range;
- exact rank in every branch.

Responses are ordered for the robustness table by:

1. lower mean rank;
2. more Top-2 appearances;
3. more first-place appearances;
4. response ID as deterministic tie-breaker.

Descriptive labels are then assigned:

- **Stable leader** — first in at least four branches and never below second;
- **Stable competitor** — Top 2 in at least five branches and never below third;
- **State-sensitive** — does not satisfy the previous stability conditions.

These labels summarize ranking stability only.

## Leader changes

The original v1.27 top response remains the baseline top response and is never overwritten.

For every v1.28 branch, v1.30 records:

- branch-specific response order;
- state-specific leader;
- whether that leader differs from the v1.27 baseline leader.

This gives staff an explicit switch map such as:

```text
Baseline: Press-Resistant Build-up
Protect lead: Transition Security
Chase game: Width & Switch
10v11: Transition Security
```

The exact output depends on the matchup evidence.

## Twin Lab presentation

v1.30 extends `TactiTwin > Twin Lab` with:

- most robust response;
- number of state branches where the leader changes;
- response robustness table;
- first-place / Top-2 counts;
- mean and worst rank;
- leader-by-state table;
- state compatibility index for the leader of each branch;
- full technical evidence with every component and branch emphasis profile.

The existing v1.27 baseline response table and v1.28 scenario tree remain visible and unchanged.

## Guardrails

v1.30 must never:

- mutate the Full Matchup Scan;
- alter v1.27 baseline fit or ranking;
- alter v1.28 branch risk/opportunity/recommendations;
- assign branch occurrence probabilities;
- predict scoreline or result;
- create a hidden aggregate robustness score;
- activate Plan A/B/C;
- persist a new tactical state;
- present authored branch emphasis as learned truth.

## Exit criterion

For one valid Twin Lab result, all four v1.27 response packages must be evaluated against all seven v1.28 branches using documented branch emphasis profiles that sum to 1.0. The UI must expose per-branch leaders, leader changes from baseline and rank-stability diagnostics for every response. The original v1.27 ranking and v1.28 outputs must remain unchanged, ES/EN mathematics must be invariant, and regression tests must verify methodology, boundaries and deterministic behavior.
