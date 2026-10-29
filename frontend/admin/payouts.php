<?php
// admin/payouts.php
require_once dirname(__DIR__, 2) . '/backend/includes/functions.php';
requireAdmin();

$user = getCurrentUser();
$error = '';
$success = '';

// Release payment to mentor
if (isset($_GET['release'])) {
    $payment_id = (int)$_GET['release'];
    
    // Get payment details
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? AND payment_status = 'captured'");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();
    
    if ($payment) {
        // Update payment status
        $stmt = $pdo->prepare("UPDATE payments SET payment_status = 'released', payout_date = NOW() WHERE id = ?");
        $stmt->execute([$payment_id]);
        
        // Update session payment status
        $stmt = $pdo->prepare("UPDATE sessions SET payment_status = 'released_to_mentor', payout_date = NOW() WHERE id = ?");
        $stmt->execute([$payment['session_id']]);
        
        // Update admin revenue
        $stmt = $pdo->prepare("SELECT semester_id FROM sessions WHERE id = ?");
        $stmt->execute([$payment['session_id']]);
        $semester = $stmt->fetch();
        
        if ($semester) {
            $stmt = $pdo->prepare("INSERT INTO admin_revenue
                                   (semester_id, total_fees_collected, total_payouts_made, total_service_fees)
                                   VALUES (?, ?, ?, ?)
                                   ON CONFLICT (semester_id) DO UPDATE SET
                                       total_fees_collected = admin_revenue.total_fees_collected + EXCLUDED.total_fees_collected,
                                       total_payouts_made = admin_revenue.total_payouts_made + EXCLUDED.total_payouts_made,
                                       total_service_fees = admin_revenue.total_service_fees + EXCLUDED.total_service_fees");
            $stmt->execute([
                $semester['semester_id'],
                $payment['amount'],
                $payment['mentor_payout'],
                $payment['admin_fee']
            ]);
        }
        
        logActivity($user['id'], 'Released payment #' . $payment_id . ' to mentor #' . $payment['mentor_id']);
        $success = 'Payment released successfully!';
    } else {
        $error = 'Payment not found or already released.';
    }
}

// Get all captured payments (ready for release)
$stmt = $pdo->prepare("
    SELECT p.*, 
           m.name as mentor_name, m.phone as mentor_phone, m.email as mentor_email,
           u.name as mentee_name,
           s.course_code, s.session_date
    FROM payments p
    JOIN sessions s ON p.session_id = s.id
    JOIN users m ON p.mentor_id = m.id
    JOIN users u ON p.mentee_id = u.id
    WHERE p.payment_status = 'captured'
    ORDER BY p.created_at ASC
");
$stmt->execute();
$pending_payouts = $stmt->fetchAll();

// Get released payments history
$stmt = $pdo->prepare("
    SELECT p.*, 
           m.name as mentor_name, 
           u.name as mentee_name,
           s.course_code
    FROM payments p
    JOIN sessions s ON p.session_id = s.id
    JOIN users m ON p.mentor_id = m.id
    JOIN users u ON p.mentee_id = u.id
    WHERE p.payment_status = 'released'
    ORDER BY p.payout_date DESC
    LIMIT 20
");
$stmt->execute();
$released_payments = $stmt->fetchAll();

// Get mentor payout requests
$stmt = $pdo->prepare("
    SELECT pr.*, u.name as mentor_name, u.phone as mentor_phone
    FROM payout_requests pr
    JOIN users u ON pr.mentor_id = u.id
    WHERE pr.status = 'pending'
    ORDER BY pr.created_at ASC
");
$stmt->execute();
$payout_requests = $stmt->fetchAll();

// FIX: Get counts properly - use arrays from fetchAll()
$pending_payouts_count = is_array($pending_payouts) ? count($pending_payouts) : 0;
$released_payments_count = is_array($released_payments) ? count($released_payments) : 0;
$payout_requests_count = is_array($payout_requests) ? count($payout_requests) : 0;

// Get total statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM payments WHERE payment_status = 'captured'");
$total_captured = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total FROM payments WHERE payment_status = 'released'");
$total_released = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT SUM(amount) as total FROM payments WHERE payment_status = 'captured'");
$total_captured_amount = $stmt->fetchColumn() ?? 0;

$stmt = $pdo->query("SELECT SUM(amount) as total FROM payments WHERE payment_status = 'released'");
$total_released_amount = $stmt->fetchColumn() ?? 0;

$stmt = $pdo->query("SELECT SUM(admin_fee) as total FROM payments WHERE payment_status = 'released'");
$total_fees = $stmt->fetchColumn() ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payouts - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .stat-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: default;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        .payment-card {
            transition: all 0.3s ease;
        }
        .payment-card:hover {
            background-color: #f8f9fa;
        }
        .badge-captured {
            background-color: #0d6efd;
            color: white;
        }
        .badge-released {
            background-color: #28a745;
            color: white;
        }
        .badge-pending {
            background-color: #ffc107;
            color: #212529;
        }
        @media (max-width: 768px) {
            .table-responsive { font-size: 0.85rem; }
            .btn-sm { font-size: 0.7rem; padding: 4px 8px; }
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
                <h3><i class="bi bi-cash-stack"></i> Payouts</h3>
                <p class="text-muted">Release payments to mentors after session completion.</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3 col-6">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <h6 class="card-title">Pending Payouts</h6>
                                <h2 class="mb-0"><?php echo $pending_payouts_count; ?></h2>
                                <small>Ready to release</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <h6 class="card-title">Released</h6>
                                <h2 class="mb-0"><?php echo $total_released; ?></h2>
                                <small>Completed payouts</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card stat-card bg-warning text-dark">
                            <div class="card-body">
                                <h6 class="card-title">Total Amount</h6>
                                <h2 class="mb-0"><?php echo formatCurrency($total_captured_amount); ?></h2>
                                <small>Awaiting release</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <h6 class="card-title">Service Fees</h6>
                                <h2 class="mb-0"><?php echo formatCurrency($total_fees); ?></h2>
                                <small>5% collected</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabs -->
                <ul class="nav nav-tabs mb-4" id="payoutTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">
                            <i class="bi bi-clock"></i> Pending Payouts
                            <?php if ($pending_payouts_count > 0): ?>
                                <span class="badge bg-danger"><?php echo $pending_payouts_count; ?></span>
                            <?php endif; ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="requests-tab" data-bs-toggle="tab" data-bs-target="#requests" type="button" role="tab">
                            <i class="bi bi-envelope"></i> Withdrawal Requests
                            <?php if ($payout_requests_count > 0): ?>
                                <span class="badge bg-warning text-dark"><?php echo $payout_requests_count; ?></span>
                            <?php endif; ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab">
                            <i class="bi bi-clock-history"></i> History
                            <span class="badge bg-secondary"><?php echo $released_payments_count; ?></span>
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content">
                    <!-- Pending Payouts Tab -->
                    <div class="tab-pane fade show active" id="pending" role="tabpanel">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5 class="mb-0">Payments Ready for Release</h5>
                                <small>Release these payments to mentors (95% of total after 5% admin fee)</small>
                            </div>
                            <div class="card-body p-0">
                                <?php if ($pending_payouts && count($pending_payouts) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Ref</th>
                                                    <th>Mentor</th>
                                                    <th>Mentee</th>
                                                    <th>Course</th>
                                                    <th>Total</th>
                                                    <th>Fee (5%)</th>
                                                    <th>Payout</th>
                                                    <th>Date</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pending_payouts as $payment): ?>
                                                <tr class="payment-card">
                                                    <td><code><?php echo substr($payment['transaction_reference'], -8); ?></code></td>
                                                    <td><?php echo htmlspecialchars($payment['mentor_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($payment['mentee_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($payment['course_code']); ?></td>
                                                    <td><?php echo formatCurrency($payment['amount']); ?></td>
                                                    <td><?php echo formatCurrency($payment['admin_fee']); ?></td>
                                                    <td><strong><?php echo formatCurrency($payment['mentor_payout']); ?></strong></td>
                                                    <td><?php echo date('d M Y', strtotime($payment['created_at'])); ?></td>
                                                    <td>
                                                        <a href="payouts.php?release=<?php echo $payment['id']; ?>" 
                                                           class="btn btn-sm btn-success"
                                                           onclick="return confirm('Release <?php echo formatCurrency($payment['mentor_payout']); ?> to <?php echo htmlspecialchars($payment['mentor_name']); ?>?')">
                                                            <i class="bi bi-cash"></i> Release
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-check-circle display-1 text-success"></i>
                                        <p class="text-muted mt-3">No pending payouts.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Withdrawal Requests Tab -->
                    <div class="tab-pane fade" id="requests" role="tabpanel">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5 class="mb-0">Mentor Withdrawal Requests</h5>
                            </div>
                            <div class="card-body p-0">
                                <?php if ($payout_requests && count($payout_requests) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Mentor</th>
                                                    <th>Phone</th>
                                                    <th>Amount</th>
                                                    <th>Method</th>
                                                    <th>Account Details</th>
                                                    <th>Date</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($payout_requests as $request): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($request['mentor_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($request['mentor_phone']); ?></td>
                                                    <td><strong><?php echo formatCurrency($request['amount']); ?></strong></td>
                                                    <td><?php echo str_replace('_', ' ', ucfirst($request['payment_method'])); ?></td>
                                                    <td><?php echo htmlspecialchars($request['account_details']); ?></td>
                                                    <td><?php echo date('d M Y', strtotime($request['created_at'])); ?></td>
                                                    <td>
                                                        <a href="process_payout.php?complete=<?php echo $request['id']; ?>" 
                                                           class="btn btn-sm btn-success"
                                                           onclick="return confirm('Complete this payout?')">
                                                            <i class="bi bi-check"></i> Complete
                                                        </a>
                                                        <a href="process_payout.php?reject=<?php echo $request['id']; ?>" 
                                                           class="btn btn-sm btn-danger"
                                                           onclick="return confirm('Reject this payout?')">
                                                            <i class="bi bi-x"></i> Reject
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-envelope display-1 text-muted"></i>
                                        <p class="text-muted mt-3">No pending withdrawal requests.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- History Tab -->
                    <div class="tab-pane fade" id="history" role="tabpanel">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5 class="mb-0">Released Payouts History</h5>
                            </div>
                            <div class="card-body p-0">
                                <?php if ($released_payments && count($released_payments) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Ref</th>
                                                    <th>Mentor</th>
                                                    <th>Mentee</th>
                                                    <th>Course</th>
                                                    <th>Payout</th>
                                                    <th>Fee</th>
                                                    <th>Released</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($released_payments as $payment): ?>
                                                <tr>
                                                    <td><code><?php echo substr($payment['transaction_reference'], -8); ?></code></td>
                                                    <td><?php echo htmlspecialchars($payment['mentor_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($payment['mentee_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($payment['course_code']); ?></td>
                                                    <td><?php echo formatCurrency($payment['mentor_payout']); ?></td>
                                                    <td><?php echo formatCurrency($payment['admin_fee']); ?></td>
                                                    <td><?php echo date('d M Y', strtotime($payment['payout_date'])); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-clock-history display-1 text-muted"></i>
                                        <p class="text-muted mt-3">No payout history.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>