<?php
// cron/expire_sessions.php
// Cancel sessions that are too old and still pending

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Cancel pending sessions that are more than 7 days old
$stmt = $pdo->query("
    UPDATE sessions 
    SET status = 'cancelled' 
    WHERE status = 'pending' 
      AND created_at < NOW() - INTERVAL '7 days'
");

$count = $stmt->rowCount();

logActivity(0, "Auto-cancelled $count expired pending sessions");

echo "Cancelled $count expired sessions\n";
?>