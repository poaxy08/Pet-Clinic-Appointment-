<?php
// Optional: run from cron / Task Scheduler so reminders go out even when nobody is on the site.
//   */15 * * * *  php /path/to/petcare/cron/send_reminders.php
if (PHP_SAPI !== 'cli') exit('CLI only');
require __DIR__ . '/../app/bootstrap.php';
$n = run_reminders(true);
echo date('c') . " — $n reminder(s) created\n";
