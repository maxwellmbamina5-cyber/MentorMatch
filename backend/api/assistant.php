<?php
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function assistantJsonResponse($status, $payload) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    assistantJsonResponse(405, ['error' => 'Use POST to send a message.']);
}

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['user_role'] ?? '';
if (!$userId || !in_array($role, ['mentor', 'mentee'], true)) {
    assistantJsonResponse(401, ['error' => 'Sign in as a mentor or mentee to use the assistant.']);
}

$account = $pdo->prepare("SELECT role, status FROM users WHERE id = ?");
$account->execute([$userId]);
$account = $account->fetch();
if (!$account || $account['role'] !== $role) {
    assistantJsonResponse(403, ['error' => 'Your account cannot use this assistant.']);
}
if (!hasActiveSubscription($userId)) {
    assistantJsonResponse(403, ['error' => 'An active monthly subscription is required to use the AI assistant.']);
}

$request = json_decode(file_get_contents('php://input'), true);
if (!is_array($request) || !isset($request['messages']) || !is_array($request['messages'])) {
    assistantJsonResponse(400, ['error' => 'Send a valid chat message.']);
}

$messages = [];
foreach (array_slice($request['messages'], -10) as $message) {
    if (!is_array($message)
        || !isset($message['role'], $message['content'])
        || !in_array($message['role'], ['user', 'assistant'], true)
        || !is_string($message['content'])) {
        assistantJsonResponse(400, ['error' => 'The conversation contains an invalid message.']);
    }

    $content = trim($message['content']);
    if ($content === '' || strlen($content) > 4000) {
        assistantJsonResponse(400, ['error' => 'Each message must be between 1 and 4,000 characters.']);
    }
    $messages[] = ['role' => $message['role'], 'content' => $content];
}

if (empty($messages) || end($messages)['role'] !== 'user') {
    assistantJsonResponse(400, ['error' => 'Write a message before asking the assistant.']);
}

$model = getenv('OLLAMA_MODEL') ?: 'qwen2.5:3b';
$context = [];

try {
    if ($role === 'mentor') {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS completed_sessions,
                   (SELECT COUNT(*) FROM sessions WHERE mentor_id = ? AND status = 'pending') AS pending_requests
            FROM sessions
            WHERE mentor_id = ? AND status = 'completed'
        ");
        $stmt->execute([$userId, $userId]);
        $mentorStats = $stmt->fetch();

        $context = [
            'completed_sessions' => (int)$mentorStats['completed_sessions'],
            'pending_session_requests' => (int)$mentorStats['pending_requests'],
        ];
        $systemPrompt = "You are MentorMatch Coach, a practical, encouraging coaching assistant for peer mentors in a Zambian student mentorship platform. Help mentors improve session preparation, clear explanations, active listening, inclusive teaching, follow-up, and reliability. Offer specific, respectful actions and sample questions. Use the private aggregate dashboard context when relevant; it contains no mentee identities. Mentor ratings are private and must not be disclosed, summarized, or inferred. Never encourage pressuring students, sharing private student details, or making promises about academic grades. Be honest that you cannot change bookings or contact mentees. If asked how to manage bookings, direct the mentor to My Sessions and their pending requests. Reply in plain text, concise and warm.";
    } else {
        $stmt = $pdo->prepare("
            SELECT
                (SELECT COUNT(*) FROM sessions WHERE mentee_id = ? AND status = 'completed') AS completed_sessions,
                (SELECT COUNT(*) FROM sessions WHERE mentee_id = ? AND status = 'confirmed') AS confirmed_sessions,
                (SELECT COUNT(*) FROM sessions WHERE mentee_id = ? AND status = 'pending' AND payment_status = 'paid_to_admin') AS awaiting_mentor,
                (SELECT COUNT(*) FROM sessions WHERE mentee_id = ? AND payment_status = 'pending' AND status IN ('pending', 'confirmed')) AS awaiting_payment
        ");
        $stmt->execute([$userId, $userId, $userId, $userId]);
        $menteeStats = $stmt->fetch();

        $context = [
            'completed_sessions' => (int)$menteeStats['completed_sessions'],
            'confirmed_sessions' => (int)$menteeStats['confirmed_sessions'],
            'paid_sessions_awaiting_mentor_response' => (int)$menteeStats['awaiting_mentor'],
            'sessions_awaiting_payment' => (int)$menteeStats['awaiting_payment'],
        ];
        $systemPrompt = "You are MentorMatch Study Guide, a supportive assistant for mentees at a Zambian peer-mentoring platform. Help learners make a catch-up plan, choose what to ask a mentor, prepare for a session, and use the platform. To find help, direct them to Find a Mentor and search by course code or course name; they can open a mentor and request a session. Explain that booking requests and mentor confirmations are managed under My Sessions. Once a booking exists, Session Details shows the contact information available for that session. Do not claim that the system has direct in-app chat, that you sent a message, or that a mentor is available unless the dashboard context proves it. Be honest that you cannot book, pay, or contact mentors for them. Use the private aggregate dashboard context when useful; do not ask for passwords, payment credentials, or unnecessary personal information. Offer practical, encouraging, step-by-step study advice. Reply in plain text, concise and warm.";
    }
} catch (PDOException $exception) {
    error_log('MentorMatch assistant context query failed: ' . $exception->getMessage());
    assistantJsonResponse(500, ['error' => 'Could not load your dashboard context. Please try again.']);
}

$messages = array_merge(
    [['role' => 'system', 'content' => $systemPrompt . "\nCurrent dashboard context (JSON): " . json_encode($context)]],
    $messages
);

$payload = json_encode([
    'model' => $model,
    'messages' => $messages,
    'stream' => false,
    'options' => [
        'temperature' => 0.65,
        'num_ctx' => 4096,
        'num_predict' => 500,
    ],
]);

$curl = curl_init('http://127.0.0.1:11434/api/chat');
curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 120,
]);

$response = curl_exec($curl);
$curlError = curl_error($curl);
$httpStatus = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($response === false) {
    error_log('MentorMatch local Ollama request failed: ' . $curlError);
    assistantJsonResponse(503, ['error' => 'The local AI is not responding. Start Ollama and try again.']);
}

$result = json_decode($response, true);
if ($httpStatus === 404) {
    assistantJsonResponse(503, ['error' => 'The local AI model is not installed yet. Run: ollama pull ' . $model]);
}
if ($httpStatus < 200 || $httpStatus >= 300) {
    error_log('MentorMatch Ollama returned HTTP ' . $httpStatus . ': ' . substr($response, 0, 1000));
    assistantJsonResponse(503, ['error' => 'The local AI could not answer. Check that Ollama is running and try again.']);
}

$answer = trim($result['message']['content'] ?? '');
if ($answer === '') {
    error_log('MentorMatch Ollama returned an empty chat response.');
    assistantJsonResponse(502, ['error' => 'The local AI returned an empty response. Please try again.']);
}

assistantJsonResponse(200, ['reply' => $answer]);
