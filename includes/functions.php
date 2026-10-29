<?php
// includes/functions.php
// Fix the path - use absolute path from document root

// Get the base path
$base_path = dirname(__DIR__); // This goes up one level from includes folder

// Correct path to config file
require_once $base_path . '/config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Get current user data
function getCurrentUser() {
    global $pdo;
    if (!isLoggedIn()) return null;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// Check if user has specific role
function hasRole($role) {
    $user = getCurrentUser();
    return $user && $user['role'] === $role;
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit();
    }
}

// Redirect if not admin
function requireAdmin() {
    requireLogin();
    if (!hasRole('admin')) {
        header('Location: ../dashboard.php');
        exit();
    }
}

// Redirect if not mentor
function requireMentor() {
    requireLogin();
    $user = getCurrentUser();
    if (!$user || $user['role'] !== 'mentor') {
        header('Location: ../dashboard.php');
        exit();
    }
    if ($user['status'] !== 'active') {
        header('Location: ' . BASE_URL . 'subscription.php?account=pending');
        exit();
    }
}

// Redirect if not mentee
function requireMentee() {
    requireLogin();
    $user = getCurrentUser();
    if (!$user || $user['role'] !== 'mentee') {
        header('Location: ../dashboard.php');
        exit();
    }
    if ($user['status'] !== 'active') {
        header('Location: ' . BASE_URL . 'subscription.php?account=pending');
        exit();
    }
}

// Generate unique transaction reference
function generateTransactionRef() {
    return 'MTM' . date('Ymd') . strtoupper(uniqid());
}

// Calculate payment amounts
function calculatePayment($rate, $duration_minutes = 60) {
    $total = $rate * ($duration_minutes / 60);
    $admin_fee = $total * ADMIN_COMMISSION;
    $mentor_payout = $total - $admin_fee;
    
    return [
        'total' => round($total, 2),
        'admin_fee' => round($admin_fee, 2),
        'mentor_payout' => round($mentor_payout, 2)
    ];
}

// Format currency
function formatCurrency($amount) {
    return 'K' . number_format($amount, 2);
}

// Generate certificate hash
function generateCertificateHash($mentor_id, $semester_id) {
    return hash('sha256', $mentor_id . $semester_id . time() . rand(1000, 9999));
}

// Log system activity
function logActivity($user_id, $action, $details = null) {
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, action, ip_address, user_agent, details) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$user_id, $action, $ip, $user_agent, $details]);
}

// Send SMS notification (placeholder)
function sendSMS($phone, $message) {
    error_log("SMS to $phone: $message");
    return true;
}

// Send email notification
function sendEmail($to, $subject, $message) {
    error_log("Email to $to: $subject - $message");
    return true;
}

// Generate random password
function generateRandomPassword($length = 10) {
    return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, $length);
}

// Sanitize input
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Get semester status text
function getSemesterStatus($status) {
    $statuses = [
        'upcoming' => 'Upcoming',
        'active' => 'Active',
        'closed' => 'Closed',
        'archived' => 'Archived'
    ];
    return $statuses[$status] ?? $status;
}

// Get payment status text
function getPaymentStatus($status) {
    $statuses = [
        'pending' => 'Pending',
        'paid_to_admin' => 'Paid to Admin',
        'released_to_mentor' => 'Released to Mentor',
        'refunded' => 'Refunded'
    ];
    return $statuses[$status] ?? $status;
}

// Get session status text
function getSessionStatus($status) {
    $statuses = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show' => 'No Show'
    ];
    return $statuses[$status] ?? $status;
}

// Get mentor rate for a course
function getMentorRate($mentor_id, $course_code) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT rate_per_hour FROM mentor_rates WHERE mentor_id = ? AND course_code = ?");
    $stmt->execute([$mentor_id, $course_code]);
    $result = $stmt->fetch();
    return $result ? $result['rate_per_hour'] : 0;
}

// Check if mentor is available at a specific time
function isMentorAvailable($mentor_id, $datetime, $duration = 60) {
    global $pdo;
    $start_timestamp = strtotime($datetime);
    if (!$start_timestamp || $duration <= 0) {
        return false;
    }

    $end_timestamp = $start_timestamp + ($duration * 60);
    if (date('Y-m-d', $start_timestamp) !== date('Y-m-d', $end_timestamp)) {
        return false;
    }

    $day_of_week = (int)date('w', $start_timestamp);
    $start_time = date('H:i:s', $start_timestamp);
    $end_time = date('H:i:s', $end_timestamp);
    $end_datetime = date('Y-m-d H:i:s', $end_timestamp);

    $availability = $pdo->prepare("
        SELECT COUNT(*)
        FROM mentor_availability
        WHERE mentor_id = ?
          AND day_of_week = ?
          AND is_recurring = 1
          AND start_time <= ?
          AND end_time >= ?
    ");
    $availability->execute([$mentor_id, $day_of_week, $start_time, $end_time]);
    if (!$availability->fetchColumn()) {
        return false;
    }

    $conflicts = $pdo->prepare("
        SELECT COUNT(*)
        FROM sessions
        WHERE mentor_id = ?
          AND status IN ('pending', 'confirmed')
          AND session_date < ?
          AND session_date + duration_minutes * INTERVAL '1 minute' > ?
    ");
    $conflicts->execute([$mentor_id, $end_datetime, date('Y-m-d H:i:s', $start_timestamp)]);
    return $conflicts->fetchColumn() == 0;
}

// Get total mentoring hours for a mentor in a semester
function getMentorHours($mentor_id, $semester_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT SUM(duration_minutes) / 60.0 as total_hours 
                           FROM sessions 
                           WHERE mentor_id = ? 
                           AND semester_id = ? 
                           AND status = 'completed'
                           AND payment_status = 'released_to_mentor'");
    $stmt->execute([$mentor_id, $semester_id]);
    $result = $stmt->fetch();
    return $result['total_hours'] ?? 0;
}

// Get user by ID
function getUserById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getUserReference($user_id) {
    return 'MM-' . str_pad((string)$user_id, 8, '0', STR_PAD_LEFT);
}

function getSystemSetting($key, $default = '') {
    global $pdo;
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : $value;
}

function getSubscriptionPrice($role) {
    $prices = ['mentor' => 250.00, 'mentee' => 200.00];
    return $prices[$role] ?? 0.00;
}

function getActiveSubscription($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT s.id, s.amount, s.approved_at, s.expires_at
        FROM subscriptions s
        JOIN users u ON u.id = s.user_id AND u.status = 'active'
        WHERE s.user_id = ? AND s.status = 'active' AND s.expires_at > NOW()
        ORDER BY s.expires_at DESC
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

function hasActiveSubscription($user_id) {
    return (bool)getActiveSubscription($user_id);
}

function requireActiveSubscription($user_id = null) {
    $user_id = $user_id ?? ($_SESSION['user_id'] ?? null);
    if (!$user_id || !hasActiveSubscription($user_id)) {
        header('Location: ' . BASE_URL . 'subscription.php?required=1');
        exit();
    }
}

function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}
?>