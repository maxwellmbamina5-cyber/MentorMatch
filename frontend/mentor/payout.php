<?php
// mentor/payout.php
require_once dirname(__DIR__, 2) . '/backend/includes/functions.php';
requireMentor();

$user = getCurrentUser();
$error = '';
$success = '';

// Get total available balance
$stmt = $pdo->prepare("SELECT SUM(mentor_payout) as total 
                       FROM sessions 
                       WHERE mentor_id = ? AND payment_status = 'released_to_mentor'");
$stmt->execute([$user['id']]);
$total_earned = $stmt->fetchColumn() ?? 0;

// Get pending payouts (released but not withdrawn)
$stmt = $pdo->prepare("SELECT SUM(amount) as total 
                       FROM payout_requests 
                       WHERE mentor_id = ? AND status IN ('pending', 'processing')");
$stmt->execute([$user['id']]);
$pending_withdrawal = $stmt->fetchColumn() ?? 0;

$available_balance = $total_earned - $pending_withdrawal;

// Request payout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_payout'])) {
    $amount = (float)$_POST['amount'];
    $payment_method = sanitize($_POST['payment_method']);
    $account_details = sanitize($_POST['account_details']);
    $phone_number = sanitize($_POST['phone_number'] ?? '');
    
    if ($amount <= 0) {
        $error = 'Amount must be greater than 0.';
    } elseif ($amount > $available_balance) {
        $error = 'Insufficient balance. Available: ' . formatCurrency($available_balance);
    } elseif ($amount < 10) {
        $error = 'Minimum withdrawal amount is K10.00';
    } else {
        $stmt = $pdo->prepare("INSERT INTO payout_requests 
                               (mentor_id, amount, payment_method, phone_number, account_details, status)
                               VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$user['id'], $amount, $payment_method, $phone_number, $account_details]);
        
        logActivity($user['id'], 'Requested payout of ' . formatCurrency($amount));
        $success = 'Payout request submitted successfully!';
    }
}

// Get payout history
$stmt = $pdo->prepare("SELECT * FROM payout_requests WHERE mentor_id = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$payout_history = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payout - Mentor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include dirname(__DIR__, 2) . '/frontend/includes/mentor_nav.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3 col-lg-2 mb-3">
                <?php include dirname(__DIR__, 2) . '/frontend/includes/mentor_sidebar.php'; ?>
            </div>
            
            <div class="col-md-9 col-lg-10">
                <h3><i class="bi bi-cash-stack"></i> Payout</h3>
                <p class="text-muted">Request withdrawals of your mentoring earnings.</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-5">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5 class="mb-0">Balance</h5>
                            </div>
                            <div class="card-body">
                                <div class="text-center">
                                    <h1 class="display-4 text-success"><?php echo formatCurrency($available_balance); ?></h1>
                                    <p class="text-muted">Available Balance</p>
                                </div>
                                <hr>
                                <div class="row text-center">
                                    <div class="col-6">
                                        <h6><?php echo formatCurrency($total_earned); ?></h6>
                                        <small class="text-muted">Total Earned</small>
                                    </div>
                                    <div class="col-6">
                                        <h6><?php echo formatCurrency($pending_withdrawal); ?></h6>
                                        <small class="text-muted">Pending Withdrawal</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($available_balance >= 10): ?>
                            <div class="card shadow mt-3">
                                <div class="card-header">
                                    <h5 class="mb-0">Request Withdrawal</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="">
                                        <div class="mb-3">
                                            <label for="amount" class="form-label">Amount *</label>
                                            <div class="input-group">
                                                <span class="input-group-text">K</span>
                                                <input type="number" class="form-control" id="amount" name="amount" 
                                                       min="10" max="<?php echo $available_balance; ?>" step="0.01" required>
                                            </div>
                                            <small class="text-muted">Min: K10.00 | Max: <?php echo formatCurrency($available_balance); ?></small>
                                        </div>
                                        <div class="mb-3">
                                            <label for="payment_method" class="form-label">Payment Method *</label>
                                            <select class="form-select" id="payment_method" name="payment_method" required>
                                                <option value="">Select Method</option>
                                                <option value="mobile_money">Mobile Money (MoMo)</option>
                                                <option value="bank_transfer">Bank Transfer</option>
                                                <option value="cash">Cash Pickup</option>
                                            </select>
                                        </div>
                                        <div class="mb-3" id="phoneField">
                                            <label for="phone_number" class="form-label">Phone Number</label>
                                            <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                                                   placeholder="+2609XXXXXXXX">
                                        </div>
                                        <div class="mb-3">
                                            <label for="account_details" class="form-label">Account Details</label>
                                            <textarea class="form-control" id="account_details" name="account_details" 
                                                      rows="2" placeholder="Bank name, account number, branch..."></textarea>
                                        </div>
                                        <button type="submit" name="request_payout" class="btn btn-success w-100">
                                            <i class="bi bi-cash"></i> Request Payout
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-7">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5 class="mb-0">Payout History</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($payout_history): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Amount</th>
                                                    <th>Method</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($payout_history as $payout): ?>
                                                <tr>
                                                    <td><?php echo date('d M Y', strtotime($payout['created_at'])); ?></td>
                                                    <td><?php echo formatCurrency($payout['amount']); ?></td>
                                                    <td><?php echo str_replace('_', ' ', ucfirst($payout['payment_method'])); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $payout['status'] === 'completed' ? 'success' : 
                                                                ($payout['status'] === 'pending' ? 'warning' : 
                                                                ($payout['status'] === 'processing' ? 'info' : 'danger')); 
                                                        ?>">
                                                            <?php echo ucfirst($payout['status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No payout history.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Show/hide phone field based on payment method
        document.getElementById('payment_method').addEventListener('change', function() {
            const phoneField = document.getElementById('phoneField');
            if (this.value === 'mobile_money') {
                phoneField.style.display = 'block';
            } else {
                phoneField.style.display = 'none';
            }
        });
    </script>
</body>
</html>