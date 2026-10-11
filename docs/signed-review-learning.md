# v1.26 — Signed Review Learning Integration

## Goal

v1.26 connects the persistent post-match staff sign-off introduced in v1.25 to TactiTwin's historical learning surfaces.

The key distinction is provenance, not effectiveness:

- an **unsigned historical case** can still be valid descriptive evidence;
- a **signed historical case** means staff closed and reviewed that match workflow and an integrity-valid post-match review snapshot exists;
- a signed review does **not** prove that the tactical intervention caused the observed result;
- signed status does not add points to a tactical score, suitability score, evidence-quality score, opponent-similarity score or historical-precedent retrieval score.

## Intervention Library learning scopes

`TactiTwin_Intervention_Library_Service::build()` now accepts a fifth argument, `learning_scope`:

- `all` — default; preserves the existing historical population;
- `signed` — includes only sessions with an integrity-valid v1.25 review sign-off.

The default remains `all` for backward compatibility.

When `signed` is selected, filtering occurs **before** context-matched groups and aggregate deltas are built. This matters because the resulting medians, maturity labels and sample counts must describe the signed subset itself rather than an all-case aggregate with unsigned rows merely hidden in the UI.

## Per-case provenance

After groups are built, each intervention case receives `learning_review` metadata:

- `status`: `signed`, `unsigned` or `invalid`;
- signed review UUID;
- signed revision number;
- sign-off timestamp;
- WordPress user ID that signed off;
- signed snapshot content SHA-256.

An integrity failure in a stored signed review is surfaced as `invalid`. It is never silently downgraded to `unsigned`, and it is excluded from the `signed` learning scope.

## Group-level coverage

Every intervention group receives descriptive `learning_review` coverage:

- `all-signed`;
- `mixed`;
- `unsigned`;
- `invalid`.

Coverage also reports signed/unsigned/invalid case counts, distinct signed sessions and latest signed revision observed.

This metadata does not create an additional confidence or effectiveness score.

## Historical Precedents

v1.26 propagates group signed-review provenance into v1.8 Historical Precedent Retrieval.

Each returned precedent now exposes its `learning_review` coverage plus a result-level `learning_review_coverage` summary.

The v1.8 ranking formula remains unchanged:

- 60% current scenario relevance;
- 20% historical sample maturity;
- 10% signal volume;
- 10% cleaner share.

Signed-review provenance contributes **0%** to `retrieval_score` and does not break ties or reorder default historical evidence.

## WordPress UI

Intervention Library now includes a bilingual learning-scope selector:

- All eligible historical cases / Todos los casos históricos elegibles
- Signed staff reviews only / Solo revisiones firmadas por el staff

The UI also shows:

- number of sessions with valid signed review provenance;
- active learning scope;
- per-group signed-review coverage;
- signed cases and signed sessions;
- latest signed revision where available.

## REST

`GET /tactitwin/v1/planning/intervention-library` accepts optional:

`learning_scope=all|signed`

Unknown values normalize to `all`.

## Method boundaries

v1.26 does not:

- change tactical scores;
- change scenario recommendations;
- change Plan A/B/C activation;
- change exact-context eligibility;
- change v1.8 retrieval-score weights;
- change evidence-quality calibration;
- change opponent-similarity scoring;
- treat staff sign-off as causal validation;
- treat staff sign-off as proof that an intervention was successful.

The feature answers a narrower operational question:

> Was this historical case only calculated, or was the match workflow actually reviewed and closed by staff?

That distinction can be used as an explicit filter without conflating workflow validation with tactical effectiveness.
