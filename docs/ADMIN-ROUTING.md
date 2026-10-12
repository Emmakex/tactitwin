# TactiTwin admin routing

TactiTwin admin screens must use WordPress's canonical admin router:

`/wp-admin/admin.php?page=tactitwin-...`

For installations hosted below a subdirectory, the installation prefix must be preserved, for example:

`/extensiones/wp-admin/admin.php?page=tactitwin-match-plan`

`TactiTwin_Admin_Route` also provides a narrow recovery guard for legacy/malformed paths shaped like:

`/wp-admin/tactitwin-...`

The guard only applies to logged-in administrators, only recognizes slugs beginning with `tactitwin`, does not match canonical `admin.php` URLs, and redirects back to the same site's canonical WordPress admin router.
