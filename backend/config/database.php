<?php
// config/database.php
// Database configuration for MentorMatch

// ============================================
// DATABASE CONNECTION SETTINGS
// ============================================

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'mentormatch';
$username = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';
$base_path = getenv('APP_BASE_PATH') ?: '/mentormatch/';
$app_mode = getenv('APP_MODE') ?: 'development';
$debug_mode = getenv('DEBUG_MODE');

define('APP_MODE', $app_mode);
define(
    'DEBUG_MODE',
    $debug_mode === false
        ? APP_MODE !== 'production'
        : filter_var($debug_mode, FILTER_VALIDATE_BOOLEAN)
);

$database_url = getenv('DATABASE_URL');
$sslmode = getenv('DB_SSLMODE') ?: 'prefer';

if ($database_url !== false && $database_url !== '') {
    $database_parts = parse_url($database_url);

    if (
        $database_parts === false
        || !in_array($database_parts['scheme'] ?? '', ['postgres', 'postgresql'], true)
        || empty($database_parts['host'])
        || empty($database_parts['path'])
        || !isset($database_parts['user'], $database_parts['pass'])
    ) {
        error_log('DATABASE_URL must be a valid PostgreSQL connection URL.');
        http_response_code(500);
        die(DEBUG_MODE ? 'DATABASE_URL must be a valid PostgreSQL connection URL.' : 'Database configuration error.');
    }

    parse_str($database_parts['query'] ?? '', $database_options);
    $host = $database_parts['host'];
    $port = (string)($database_parts['port'] ?? 5432);
    $dbname = rawurldecode(ltrim($database_parts['path'], '/'));
    $username = rawurldecode($database_parts['user']);
    $password = rawurldecode($database_parts['pass']);
    $sslmode = getenv('DB_SSLMODE') ?: ($database_options['sslmode'] ?? 'prefer');
}

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=$sslmode";

// ============================================
// ESTABLISH DATABASE CONNECTION
// ============================================

try {
    $pdo = new PDO(
        $dsn,
        $username,
        $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch(PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    die(DEBUG_MODE ? 'Database connection failed: ' . $e->getMessage() : 'Database connection failed.');
}

// ============================================
// BASE URL CONFIGURATION
// ============================================

$forwarded_protocol = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')[0]));
$is_https = (
    (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || $forwarded_protocol === 'https'
);
$protocol = $is_https ? 'https://' : 'http://';
$host_name = $_SERVER['HTTP_HOST'] ?? 'localhost';
$app_url = getenv('APP_URL');
$base_path = '/' . trim($base_path, '/');
$url_path = $base_path === '/' ? '/' : $base_path . '/';
// Define base URL for the application
define(
    'BASE_URL',
    $app_url !== false && $app_url !== ''
        ? rtrim($app_url, '/') . '/'
        : $protocol . $host_name . $url_path
);

// Define site name
define('SITE_NAME', 'MentorMatch');

// ============================================
// PAYMENT SETTINGS
// ============================================

// Admin commission percentage (5%)
define('ADMIN_COMMISSION', 0.05);

// Admin Mobile Money phone number
define('MOMO_PHONE', '+260963229842');

// Payment gateway settings
define('PAYMENT_GATEWAY', 'zynlepay'); // Options: 'zynlepay', 'lipila', 'manual'

// ============================================
// SESSION SETTINGS
// ============================================

// Minimum hours a mentor needs to complete for certificate
define('MIN_MENTOR_HOURS', 10);

// Default session duration in minutes
define('DEFAULT_SESSION_DURATION', 60);

// Maximum session duration in minutes
define('MAX_SESSION_DURATION', 180);

// ============================================
// DATE & TIME SETTINGS
// ============================================

// Set default timezone
date_default_timezone_set('Africa/Lusaka');

// Date format for display
define('DATE_FORMAT', 'd M Y');
define('DATETIME_FORMAT', 'd M Y h:i A');
define('TIME_FORMAT', 'h:i A');

// ============================================
// EMAIL SETTINGS
// ============================================

// Email configuration (for PHPMailer if used)
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587));
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: 'your_email@gmail.com');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: 'your_app_password');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'noreply@mentormatch.com');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'MentorMatch');

// ============================================
// FILE UPLOAD SETTINGS
// ============================================

// Maximum file size for uploads (5MB)
define('MAX_FILE_SIZE', 5 * 1024 * 1024);

// Allowed file extensions
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']);

// Upload directories
define('UPLOAD_DIR', __DIR__ . '/../../uploads/');
define('CERTIFICATE_DIR', UPLOAD_DIR . 'certificates/');
define('PROFILE_DIR', UPLOAD_DIR . 'profiles/');

// ============================================
// SECURITY SETTINGS
// ============================================

// Password hashing cost
define('PASSWORD_COST', 12);

// Session timeout (30 minutes)
define('SESSION_TIMEOUT', 1800);

// Maximum login attempts before lockout
define('MAX_LOGIN_ATTEMPTS', 5);

// Lockout duration in minutes
define('LOCKOUT_DURATION', 15);

// ============================================
// APPLICATION SETTINGS
// ============================================

// Application mode: 'development' or 'production'
// Enable/disable email sending
define('SEND_EMAILS', filter_var(getenv('SEND_EMAILS') ?: 'true', FILTER_VALIDATE_BOOLEAN));

// Enable/disable logging
define('ENABLE_LOGGING', true);

// ============================================
// CREATE UPLOAD DIRECTORIES IF NOT EXISTS
// ============================================

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

if (!is_dir(CERTIFICATE_DIR)) {
    mkdir(CERTIFICATE_DIR, 0777, true);
}

if (!is_dir(PROFILE_DIR)) {
    mkdir(PROFILE_DIR, 0777, true);
}

// ============================================
// HELPER FUNCTIONS (Only if not already defined)
// ============================================

// Get connection status
if (!function_exists('getDbStatus')) {
    function getDbStatus() {
        global $pdo;
        try {
            $pdo->query('SELECT 1');
            return ['status' => 'connected', 'message' => 'Database connection successful'];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}

// Generate random string
if (!function_exists('generateRandomString')) {
    function generateRandomString($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
}

// Check if user is logged in
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

// Get current user ID
if (!function_exists('getCurrentUserId')) {
    function getCurrentUserId() {
        return $_SESSION['user_id'] ?? null;
    }
}

// Get current user role
if (!function_exists('getCurrentUserRole')) {
    function getCurrentUserRole() {
        return $_SESSION['user_role'] ?? null;
    }
}

// Get current user name
if (!function_exists('getCurrentUserName')) {
    function getCurrentUserName() {
        return $_SESSION['user_name'] ?? 'Guest';
    }
}

// ============================================
// START SESSION IF NOT STARTED
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// ERROR REPORTING (Development Only)
// ============================================

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ============================================
// CORS HEADERS (For API)
// ============================================

// Uncomment if you need CORS support for API
// header('Access-Control-Allow-Origin: *');
// header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
// header('Access-Control-Allow-Headers: Content-Type, Authorization');

// ============================================
// COMPRESSION (Gzip)
// ============================================

if (!ob_get_level()) {
    ob_start('ob_gzhandler');
}

// ============================================
// END OF CONFIGURATION
// ============================================
?>