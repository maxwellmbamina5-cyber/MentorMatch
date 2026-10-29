<?php
// mentee/payments.php
require_once dirname(__DIR__) . '/includes/functions.php';
requireMentee();

$user = getCurrentUser();
$error = '';
$success = '';

// Get filter parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';

// Get all payments with filters
$query = "
    SELECT p.*, 
           m.name as mentor_name, m.email as mentor_email,
           s.course_code, s.session_date, s.status as session_status,
           s.duration_minutes
    FROM payments p
    JOIN sessions s ON p.session_id = s.id
    JOIN users m ON p.mentor_id = m.id
    WHERE p.mentee_id = ?
";

$params = [$user['id']];

if ($filter_status !== 'all') {
    $query .= " AND p.payment_status = ?";
    $params[] = $filter_status;
}

if (!empty($filter_date)) {
    $query .= " AND DATE(p.created_at) = ?";
    $params[] = $filter_date;
}

$query .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Get summary statistics
$stmt = $pdo->prepare("SELECT SUM(amount) as total FROM payments WHERE mentee_id = ? AND payment_status = 'captured'");
$stmt->execute([$user['id']]);
$total_paid = $stmt->fetchColumn() ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM payments WHERE mentee_id = ? AND payment_status = 'captured'");
$stmt->execute([$user['id']]);
$total_transactions = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM payments WHERE mentee_id = ? AND payment_status = 'released'");
$stmt->execute([$user['id']]);
$released_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM payments WHERE mentee_id = ? AND payment_status = 'pending'");
$stmt->execute([$user['id']]);
$pending_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT SUM(admin_fee) as total FROM payments WHERE mentee_id = ? AND payment_status = 'captured'");
$stmt->execute([$user['id']]);
$total_fees = $stmt->fetchColumn() ?? 0;

