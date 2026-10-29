<?php
// api/v1/auth.php - API Authentication
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/database.php';

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Parse input
$input = json_decode(file_get_contents('php://input'), true);

if ($method === 'POST') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'login') {
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Email and password required']);
            exit();
        }
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                echo json_encode(['success' => false, 'message' => 'Account not active']);
                exit();
            }
            
            // Generate API token
            $token = bin2hex(random_bytes(32));
            
            // Store token in session or database
            $stmt = $pdo->prepare("UPDATE users SET api_token = ? WHERE id = ?");
            $stmt->execute([$token, $user['id']]);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'token' => $token
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        }
    } elseif ($action === 'register') {
        // Registration logic
        // Similar to register.php but returns JSON
    }
}
?>