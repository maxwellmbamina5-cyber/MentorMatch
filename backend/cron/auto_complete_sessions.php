<?php
// cron/auto_complete_sessions.php
// Run this script daily via cron job

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Auto-complete sessions that have passed
$stmt = $pdo->query("
    SELECT id FROM sessions 
    WHERE status IN ('confirmed', 'pending') 
      AND session_date < NOW() - INTERVAL '1 hour'
");

$sessions = $stmt->fetchAll();

foreach ($sessions as $session) {
    $stmt = $pdo->prepare("UPDATE sessions SET status = 'completed' WHERE id = ?");
    $stmt->execute([$session['id']]);
    
    logActivity(0, 'Auto-completed session #' . $session['id']);
}

echo "Auto-completed " . count($sessions) . " sessions\n";

// Auto-generate certificates for eligible mentors
$stmt = $pdo->query("
    SELECT DISTINCT u.id, u.name, u.email
    FROM users u
    JOIN sessions s ON u.id = s.mentor_id
    WHERE u.role = 'mentor' 
      AND u.status = 'active'
      AND NOT EXISTS (SELECT 1 FROM certificates c WHERE c.mentor_id = u.id AND c.semester_id = s.semester_id)
    GROUP BY u.id, s.semester_id
    HAVING SUM(s.duration_minutes) / 60.0 >= " . MIN_MENTOR_HOURS . "
");

$mentors = $stmt->fetchAll();

foreach ($mentors as $mentor) {
    // Get semester
    $stmt = $pdo->prepare("SELECT semester_id, SUM(duration_minutes) / 60.0 as total_hours, COUNT(*) as total_sessions
                           FROM sessions 
                           WHERE mentor_id = ? AND status = 'completed'
                           GROUP BY semester_id");
    $stmt->execute([$mentor['id']]);
    $data = $stmt->fetch();
    
    if ($data) {
        $hash = generateCertificateHash($mentor['id'], $data['semester_id']);
        
        $stmt = $pdo->prepare("INSERT INTO certificates 
                               (mentor_id, semester_id, total_hours, total_sessions, issue_date, certificate_hash, status)
                               VALUES (?, ?, ?, ?, CURRENT_DATE, ?, 'issued')
                               RETURNING id");
        $stmt->execute([$mentor['id'], $data['semester_id'], $data['total_hours'], $data['total_sessions'], $hash]);
        
        // Send email notification
        require_once dirname(__DIR__) . '/includes/email.php';
        $cert_id = $stmt->fetchColumn();
        sendCertificateEmail($mentor['name'], $mentor['email'], $cert_id);
        
        logActivity(0, 'Auto-generated certificate for mentor #' . $mentor['id']);
    }
}

echo "Auto-generated " . count($mentors) . " certificates\n";
?>