// Get monthly spending (last 6 months)
$monthly_spending = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_label = date('M Y', strtotime("-$i months"));
    $stmt = $pdo->prepare("SELECT SUM(amount) as total FROM payments WHERE mentee_id = ? AND TO_CHAR(created_at, 'YYYY-MM') = ? AND payment_status = 'captured'");
    $stmt->execute([$user['id'], $month]);
    $monthly_spending[$month_label] = (float)($stmt->fetchColumn() ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - Mentee</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: default;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        .stat-icon {
            font-size: 2rem;
            opacity: 0.3;
            position: absolute;
            right: 15px;
            top: 15px;
        }
        .payment-card {
            transition: all 0.3s ease;
        }
        .payment-card:hover {
            background-color: #f8f9fa;
        }
        .transaction-id {
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            background: #e9ecef;
            padding: 2px 8px;
            border-radius: 4px;
        }
        .filter-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
        }
        @media (max-width: 768px) {
            .stat-card .stat-icon { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <?php include dirname(__DIR__) . '/includes/mentee_nav.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3 col-lg-2 mb-3">
                <?php include dirname(__DIR__) . '/includes/mentee_sidebar.php'; ?>
            </div>
            
            <div class="col-md-9 col-lg-10">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h3><i class="bi bi-credit-card"></i> My Payments</h3>
                        <p class="text-muted">View all your payment transactions and history.</p>
                    </div>
                    <div>
                        <span class="badge bg-primary fs-6">
                            <i class="bi bi-cash"></i> Total Spent: <?php echo formatCurrency($total_paid); ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body position-relative">
                                <i class="bi bi-credit-card stat-icon"></i>
                                <h6 class="card-title">Total Spent</h6>
                                <h2 class="mb-0"><?php echo formatCurrency($total_paid); ?></h2>
                                <small><?php echo $total_transactions; ?> transactions</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body position-relative">
                                <i class="bi bi-check-circle stat-icon"></i>
                                <h6 class="card-title">Completed</h6>
                                <h2 class="mb-0"><?php echo $released_count; ?></h2>
                                <small>Released to mentors</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-warning text-dark">
                            <div class="card-body position-relative">
                                <i class="bi bi-clock stat-icon"></i>
                                <h6 class="card-title">Pending</h6>
                                <h2 class="mb-0"><?php echo $pending_count; ?></h2>
                                <small>Awaiting confirmation</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body position-relative">
                                <i class="bi bi-receipt stat-icon"></i>
                                <h6 class="card-title">Service Fees</h6>
                                <h2 class="mb-0"><?php echo formatCurrency($total_fees); ?></h2>
                                <small>5% of total payments</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Monthly Spending Chart -->
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-graph-up"></i> Monthly Spending</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="spendingChart" style="height: 150px;"></canvas>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card shadow mb-4">
                    <div class="card-body filter-section">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" onchange="this.form.submit()">
                                    <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                                    <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="captured" <?php echo $filter_status === 'captured' ? 'selected' : ''; ?>>Captured</option>
                                    <option value="released" <?php echo $filter_status === 'released' ? 'selected' : ''; ?>>Released</option>
                                    <option value="refunded" <?php echo $filter_status === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                                    <option value="failed" <?php echo $filter_status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Date</label>
                                <input type="date" class="form-control" name="date" value="<?php echo $filter_date; ?>" onchange="this.form.submit()">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <a href="payments.php" class="btn btn-secondary w-100">Clear Filters</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Payments Table -->
                <div class="card shadow">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-list-ul"></i> Payment History</h6>
                        <span class="badge bg-secondary"><?php echo count($payments); ?> entries</span>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($payments): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Ref</th>
                                            <th>Mentor</th>
                                            <th>Course</th>
                                            <th>Amount</th>
                                            <th>Fee</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payments as $payment): ?>
                                            <?php
                                            $status_badge = 'secondary';
                                            if ($payment['payment_status'] === 'released') {
                                                $status_badge = 'success';
                                            } elseif ($payment['payment_status'] === 'captured') {
                                                $status_badge = 'primary';
                                            } elseif ($payment['payment_status'] === 'pending') {
                                                $status_badge = 'warning';
                                            } elseif ($payment['payment_status'] === 'refunded') {
                                                $status_badge = 'danger';
                                            } elseif ($payment['payment_status'] === 'failed') {
                                                $status_badge = 'secondary';
                                            }
                                            ?>
                                            <tr class="payment-card">
                                                <td>
                                                    <span class="transaction-id"><?php echo substr($payment['transaction_reference'], -8); ?></span>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($payment['mentor_name']); ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($payment['mentor_email']); ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($payment['course_code']); ?></strong>
                                                    <br><small class="text-muted"><?php echo $payment['duration_minutes']; ?> min</small>
                                                </td>
                                                <td>
                                                    <strong><?php echo formatCurrency($payment['amount']); ?></strong>
                                                    <br><small class="text-muted">Payout: <?php echo formatCurrency($payment['mentor_payout']); ?></small>
                                                </td>
                                                <td><?php echo formatCurrency($payment['admin_fee']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $status_badge; ?>">
                                                        <?php echo getPaymentStatus($payment['payment_status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo date('d M Y', strtotime($payment['created_at'])); ?>
                                                    <br><small class="text-muted"><?php echo date('h:i A', strtotime($payment['created_at'])); ?></small>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#detailsModal"
                                                            data-payment='<?php echo json_encode($payment); ?>'>
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <?php if ($payment['payment_status'] === 'pending'): ?>
                                                        <a href="payment.php?session_id=<?php echo $payment['session_id']; ?>" 
                                                           class="btn btn-sm btn-success">
                                                            <i class="bi bi-credit-card"></i> Pay
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-credit-card display-1 text-muted"></i>
                                <p class="text-muted mt-3">No payment history found.</p>
                                <a href="../mentee/search.php" class="btn btn-primary">Find a Mentor</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Payment Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Payment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="paymentDetails">
                    <!-- Dynamic content -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Monthly Spending Chart
        const ctx = document.getElementById('spendingChart').getContext('2d');
        const monthlyLabels = <?php echo json_encode(array_keys($monthly_spending)); ?>;
        const monthlyData = <?php echo json_encode(array_values($monthly_spending)); ?>;
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: 'Spending (K)',
                    data: monthlyData,
                    backgroundColor: 'rgba(13, 110, 253, 0.6)',
                    borderColor: 'rgba(13, 110, 253, 1)',
                    borderWidth: 2,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'K' + value;
                            }
                        }
                    }
                }
            }
        });
        
        // Payment Details Modal
        document.addEventListener('DOMContentLoaded', function() {
            const detailsModal = document.getElementById('detailsModal');
            detailsModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const payment = JSON.parse(button.dataset.payment);
                
                const statusBadge = {
                    'pending': 'warning',
                    'captured': 'primary',
                    'released': 'success',
                    'refunded': 'danger',
                    'failed': 'secondary'
                };
                
                function formatCurrency(amount) {
                    return 'K' + parseFloat(amount).toFixed(2);
                }
                
                function getPaymentStatus(status) {
                    const statuses = {
                        'pending': 'Pending',
                        'captured': 'Paid to Admin',
                        'released': 'Released to Mentor',
                        'refunded': 'Refunded',
                        'failed': 'Failed'
                    };
                    return statuses[status] || status;
                }
                
                const html = `
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr><td><strong>Reference:</strong></td><td><code>${payment.transaction_reference}</code></td></tr>
                                <tr><td><strong>Mentor:</strong></td><td>${payment.mentor_name} (${payment.mentor_email})</td></tr>
                                <tr><td><strong>Course:</strong></td><td>${payment.course_code}</td></tr>
                                <tr><td><strong>Session Date:</strong></td><td>${payment.session_date ? new Date(payment.session_date).toLocaleString() : 'N/A'}</td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr><td><strong>Amount:</strong></td><td><h4 class="text-primary">${formatCurrency(payment.amount)}</h4></td></tr>
                                <tr><td><strong>Admin Fee (5%):</strong></td><td>${formatCurrency(payment.admin_fee)}</td></tr>
                                <tr><td><strong>Mentor Payout:</strong></td><td>${formatCurrency(payment.mentor_payout)}</td></tr>
                                <tr><td><strong>Status:</strong></td><td><span class="badge bg-${statusBadge[payment.payment_status] || 'secondary'}">${getPaymentStatus(payment.payment_status)}</span></td></tr>
                                <tr><td><strong>Payment Method:</strong></td><td>${payment.payment_method || 'Mobile Money'}</td></tr>
                                <tr><td><strong>Date:</strong></td><td>${payment.payment_date ? new Date(payment.payment_date).toLocaleString() : 'N/A'}</td></tr>
                            </table>
                        </div>
                    </div>
                    ${payment.notes ? `<div class="alert alert-info mt-2"><strong>Notes:</strong> ${payment.notes}</div>` : ''}
                `;
                
                document.getElementById('paymentDetails').innerHTML = html;
            });
        });
    </script>
</body>
</html>