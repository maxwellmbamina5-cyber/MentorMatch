<?php
// mentee/payment.php
require_once '../includes/functions.php';
require_once '../includes/payment.php';
requireMentee();

$error = '';
$success = '';
$session_data = null;
$payment_gateway = new PaymentGateway();

// Get session details
if (isset($_GET['session_id'])) {
    $session_id = (int)$_GET['session_id'];
    $user = getCurrentUser();
    
    $stmt = $pdo->prepare("SELECT s.*, m.name as mentor_name, m.phone as mentor_phone, 
                           u.phone as mentee_phone, u.name as mentee_name,
                           r.rate_per_hour
                           FROM sessions s
                           JOIN users m ON s.mentor_id = m.id
                           JOIN users u ON s.mentee_id = u.id
                           JOIN mentor_rates r ON r.mentor_id = s.mentor_id AND r.course_code = s.course_code
                           WHERE s.id = ? AND s.mentee_id = ?");
    $stmt->execute([$session_id, $user['id']]);
    $session_data = $stmt->fetch();
    
    if (!$session_data) {
        $error = 'Session not found or unauthorized.';
    } elseif ($session_data['payment_status'] !== 'pending') {
        $error = 'Payment already processed for this session.';
    }
}

// Process payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['session_id'])) {
    $session_id = (int)$_POST['session_id'];
    $user = getCurrentUser();
    
    $stmt = $pdo->prepare("SELECT * FROM sessions WHERE id = ? AND mentee_id = ?");
    $stmt->execute([$session_id, $user['id']]);
    $session = $stmt->fetch();
    
    if (!$session) {
        $error = 'Session not found';
    } else {
        // Generate transaction reference
        $reference = generateTransactionRef();
        
        // Initiate MoMo payment
        $result = $payment_gateway->initiatePayment(
            $user['phone'],
            $session['total_amount'],
            $reference,
            'MentorMatch Session Payment'
        );
        
        if ($result['success']) {
            // Update session with payment info
            $stmt = $pdo->prepare("UPDATE sessions SET 
                                   payment_status = 'paid_to_admin',
                                   transaction_id = ?,
                                   payment_method = 'mobile_money',
                                   payment_date = NOW()
                                   WHERE id = ?");
            $stmt->execute([$reference, $session_id]);
            
            // Insert into payments table
            $stmt = $pdo->prepare("INSERT INTO payments 
                                   (session_id, mentee_id, mentor_id, amount, admin_fee, mentor_payout, 
                                    payment_status, transaction_reference, payment_method, payment_date)
                                   VALUES (?, ?, ?, ?, ?, ?, 'captured', ?, 'mobile_money', NOW())");
            $stmt->execute([
                $session_id,
                $user['id'],
                $session['mentor_id'],
                $session['total_amount'],
                $session['admin_fee'],
                $session['mentor_payout'],
                $reference
            ]);
            
            logActivity($user['id'], 'Payment made for session ' . $session_id, 'Amount: ' . $session['total_amount']);
            
            // Send notification
            sendEmail($user['email'], 'Payment Successful', 'Your payment for session has been received.');
            
            $success = 'Payment successful! Your session is now confirmed.';
        } else {
            $error = 'Payment failed. Please try again.';
        }
    }
}

// If no session ID, show payment history
if (!isset($_GET['session_id']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $user = getCurrentUser();
    $stmt = $pdo->prepare("SELECT p.*, s.session_date, s.course_code, u.name as mentor_name, s.status as session_status
                           FROM payments p
                           JOIN sessions s ON p.session_id = s.id
                           JOIN users u ON s.mentor_id = u.id
                           WHERE p.mentee_id = ?
                           ORDER BY p.created_at DESC");
    $stmt->execute([$user['id']]);
    $payments = $stmt->fetchAll();
} else {
    $payments = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - MentorMatch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/mentee_nav.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <h3 class="mb-4">Payment</h3>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                    <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                <?php endif; ?>
                
                <?php if ($session_data && !$success): ?>
                    <div class="card shadow">
                        <div class="card-body">
                            <h5 class="card-title">Payment Summary</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Session ID:</strong></td>
                                    <td>#<?php echo $session_data['id']; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Course:</strong></td>
                                    <td><?php echo htmlspecialchars($session_data['course_code']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Mentor:</strong></td>
                                    <td><?php echo htmlspecialchars($session_data['mentor_name']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Date & Time:</strong></td>
                                    <td><?php echo date('F d, Y h:i A', strtotime($session_data['session_date'])); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Duration:</strong></td>
                                    <td><?php echo $session_data['duration_minutes']; ?> minutes</td>
                                </tr>
                                <tr>
                                    <td><strong>Mentor Rate:</strong></td>
                                    <td><?php echo formatCurrency($session_data['rate_per_hour']); ?>/hour</td>
                                </tr>
                                <tr class="table-primary">
                                    <td><strong>Total Amount:</strong></td>
                                    <td><strong><?php echo formatCurrency($session_data['total_amount']); ?></strong></td>
                                </tr>
                                <tr>
                                    <td><strong>Admin Service Fee (5%):</strong></td>
                                    <td><?php echo formatCurrency($session_data['admin_fee']); ?></td>
                                </tr>
                                <tr class="text-muted">
                                    <td colspan="2"><small>Payment will be made via Mobile Money (MoMo) to the admin number: <?php echo MOMO_PHONE; ?></small></td>
                                </tr>
                            </table>
                            
                            <form method="POST" action="">
                                <input type="hidden" name="session_id" value="<?php echo $session_data['id']; ?>">
                                <button type="submit" class="btn btn-success w-100">
                                    Pay <?php echo formatCurrency($session_data['total_amount']); ?> via MoMo
                                </button>
                                <a href="booking.php" class="btn btn-secondary w-100 mt-2">Cancel</a>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Payment History -->
                <?php if ($payments): ?>
                    <div class="card shadow mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">Payment History</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Ref</th>
                                            <th>Course</th>
                                            <th>Mentor</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payments as $payment): ?>
                                        <tr>
                                            <td>#<?php echo substr($payment['transaction_reference'], -8); ?></td>
                                            <td><?php echo htmlspecialchars($payment['course_code']); ?></td>
                                            <td><?php echo htmlspecialchars($payment['mentor_name']); ?></td>
                                            <td><?php echo formatCurrency($payment['amount']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $payment['payment_status'] === 'released' ? 'success' : 'warning'; ?>">
                                                    <?php echo getPaymentStatus($payment['payment_status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>