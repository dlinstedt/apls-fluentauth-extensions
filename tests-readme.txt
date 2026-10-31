Development staging test matrix:
1. ACF inactive: no fatal error; ACF section disabled; marketing and CRM unchanged.
2. ACF selected: supported text, select, checkbox, date, time, date-time rendering.
3. Save group containing unsupported repeater: admin validation must reject selection.
4. Introduce unsupported type after save: registration submission disabled with admin notice.
5. Verify bad email token: no WordPress user and no user meta.
6. Verify correct code: user meta saved with field key references; pending JSON removed.
7. Simultaneous registration for same email: opaque transaction separation.
8. Non-ACF signup, social signup: no ACF data applied.
9. Change selected group during pending flow: reject stale schema.
10. WP-Cron: custom intervals registered, default near 00:05 local, deactivation unschedules.
11. Deactivate with keep-table setting: data retained; with drop-table: table dropped.
12. Uninstall: table cleanup and optional settings deletion.
13. WP Fusion and FluentCRM: field sync timing and marketing opt-in remain isolated.
