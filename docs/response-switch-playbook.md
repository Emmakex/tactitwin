# v1.31 — Response Switch Playbook

## Goal

v1.31 converts the state-specific leader changes detected by v1.30 into an operational pre-match review map for staff.

The question is no longer only:

> Which response is most robust across states?

It becomes:

> If the match reaches one of our prepared states, should we keep the baseline response or review a switch, what response should we review, and what live evidence must staff validate before acting?

## Inputs

The playbook composes existing outputs only:

- **v1.27 Tactical Response Matrix** — the unchanged baseline response order;
- **v1.28 Match-State Scenario Tree** — minute, score, manpower, recommendations, decision triggers and historical coverage for each prepared branch;
- **v1.30 Response Robustness** — branch-specific response order and compatibility indexes.

No new tactical model is introduced.

## Decision states

Each of the seven prepared branches receives one of two deterministic labels:

- `retain-baseline` — the v1.30 branch leader is the same response that leads the v1.27 baseline matrix;
- `switch-candidate` — the v1.30 branch leader differs from the v1.27 baseline response.

A `switch-candidate` is **not an activation command**. It is a prepared review point.

Every item exposes:

- branch identity and purpose;
- exact hypothetical minute / score / player-count state;
- baseline response;
- target response for review;
- second-ranked response;
- branch compatibility margin between first and second;
- up to three existing v1.28 decision triggers as validation signals;
- the first existing branch recommendation;
- historical coverage and an explicit coverage caution when relevant;
- `automatic_activation=false`;
- `manual_confirmation_required=true`.

## Compatibility margin

`state_fit_margin` is:

```text
leader state_fit - runner-up state_fit
```

It describes separation between the first two responses inside that prepared branch.

It is not:

- statistical confidence;
- probability the target response succeeds;
- probability the branch occurs;
- expected-goal gain;
- expected-points gain;
- causal effect.

A small margin should encourage staff to treat the branch as tactically ambiguous rather than forcing a switch.

## Live validation signals

v1.31 does not invent a new trigger model.

It reuses the branch's existing v1.28 `decision_triggers`, which themselves come from the score-state/manpower What-if layer. Up to three are attached to each playbook item so staff have explicit observations to verify before changing response.

If no strong live confirmation exists, the existence of a switch candidate alone is insufficient to change the tactical response.

## Coverage caution

A switch review is marked with `coverage_caution=true` when the branch has low general historical coverage or low manpower-state coverage.

The UI then states that the item should be treated as a rehearsal candidate and validated live before increasing its tactical weight.

Coverage caution never blocks staff action and never promotes a different response automatically.

## Summary diagnostics

The playbook exposes:

- total prepared branches;
- number of switch candidates;
- number of branches retaining the baseline response;
- number of switch candidates with coverage caution;
- earliest prepared switch candidate by match minute;
- frequency of target responses across switch candidates.

These are descriptive workflow diagnostics, not performance scores.

## Twin Lab presentation

v1.31 extends `TactiTwin > Twin Lab` with:

- a switch-candidate summary card;
- coverage-caution count;
- a **Response Switch Playbook** table containing branch, prepared decision, target response, compatibility margin, state trigger, validation signal and coverage caution;
- an explicit manual-confirmation notice;
- full technical JSON for audit.

The v1.27 baseline matrix, v1.30 robustness table and v1.28 branch outputs remain visible and unchanged.

## Guardrails

v1.31 must never:

- activate a tactical response automatically;
- alter the v1.27 baseline response ranking;
- alter v1.30 branch rankings;
- alter v1.28 risk, opportunity, recommendations or triggers;
- activate or rewrite Plan A/B/C;
- turn a state match into certainty that a switch is correct;
- use the compatibility margin as confidence or outcome probability;
- hide low historical coverage;
- create a new persistent tactical state;
- claim causal coaching effectiveness.

## Exit criterion

For one valid Twin Lab result, v1.31 must generate one review item for every v1.28 branch. Each item must preserve the v1.27 baseline response, derive its target only from the v1.30 branch leader, reuse existing v1.28 validation signals and expose historical coverage. Switch candidates must require manual confirmation and never auto-activate. ES/EN decision logic must remain invariant, all upstream inputs must remain immutable, and dedicated regression tests must prove representative switch targets, coverage caution and guardrails.
