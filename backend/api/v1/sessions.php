<?php
// api/v1/sessions.php - Session API endpoints
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get user from token (simplified)
$headers = getallheaders();
$token = $headers['Authorization'] ?? '';
// Validate token and get user_id

if ($method === 'GET') {
    $user_id = 1; // Placeholder - should come from token validation
    $role = $_GET['role'] ?? '';
    $status = $_GET['status'] ?? '';
    
    $query = "SELECT s.*, 
                     m.name as mentor_name, m.email as mentor_email, m.phone as mentor_phone,
                     u.name as mentee_name, u.email as mentee_email, u.phone as mentee_phone
              FROM sessions s
              JOIN users m ON s.mentor_id = m.id
              JOIN users u ON s.mentee_id = u.id
              WHERE 1=1";
    $params = [];
    
    if ($role === 'mentor') {
        $query .= " AND s.mentor_id = ?";
        $params[] = $user_id;
    } elseif ($role === 'mentee') {
        $query .= " AND s.mentee_id = ?";
        $params[] = $user_id;
    }
    
    if (!empty($status)) {
        $query .= " AND s.status = ?";
        $params[] = $status;
    }
    
    $query .= " ORDER BY s.session_date ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $sessions = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $sessions]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $mentor_id = $input['mentor_id'] ?? 0;
    $mentee_id = $input['mentee_id'] ?? 0;
    $course_code = $input['course_code'] ?? '';
    $session_date = $input['session_date'] ?? '';
    $duration_minutes = $input['duration_minutes'] ?? 60;
    $semester_id = $input['semester_id'] ?? 0;

    if ((int)$semester_id <= 0) {
        $stmt = $pdo->query("SELECT id FROM semesters WHERE status = 'active' ORDER BY id DESC LIMIT 1");
        $semester_id = $stmt->fetchColumn();
    }

    if (!$semester_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No active semester found']);
        exit();
    }
    
    // Get mentor rate
    $rate = getMentorRate($mentor_id, $course_code);
    if ($rate <= 0) {
        echo json_encode(['success' => false, 'message' => 'Mentor rate not set for this course']);
        exit();
    }
    
    // Calculate payment
    $payment = calculatePayment($rate, $duration_minutes);
    
    // Create session
    $stmt = $pdo->prepare("INSERT INTO sessions 
                           (mentor_id, mentee_id, semester_id, course_code, session_date, 
                            duration_minutes, mentor_rate, total_amount, admin_fee, mentor_payout, 
                            status, payment_status)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')
                           RETURNING id");
    $success = $stmt->execute([
        $mentor_id, $mentee_id, $semester_id, $course_code, $session_date,
        $duration_minutes, $rate, $payment['total'], $payment['admin_fee'], $payment['mentor_payout']
    ]);
    
    if ($success) {
        $session_id = $stmt->fetchColumn();
        echo json_encode([
            'success' => true,
            'data' => ['session_id' => $session_id, 'payment' => $payment]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create session']);
    }
}
?>