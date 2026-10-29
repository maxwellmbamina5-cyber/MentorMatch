<?php
// admin/payments.php
require_once dirname(__DIR__, 2) . '/backend/includes/functions.php';
requireAdmin();

$user = getCurrentUser();
$filter_status = $_GET['status'] ?? 'all';

// Get all payments
$query = "
    SELECT p.*, 
           m.name as mentor_name, m.email as mentor_email, m.phone as mentor_phone,
           u.name as mentee_name, u.email as mentee_email, u.phone as mentee_phone,
           s.course_code, s.session_date, s.status as session_status
    FROM payments p
    JOIN sessions s ON p.session_id = s.id
    JOIN users m ON p.mentor_id = m.id
    JOIN users u ON p.mentee_id = u.id
";

if ($filter_status !== 'all') {
    $query .= " WHERE p.payment_status = ?";
}

$query .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($query);
if ($filter_status !== 'all') {
    $stmt->execute([$filter_status]);
} else {
    $stmt->execute();
}
$payments = $stmt->fetchAll();

// Get summary stats
$stmt = $pdo->query("SELECT COUNT(*) as total FROM payments WHERE payment_status != 'pending'");
$total_payments = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT SUM(amount) as total FROM payments WHERE payment_status = 'captured'");
$total_captured = $stmt->fetchColumn() ?? 0;

$stmt = $pdo->query("SELECT SUM(amount) as total FROM payments WHERE payment_status = 'released'");
$total_released = $stmt->fetchColumn() ?? 0;

$stmt = $pdo->query("SELECT SUM(admin_fee) as total FROM payments WHERE payment_status = 'released'");
$total_fees = $stmt->fetchColumn() ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include dirname(__DIR__, 2) . '/frontend/includes/admin_nav.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3 col-lg-2 mb-3">
                <?php include dirname(__DIR__, 2) . '/frontend/includes/admin_sidebar.php'; ?>
            </div>
            
            <div class="col-md-9 col-lg-10">
                <h3><i class="bi bi-credit-card"></i> Payments</h3>
                <p class="text-muted">View and manage all payment transactions.</p>
                
                <!-- Summary Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Payments</h5>
                                <h2><?php echo $total_payments; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Captured</h5>
                                <h2><?php echo formatCurrency($total_captured); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Released</h5>
                                <h2><?php echo formatCurrency($total_released); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <h5 class="card-title">Service Fees</h5>
                                <h2><?php echo formatCurrency($total_fees); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filter -->
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Filter by Status</label>
                                <select class="form-select" name="status" onchange="this.form.submit()">
                                    <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All</option>
                                    <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="captured" <?php echo $filter_status === 'captured' ? 'selected' : ''; ?>>Captured</option>
                                    <option value="released" <?php echo $filter_status === 'released' ? 'selected' : ''; ?>>Released</option>
                                    <option value="refunded" <?php echo $filter_status === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                                    <option value="failed" <?php echo $filter_status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                </select>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Payments Table -->
                <div class="card shadow">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Ref</th>
                                        <th>Mentee</th>
                                        <th>Mentor</th>
                                        <th>Course</th>
                                        <th>Amount</th>
                                        <th>Fee (5%)</th>
                                        <th>Payout</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><code><?php echo substr($payment['transaction_reference'], -8); ?></code></td>
                                        <td><?php echo htmlspecialchars($payment['mentee_name']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['mentor_name']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['course_code']); ?></td>
                                        <td><?php echo formatCurrency($payment['amount']); ?></td>
                                        <td><?php echo formatCurrency($payment['admin_fee']); ?></td>
                                        <td><?php echo formatCurrency($payment['mentor_payout']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $payment['payment_status'] === 'released' ? 'success' : 
                                                    ($payment['payment_status'] === 'captured' ? 'info' : 
                                                    ($payment['payment_status'] === 'pending' ? 'warning' : 'danger')); 
                                            ?>">
                                                <?php echo getPaymentStatus($payment['payment_status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d M Y', strtotime($payment['created_at'])); ?></td>
                                        <td>
                                            <?php if ($payment['payment_status'] === 'captured'): ?>
                                                <a href="payouts.php?release=<?php echo $payment['id']; ?>" 
                                                   class="btn btn-sm btn-success">
                                                    <i class="bi bi-cash"></i> Release
                                                </a>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-info" data-bs-toggle="modal" 
                                                    data-bs-target="#detailsModal"
                                                    data-payment='<?php echo json_encode($payment); ?>'>
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Payment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="paymentDetails">
                    <!-- Dynamic content -->
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const detailsModal = document.getElementById('detailsModal');
            detailsModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const payment = JSON.parse(button.dataset.payment);
                
                const html = `
                    <table class="table table-borderless">
                        <tr><td><strong>Reference:</strong></td><td><code>${payment.transaction_reference}</code></td></tr>
                        <tr><td><strong>Mentee:</strong></td><td>${payment.mentee_name} (${payment.mentee_email})</td></tr>
                        <tr><td><strong>Mentor:</strong></td><td>${payment.mentor_name} (${payment.mentor_email})</td></tr>
                        <tr><td><strong>Course:</strong></td><td>${payment.course_code}</td></tr>
                        <tr><td><strong>Amount:</strong></td><td>${formatCurrency(payment.amount)}</td></tr>
                        <tr><td><strong>Admin Fee (5%):</strong></td><td>${formatCurrency(payment.admin_fee)}</td></tr>
                        <tr><td><strong>Mentor Payout:</strong></td><td>${formatCurrency(payment.mentor_payout)}</td></tr>
                        <tr><td><strong>Status:</strong></td><td><span class="badge bg-${payment.payment_status === 'released' ? 'success' : 'info'}">${payment.payment_status}</span></td></tr>
                        <tr><td><strong>Payment Method:</strong></td><td>${payment.payment_method || 'N/A'}</td></tr>
                        <tr><td><strong>Payment Date:</strong></td><td>${payment.payment_date ? new Date(payment.payment_date).toLocaleString() : 'N/A'}</td></tr>
                        <tr><td><strong>Payout Date:</strong></td><td>${payment.payout_date ? new Date(payment.payout_date).toLocaleString() : 'Not yet'}</td></tr>
                    </table>
                `;
                
                document.getElementById('paymentDetails').innerHTML = html;
            });
        });
        
        function formatCurrency(amount) {
            return 'K' + parseFloat(amount).toFixed(2);
        }
    </script>
</body>
</html>