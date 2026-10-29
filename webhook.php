<?php
// webhook.php - Handle payment callbacks from ZynlePay
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/zynlepay.php';

// Get the raw POST data
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

// Log the webhook data for debugging
error_log('Webhook received: ' . $payload);

if ($data) {
    // Extract data
    $reference = $data['reference'] ?? '';
    $status = $data['status'] ?? '';
    $transaction_id = $data['transactionId'] ?? '';
    $amount = $data['amount'] ?? 0;
    $phone = $data['phoneNumber'] ?? '';
    
    if (empty($reference)) {
        http_response_code(400);
        exit('Missing reference');
    }
    
    // Find the session with this reference
    $stmt = $pdo->prepare("SELECT id, mentee_id, mentor_id, total_amount, admin_fee, mentor_payout FROM sessions WHERE transaction_id = ?");
    $stmt->execute([$reference]);
    $session = $stmt->fetch();
    
    if (!$session) {
        http_response_code(404);
        exit('Session not found');
    }
    
    // Update based on status
    if ($status === 'completed' || $status === 'successful') {
        // Update session payment status
        $stmt = $pdo->prepare("UPDATE sessions SET 
                               payment_status = 'paid_to_admin', 
                               payment_date = NOW() 
                               WHERE id = ?");
        $stmt->execute([$session['id']]);
        
        // Insert into payments table
        $stmt = $pdo->prepare("INSERT INTO payments 
                               (session_id, mentee_id, mentor_id, amount, admin_fee, mentor_payout, 
                                payment_status, transaction_reference, payment_method, payment_date)
                               VALUES (?, ?, ?, ?, ?, ?, 'captured', ?, 'mobile_money', NOW())
                               ON CONFLICT (transaction_reference) DO UPDATE SET
                                   payment_status = EXCLUDED.payment_status,
                                   payment_date = EXCLUDED.payment_date");
        $stmt->execute([
            $session['id'],
            $session['mentee_id'],
            $session['mentor_id'],
            $session['total_amount'],
            $session['admin_fee'],
            $session['mentor_payout'],
            $reference
        ]);
        
        // Log the payment
        logActivity($session['mentee_id'], 'Payment completed for session #' . $session['id']);
        
        // Send notifications
        require_once 'includes/email.php';
        
        // Notify mentee
        $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
        $stmt->execute([$session['mentee_id']]);
        $mentee = $stmt->fetch();
        
        sendPaymentReceiptEmail($mentee['name'], $mentee['email'], [
            'transaction_id' => $reference,
            'amount' => $session['total_amount'],
            'course_code' => $data['course_code'] ?? 'N/A',
            'payment_date' => date('Y-m-d H:i:s')
        ]);
        
        // Notify mentor
        $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
        $stmt->execute([$session['mentor_id']]);
        $mentor = $stmt->fetch();
        
        sendEmailNotification($mentor['email'], 'New Payment Received', 
            "Dear {$mentor['name']},\n\nA mentee has paid for session #{$session['id']}. The payment of " . 
            formatCurrency($session['total_amount']) . " is now with the admin.\n\n" .
            "Your payout of " . formatCurrency($session['mentor_payout']) . " will be released after session completion.\n\n" .
            "Regards,\nMentorMatch Team"
        );
        
        // Return success
        http_response_code(200);
        echo 'OK';
        
    } elseif ($status === 'failed' || $status === 'cancelled') {
        // Update session payment status
        $stmt = $pdo->prepare("UPDATE sessions SET payment_status = 'failed' WHERE id = ?");
        $stmt->execute([$session['id']]);
        
        // Log the failure
        logActivity($session['mentee_id'], 'Payment failed for session #' . $session['id']);
        
        http_response_code(200);
        echo 'OK';
    } else {
        // Unknown status
        http_response_code(400);
        exit('Unknown status');
    }
} else {
    http_response_code(400);
    exit('Invalid payload');
}
?>