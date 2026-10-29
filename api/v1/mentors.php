<?php
// api/v1/mentors.php - Get mentors by course
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../includes/functions.php';

// Accept either an authenticated mentee session or a bearer API token.
$headers = getallheaders();
$authorization = $headers['Authorization'] ?? $headers['authorization'] ?? '';
$token = preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) ? trim($matches[1]) : '';
$mentee_id = null;
if (($_SESSION['user_role'] ?? '') === 'mentee') {
    $mentee_id = (int)$_SESSION['user_id'];
} elseif ($token !== '') {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE api_token = ? AND role = 'mentee' AND status = 'active'");
    $stmt->execute([$token]);
    $mentee_id = (int)($stmt->fetchColumn() ?: 0);
}
if ($mentee_id) {
    $account = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'mentee' AND status = 'active'");
    $account->execute([$mentee_id]);
    $mentee_id = (int)($account->fetchColumn() ?: 0);
}
if (!$mentee_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sign in as a mentee to search for mentors.']);
    exit();
}
if (!hasActiveSubscription($mentee_id)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'An active subscription is required to search for mentors.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $course = $_GET['course'] ?? '';
    $semester_id = (int)($_GET['semester_id'] ?? 0);
    
    if (empty($course)) {
        echo json_encode(['success' => false, 'message' => 'Course code required']);
        exit();
    }
    
    // Get active semester if not specified
    if ($semester_id === 0) {
        $stmt = $pdo->query("SELECT id FROM semesters WHERE status = 'active' LIMIT 1");
        $semester = $stmt->fetch();
        $semester_id = $semester['id'] ?? 0;
    }
    
    // Search mentors
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.name, u.email, u.phone, u.programme, u.year_of_study,
               r.rate_per_hour,
               (SELECT AVG(rating) FROM feedback f WHERE f.mentor_id = u.id) as avg_rating,
               (SELECT COUNT(*) FROM sessions s WHERE s.mentor_id = u.id AND s.status = 'completed') as total_sessions
        FROM users u
        JOIN mentor_rates r ON u.id = r.mentor_id
        JOIN mentor_applications ma ON u.id = ma.mentor_id
        WHERE u.role = 'mentor' 
          AND u.status = 'active'
          AND ma.status = 'approved'
          AND ma.semester_id = ?
          AND EXISTS (
              SELECT 1 FROM subscriptions sub
              WHERE sub.user_id = u.id AND sub.status = 'active' AND sub.expires_at > NOW()
          )
          AND (r.course_code LIKE ? OR ma.course_code LIKE ?)
        ORDER BY avg_rating DESC
    ");
    $stmt->execute([$semester_id, "%$course%", "%$course%"]);
    $mentors = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $mentors
    ]);
}
?>