# TactiTwin Test Candidate 0.1.3

Runtime hotfix for WordPress installations hosted in a subdirectory where a TactiTwin admin page can be requested as a legacy pretty path such as `/extensiones/wp-admin/tactitwin-match-plan`.

## Expected behaviour

- Recognize only TactiTwin legacy admin paths under `/wp-admin/`.
- Redirect them to the canonical WordPress admin router: `/wp-admin/admin.php?page=tactitwin-...`.
- Preserve the WordPress installation subdirectory.
- Never redirect already-canonical `admin.php?page=...` routes.
- Ignore unrelated WordPress admin paths.

## Retest point

From Match Workspace open Match Plan again. The page must remain inside the WordPress admin and show the Match Plan form/result instead of the site 404 template.
