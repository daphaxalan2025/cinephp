<?php
// cron/archive_screenings.php
// Run daily: 0 0 * * * php /path/to/cinema/cron/archive_screenings.php

require_once dirname(__DIR__) . '/includes/functions.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting auto-archive...\n";

$result = autoArchiveExpiredScreenings();

echo "Archived " . $result['screenings'] . " expired screenings\n";
echo "Archived " . $result['online'] . " expired online schedules\n";
echo "[" . date('Y-m-d H:i:s') . "] Done!\n";
?>