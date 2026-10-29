<?php
// admin/session_details.php
require_once dirname(__DIR__, 2) . '/backend/includes/functions.php';
requireAdmin();

$user = getCurrentUser();
$error = '';

if (!isset($_GET['id'])) {
    header('Location: sessions.php');
    exit();
}

$session_id = (int)$_GET['id'];

// Get session details
$stmt = $pdo->prepare("
    SELECT s.*, 
           m.name as mentor_name, m.email as mentor_email, m.phone as mentor_phone,
           u.name as mentee_name, u.email as mentee_email, u.phone as mentee_phone,
           sem.semester_name,
           (SELECT AVG(rating) FROM feedback WHERE mentor_id = m.id) as mentor_avg_rating,
           (SELECT COUNT(*) FROM feedback WHERE mentor_id = m.id) as mentor_total_reviews,
           (SELECT COUNT(*) FROM sessions WHERE mentor_id = m.id AND status = 'completed') as mentor_total_sessions
    FROM sessions s
    JOIN users m ON s.mentor_id = m.id
    JOIN users u ON s.mentee_id = u.id
    JOIN semesters sem ON s.semester_id = sem.id
    WHERE s.id = ?
");
$stmt->execute([$session_id]);
$session = $stmt->fetch();

if (!$session) {
    $error = 'Session not found.';
}

// Get feedback if exists
$stmt = $pdo->prepare("SELECT * FROM feedback WHERE session_id = ?");
$stmt->execute([$session_id]);
$feedback = $stmt->fetch();

// Get payment details
$stmt = $pdo->prepare("SELECT * FROM payments WHERE session_id = ?");
$stmt->execute([$session_id]);
$payment = $stmt->fetch();

// Get session timeline (for activity log)
$stmt = $pdo->prepare("
    SELECT * FROM system_logs 
    WHERE details LIKE ? 
    ORDER BY created_at ASC
");
$stmt->execute(["%session #$session_id%"]);
$timeline = $stmt->fetchAll();

// Handle session status update (admin override)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = sanitize($_POST['new_status']);
    $admin_notes = sanitize($_POST['admin_notes'] ?? '');
    
    $stmt = $pdo->prepare("UPDATE sessions SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$new_status, $session_id]);
    
    logActivity($user['id'], "Updated session #$session_id status to $new_status - Notes: $admin_notes");
    $success = "Session status updated to " . ucfirst(str_replace('_', ' ', $new_status));
    
    // Refresh session data
    $stmt = $pdo->prepare("SELECT * FROM sessions WHERE id = ?");
    $stmt->execute([$session_id]);
    $session = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Details - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/mobile.css" rel="stylesheet">
    <style>
        .detail-card {
            border-radius: 16px;
            border: none;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        .detail-card .card-header {
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            padding: 12px 20px;
        }
        .status-badge {
            font-size: 1rem;
            padding: 8px 20px;
            border-radius: 50px;
        }
        .timeline-item {
            padding: 10px 15px;
            border-left: 3px solid #0d6efd;
            margin-bottom: 8px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .timeline-item .time {
            font-size: 0.75rem;
            color: #6c757d;
        }
        .avatar-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
        }
        .avatar-circle.bg-1 { background: linear-gradient(135deg, #0d6efd, #0a58ca); }
        .avatar-circle.bg-2 { background: linear-gradient(135deg, #28a745, #1e7e34); }
        @media (max-width: 768px) {
            .detail-card .card-header { padding: 10px 14px; }
            .status-badge { font-size: 0.8rem; padding: 4px 12px; }
        }
    </style>
</head>
<body>
    <?php include dirname(__DIR__, 2) . '/frontend/includes/admin_nav.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3 col-lg-2 mb-3">
                <?php include dirname(__DIR__, 2) . '/frontend/includes/admin_sidebar.php'; ?>
            </div>
            
            <div class="col-md-9 col-lg-10">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                    <div>
                        <h3><i class="bi bi-calendar-check"></i> Session Details</h3>
                        <p class="text-muted">#<?php echo $session_id; ?> - <?php echo htmlspecialchars($session['course_code'] ?? ''); ?></p>
                    </div>
                    <a href="sessions.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Sessions
                    </a>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if ($session): ?>
                    <div class="row g-3">
                        <!-- Main Details -->
                        <div class="col-lg-8">
                            <div class="card detail-card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Session Information</h6>
                                    <span class="status-badge bg-<?php 
                                        echo $session['status'] === 'completed' ? 'success' : 
                                            ($session['status'] === 'confirmed' ? 'primary' : 
                                            ($session['status'] === 'pending' ? 'warning' : 
                                            ($session['status'] === 'cancelled' ? 'danger' : 'secondary'))); 
                                    ?> text-white">
                                        <?php echo getSessionStatus($session['status']); ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <table class="table table-borderless">
                                                <tr>
                                                    <td><strong>Session ID:</strong></td>
                                                    <td>#<?php echo $session['id']; ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Course:</strong></td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($session['course_code']); ?></strong>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($session['semester_name']); ?></small>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Date & Time:</strong></td>
                                                    <td>
                                                        <?php echo date('l, F d, Y', strtotime($session['session_date'])); ?>
                                                        <br><small class="text-muted"><?php echo date('h:i A', strtotime($session['session_date'])); ?></small>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Duration:</strong></td>
                                                    <td><?php echo $session['duration_minutes']; ?> minutes</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Mentor Rate:</strong></td>
                                                    <td><?php echo formatCurrency($session['mentor_rate']); ?>/hour</td>
                                                </tr>
                                            </table>
                                        </div>
                                        <div class="col-md-6">
                                            <table class="table table-borderless">
                                                <tr>
                                                    <td><strong>Total Amount:</strong></td>
                                                    <td><h5 class="text-primary"><?php echo formatCurrency($session['total_amount']); ?></h5></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Admin Fee (5%):</strong></td>
                                                    <td><?php echo formatCurrency($session['admin_fee']); ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Mentor Payout:</strong></td>
                                                    <td><?php echo formatCurrency($session['mentor_payout']); ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Payment Status:</strong></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $session['payment_status'] === 'released_to_mentor' ? 'success' : 
                                                                ($session['payment_status'] === 'paid_to_admin' ? 'info' : 'secondary'); 
                                                        ?>">
                                                            <?php echo getPaymentStatus($session['payment_status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php if ($session['transaction_id']): ?>
                                                    <tr>
                                                        <td><strong>Transaction:</strong></td>
                                                        <td><code><?php echo htmlspecialchars($session['transaction_id']); ?></code></td>
                                                    </tr>
                                                <?php endif; ?>
                                            </table>
                                        </div>
                                    </div>
                                    
                                    <?php if ($session['meeting_link']): ?>
                                        <div class="mt-3">
                                            <a href="<?php echo htmlspecialchars($session['meeting_link']); ?>" 
                                               target="_blank" class="btn btn-primary">
                                                <i class="bi bi-video"></i> Join Meeting
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Mentor Info -->
                            <div class="card detail-card mt-3">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="bi bi-person-badge"></i> Mentor Information</h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="avatar-circle bg-1">
                                            <?php echo strtoupper(substr($session['mentor_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($session['mentor_name']); ?></h6>
                                            <p class="mb-1 text-muted small">
                                                <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($session['mentor_email']); ?>
                                                <br><i class="bi bi-phone"></i> <?php echo htmlspecialchars($session['mentor_phone']); ?>
                                            </p>
                                            <div class="d-flex gap-2">
                                                <span class="badge bg-primary"><?php echo $session['mentor_total_sessions']; ?> sessions</span>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="bi bi-star-fill"></i> <?php echo number_format($session['mentor_avg_rating'] ?? 0, 1); ?>
                                                    (<?php echo $session['mentor_total_reviews'] ?? 0; ?> reviews)
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Mentee Info -->
                            <div class="card detail-card mt-3">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="bi bi-person"></i> Mentee Information</h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="avatar-circle bg-2">
                                            <?php echo strtoupper(substr($session['mentee_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($session['mentee_name']); ?></h6>
                                            <p class="mb-1 text-muted small">
                                                <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($session['mentee_email']); ?>
                                                <br><i class="bi bi-phone"></i> <?php echo htmlspecialchars($session['mentee_phone']); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Feedback -->
                            <?php if ($feedback): ?>
                                <div class="card detail-card mt-3">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="bi bi-star"></i> Feedback</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <span class="fw-bold">Rating:</span>
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="bi bi-star-fill <?php echo $i <= $feedback['rating'] ? 'text-warning' : 'text-muted opacity-25'; ?>" 
                                                   style="font-size: 1.2rem;"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <?php if ($feedback['comments']): ?>
                                            <p class="border p-3 bg-light rounded"><?php echo nl2br(htmlspecialchars($feedback['comments'])); ?></p>
                                        <?php endif; ?>
                                        <small class="text-muted">Submitted: <?php echo date('d M Y h:i A', strtotime($feedback['submitted_at'])); ?></small>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Timeline -->
                            <?php if ($timeline): ?>
                                <div class="card detail-card mt-3">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="bi bi-clock-history"></i> Session Timeline</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php foreach ($timeline as $event): ?>
                                            <div class="timeline-item">
                                                <div class="d-flex justify-content-between">
                                                    <span><?php echo htmlspecialchars($event['action']); ?></span>
                                                    <span class="time"><?php echo date('d M Y h:i A', strtotime($event['created_at'])); ?></span>
                                                </div>
                                                <?php if ($event['user_name']): ?>
                                                    <small class="text-muted">By: <?php echo htmlspecialchars($event['user_name']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Sidebar - Actions -->
                        <div class="col-lg-4">
                            <!-- Payment Info -->
                            <?php if ($payment): ?>
                                <div class="card detail-card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="bi bi-credit-card"></i> Payment Details</h6>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-borderless">
                                            <tr>
                                                <td><strong>Reference:</strong></td>
                                                <td><code><?php echo substr($payment['transaction_reference'], -8); ?></code></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Amount:</strong></td>
                                                <td><?php echo formatCurrency($payment['amount']); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Fee:</strong></td>
                                                <td><?php echo formatCurrency($payment['admin_fee']); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Payout:</strong></td>
                                                <td><?php echo formatCurrency($payment['mentor_payout']); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Status:</strong></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $payment['payment_status'] === 'released' ? 'success' : 
                                                            ($payment['payment_status'] === 'captured' ? 'info' : 'warning'); 
                                                    ?>">
                                                        <?php echo getPaymentStatus($payment['payment_status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php if ($payment['payout_date']): ?>
                                                <tr>
                                                    <td><strong>Released:</strong></td>
                                                    <td><?php echo date('d M Y', strtotime($payment['payout_date'])); ?></td>
                                                </tr>
                                            <?php endif; ?>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Update Status -->
                            <div class="card detail-card mt-3">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="bi bi-pencil"></i> Update Status</h6>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="">
                                        <div class="mb-3">
                                            <label class="form-label">New Status</label>
                                            <select class="form-select" name="new_status" required>
                                                <option value="pending" <?php echo $session['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="confirmed" <?php echo $session['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                <option value="completed" <?php echo $session['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="cancelled" <?php echo $session['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                <option value="no_show" <?php echo $session['status'] === 'no_show' ? 'selected' : ''; ?>>No Show</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Admin Notes</label>
                                            <textarea class="form-control" name="admin_notes" rows="2" 
                                                      placeholder="Reason for status change..."></textarea>
                                        </div>
                                        <button type="submit" name="update_status" class="btn btn-primary w-100">
                                            <i class="bi bi-save"></i> Update Status
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <!-- Quick Actions -->
                            <div class="card detail-card mt-3">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="bi bi-lightning-charge"></i> Quick Actions</h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <?php if ($session['payment_status'] === 'paid_to_admin' && $session['status'] === 'completed'): ?>
                                            <a href="../admin/payouts.php?release=<?php echo $session['id']; ?>" 
                                               class="btn btn-success">
                                                <i class="bi bi-cash"></i> Release Payment
                                            </a>
                                        <?php endif; ?>
                                        <a href="sessions.php" class="btn btn-secondary">
                                            <i class="bi bi-arrow-left"></i> Back to Sessions
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>