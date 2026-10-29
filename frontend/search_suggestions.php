<?php
// search_suggestions.php - AI Search Suggestions API
require_once __DIR__ . '/../backend/includes/functions.php';

header('Content-Type: application/json');
if (($_SESSION['user_role'] ?? '') !== 'mentee' || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Sign in as a mentee to search for mentors.']);
    exit();
}
if (!hasActiveSubscription($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'An active subscription is required to search for mentors.']);
    exit();
}

if (!isset($_GET['q']) || empty($_GET['q'])) {
    echo json_encode(['suggestions' => []]);
    exit();
}

$query = sanitize($_GET['q']);
$suggestions = [];

// Get active semester
$stmt = $pdo->query("SELECT * FROM semesters WHERE status = 'active' ORDER BY id DESC LIMIT 1");
$active_semester = $stmt->fetch();

if (!$active_semester) {
    echo json_encode(['suggestions' => []]);
    exit();
}

// Only suggest courses that have an active, approved mentor with a rate set.
$stmt = $pdo->prepare("
    SELECT DISTINCT ma.course_code,
           COALESCE(NULLIF(c.course_name, ''), NULLIF(ma.course_name, ''), ma.course_code) AS course_name
    FROM mentor_applications ma
    JOIN users u ON u.id = ma.mentor_id AND u.role = 'mentor' AND u.status = 'active'
    JOIN mentor_rates r ON r.mentor_id = ma.mentor_id AND r.course_code = ma.course_code
    LEFT JOIN courses c ON c.course_code = ma.course_code
    WHERE ma.status = 'approved'
      AND ma.semester_id = ?
      AND EXISTS (
          SELECT 1 FROM subscriptions sub
          WHERE sub.user_id = u.id AND sub.status = 'active' AND sub.expires_at > NOW()
      )
      AND (ma.course_code LIKE ? OR ma.course_name LIKE ? OR c.course_name LIKE ?)
    ORDER BY ma.course_code
    LIMIT 5
");
$stmt->execute([$active_semester['id'], "%$query%", "%$query%", "%$query%"]);
$courses = $stmt->fetchAll();

foreach ($courses as $course) {
    $suggestions[] = [
        'type' => 'course',
        'text' => $course['course_code'] . ' - ' . $course['course_name'],
        'value' => $course['course_code']
    ];
}

// Get mentors
$stmt = $pdo->prepare("
    SELECT DISTINCT u.name
    FROM users u
    JOIN mentor_applications ma ON u.id = ma.mentor_id
    JOIN mentor_rates r ON r.mentor_id = u.id AND r.course_code = ma.course_code
    WHERE u.role = 'mentor' AND u.status = 'active'
      AND ma.status = 'approved' AND ma.semester_id = ?
      AND EXISTS (
          SELECT 1 FROM subscriptions sub
          WHERE sub.user_id = u.id AND sub.status = 'active' AND sub.expires_at > NOW()
      )
      AND u.name LIKE ?
    LIMIT 3
");
$stmt->execute([$active_semester['id'], "%$query%"]);
$mentors = $stmt->fetchAll();

foreach ($mentors as $mentor) {
    $suggestions[] = [
        'type' => 'mentor',
        'text' => $mentor['name'] . ' (Mentor)',
        'value' => $mentor['name']
    ];
}

// Subject suggestions
$subjects = [
    'programming' => ['coding', 'python', 'java', 'web development', 'software'],
    'mathematics' => ['math', 'calculus', 'algebra', 'statistics'],
    'science' => ['physics', 'chemistry', 'biology', 'lab'],
    'business' => ['accounting', 'finance', 'marketing', 'economics'],
    'english' => ['writing', 'literature', 'grammar', 'essay']
];

foreach ($subjects as $subject => $keywords) {
    foreach ($keywords as $keyword) {
        if (stripos($query, $keyword) !== false) {
            $suggestions[] = [
                'type' => 'topic',
                'text' => ucfirst($subject) . ' Mentors',
                'value' => $subject
            ];
            break 2;
        }
    }
}

// Limit to 8 suggestions
$suggestions = array_slice($suggestions, 0, 8);

echo json_encode(['suggestions' => $suggestions]);
?>