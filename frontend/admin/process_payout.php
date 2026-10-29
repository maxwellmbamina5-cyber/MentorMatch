<?php
// admin/process_payout.php
require_once dirname(__DIR__, 2) . '/backend/includes/functions.php';
requireAdmin();

$user = getCurrentUser();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = (int)$_POST['request_id'];
    
    if (isset($_POST['complete'])) {
        // Mark payout request as completed
        $stmt = $pdo->prepare("UPDATE payout_requests 
                               SET status = 'completed', processed_by = ?, processed_at = NOW() 
                               WHERE id = ?");
        $stmt->execute([$user['id'], $request_id]);
        
        logActivity($user['id'], 'Completed payout request #' . $request_id);
        $success = 'Payout request completed successfully!';
    } elseif (isset($_POST['reject'])) {
        // Mark payout request as failed
        $stmt = $pdo->prepare("UPDATE payout_requests 
                               SET status = 'failed', processed_by = ?, processed_at = NOW() 
                               WHERE id = ?");
        $stmt->execute([$user['id'], $request_id]);
        
        logActivity($user['id'], 'Rejected payout request #' . $request_id);
        $success = 'Payout request rejected.';
    }
}

header("Location: payouts.php?" . ($success ? 'success=' . urlencode($success) : 'error=' . urlencode($error)));
exit();
?>