# TactiTwin Test Candidate 0.1.4

Runtime checkpoint: WordPress admin submenu registration order.

A real WordPress test under `/extensiones/` reached the canonical Scenario Lab URL but WordPress returned "You do not have permission to access this page". The cause was the parent TactiTwin menu and child module submenus being registered at the same default `admin_menu` priority while child hooks were attached first.

## Fix

- register the TactiTwin parent menu at priority `5`;
- keep module submenus at the normal priority `10`;
- ensure WordPress knows the parent slug before generating child page hooks;
- add regression coverage for the parent hook priority.

## Retest

1. Keep the existing Bayer Leverkusen vs Augsburg planning context and imported data.
2. Open Match Workspace.
3. Open Scenario Lab and Match Plan from the workflow.
4. Both pages must render inside wp-admin for an administrator.
5. Continue with Match Plan using a 5-match pre-match sample.
