# Initial Tactical Scenarios

TactiTwin should begin with a small set of explainable scenarios that can be evaluated from public/open evidence plus optional coach inputs.

## Scenario catalogue v0.1

1. **Build-up vs high press**
   - Detect opponent pressure tendencies and our build-up vulnerability.
   - Evaluate safer progression routes and risk concentration by side/zone.

2. **Attack vs low block**
   - Compare central vs wide progression, final-third entries and shot creation.
   - Suggest width, overload or second-line occupation adjustments.

3. **Defensive transition after loss**
   - Measure where possession is lost and what follows immediately.
   - Flag zones with elevated transition exposure.

4. **Opponent side bias**
   - Detect whether the rival creates/progresses disproportionately through one side.
   - Recommend protection, pressing orientation or trapping options.

5. **Exploit opponent weak side**
   - Identify zones where the rival concedes progression, entries or shots.
   - Match those zones against our strongest routes.

6. **Protect a lead**
   - Re-score risk tolerance for a winning state.
   - Prefer control, rest-defense and lower-variance choices.

7. **Chase the game**
   - Re-score attacking risk for a losing state.
   - Suggest changes that increase territory, entries and chance volume.

8. **Formation switch**
   - Compare baseline tactical profile against a configured alternative formation.
   - Initial version uses rules/weights rather than agent simulation.

9. **Key-player absence/substitution**
   - Recalculate profile after removing or replacing a player contribution.
   - Surface dependencies and role gaps.

10. **Set-piece preparation**
    - Start with descriptive event evidence where source coverage permits.
    - Separate offensive and defensive set-piece recommendations.

## Scenario result contract

Each result should return:

- scenario ID and assumptions
- evidence coverage
- risk score
- opportunity score
- affected zones
- affected players/roles when known
- recommended response
- evidence summary
- confidence indicator
- alternative response(s)

## Principle

The engine must explain *why* a recommendation exists. A scenario with insufficient evidence should explicitly return low coverage rather than invent certainty.
