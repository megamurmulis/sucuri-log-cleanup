# sucuri-log-cleanup

This is a helper plugin for the Sucuri plugin:
https://wordpress.org/plugins/sucuri-scanner/

Periodically cleanup Sucuri log files:
- `sucuri-auditqueue.php`
- `sucuri-oldfailedlogins.php`
to avoid `Allowed memory size exhausted` error when log files are too big.<br>

With time/bots bruteforcing backend login/etc these log files can grow very big,
and since Sucuri loads these files fully in memory - can trigger Fatal Error (white screen if `display_errors=off`), preventing backend access.

Cron period is hardcoded to 7 days: `const MAX_DAYS = 7;`<br>
Cron check is done via `admin_init` hook (back-end access).<br>
`const SCHEMA = 2;` - increment value to force cleanup on plugin update without waiting for interval to elapse.<br>
