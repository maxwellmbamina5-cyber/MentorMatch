<?php
require_once __DIR__ . '/../backend/includes/functions.php';
requireLogin();

$user = getCurrentUser();
if (!$user || !in_array($user['role'], ['mentor', 'mentee'], true)) {
    header('Location: index.php');
    exit();
}
$account_active = $user['status'] === 'active';

$error = '';
$success = '';
$active_subscription = getActiveSubscription($user['id']);
$stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = ? ORDER BY id DESC");
$stmt->execute([$user['id']]);
$subscription_history = $stmt->fetchAll();
$latest_subscription = $subscription_history[0] ?? null;
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM subscriptions WHERE user_id = ? AND approved_at IS NOT NULL");
$stmt->execute([$user['id']]);
$subscription_spending = (float)$stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_confirmation'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your request could not be verified. Reload the page and try again.';
    } else {
        try {
            $pdo->beginTransaction();
            $lock = $pdo->prepare("SELECT role, status FROM users WHERE id = ? FOR UPDATE");
            $lock->execute([$user['id']]);
            $locked_user = $lock->fetch();

            if (!$locked_user || $locked_user['role'] !== $user['role'] || $locked_user['status'] !== 'active') {
                throw new RuntimeException('Your account must be active to request subscription confirmation.');
            }

            $active = $pdo->prepare("SELECT id FROM subscriptions WHERE user_id = ? AND status = 'active' AND expires_at > NOW() LIMIT 1");
            $active->execute([$user['id']]);
            if ($active->fetch()) {
                throw new RuntimeException('Your subscription is already active.');
            }

            $pending = $pdo->prepare("SELECT id FROM subscriptions WHERE user_id = ? AND status = 'pending' LIMIT 1");
            $pending->execute([$user['id']]);
            if ($pending->fetch()) {
                throw new RuntimeException('Your payment confirmation request is already waiting for admin review.');
            }

            $insert = $pdo->prepare("INSERT INTO subscriptions (user_id, amount, status) VALUES (?, ?, 'pending')");
            $insert->execute([$user['id'], getSubscriptionPrice($user['role'])]);
            $pdo->commit();
            logActivity($user['id'], 'Requested monthly subscription payment confirmation');
            $success = 'Your request is waiting for an administrator to confirm your payment.';
        } catch (RuntimeException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $exception->getMessage();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Subscription request failed: ' . $exception->getMessage());
            $error = 'We could not submit your request. Please try again.';
        }
    }

    $active_subscription = getActiveSubscription($user['id']);
    $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = ? ORDER BY id DESC");
    $stmt->execute([$user['id']]);
    $subscription_history = $stmt->fetchAll();
    $latest_subscription = $subscription_history[0] ?? null;
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM subscriptions WHERE user_id = ? AND approved_at IS NOT NULL");
    $stmt->execute([$user['id']]);
    $subscription_spending = (float)$stmt->fetchColumn();
}

$pending_request = $latest_subscription && $latest_subscription['status'] === 'pending';
$price = getSubscriptionPrice($user['role']);
$dashboard = $user['role'] === 'mentor' ? 'mentor/dashboard.php' : 'mentee/dashboard.php';
$mobile_money_number = getSystemSetting('mobile_money_number', MOMO_PHONE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#123c43">
    <title>Subscription | MentorMatch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/mobile.css" rel="stylesheet">
</head>
<body>
    <?php
    if ($user['role'] === 'mentor') {
        include __DIR__ . '/includes/mentor_nav.php';
    } else {
        include __DIR__ . '/includes/mentee_nav.php';
    }
    ?>
    <main class="container py-4 py-md-5" style="max-width: 860px;">
        <a href="<?php echo BASE_URL . htmlspecialchars($dashboard, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-link px-0 mb-3">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> Back to dashboard
        </a>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <span class="badge rounded-pill text-bg-light mb-3"><?php echo htmlspecialchars(ucfirst($user['role']), ENT_QUOTES, 'UTF-8'); ?> plan</span>
                <h1 class="h2">Monthly MentorMatch subscription</h1>
                <?php if ($account_active): ?>
                    <p class="text-muted">Subscription payments are made separately. There is no online payment processing in the system. An administrator activates access after manually confirming your payment.</p>
                <?php endif; ?>

                <?php if ($error): ?><div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success" role="status"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
                <?php if (!$account_active): ?>
                    <div class="alert alert-warning" role="status">
                        <?php if ($user['status'] === 'pending'): ?>
                            Your account is awaiting administrator approval. You can request subscription confirmation after it is activated.
                        <?php else: ?>
                            Your account is <strong><?php echo htmlspecialchars($user['status'], ENT_QUOTES, 'UTF-8'); ?></strong>. Contact an administrator for help before requesting subscription confirmation.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="p-3 p-md-4 mb-4 bg-light rounded-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <span class="text-muted">Monthly price</span>
                            <div class="display-6 fw-bold">ZMW <?php echo number_format($price, 2); ?></div>
                        </div>
                        <?php if (!$account_active): ?>
                            <span class="badge text-bg-warning fs-6"><?php echo $user['status'] === 'pending' ? 'Account approval required' : 'Account inactive'; ?></span>
                        <?php elseif ($active_subscription): ?>
                            <span class="badge text-bg-success fs-6">Active</span>
                        <?php elseif ($pending_request): ?>
                            <span class="badge text-bg-warning fs-6">Awaiting confirmation</span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary fs-6">Inactive</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($active_subscription): ?>
                        <p class="mb-0 mt-3">Active until <strong><?php echo date('j M Y, g:i a', strtotime($active_subscription['expires_at'])); ?></strong>.</p>
                    <?php elseif ($pending_request): ?>
                        <p class="mb-0 mt-3">Requested <?php echo date('j M Y', strtotime($latest_subscription['requested_at'])); ?>. Features will become available as soon as an administrator confirms payment.</p>
                    <?php elseif ($latest_subscription && $latest_subscription['status'] === 'rejected'): ?>
                        <p class="mb-0 mt-3">Your last request was not approved. Contact the administrator if you believe this is incorrect.</p>
                    <?php elseif ($latest_subscription && $latest_subscription['status'] === 'active' && $latest_subscription['expires_at']): ?>
                        <p class="mb-0 mt-3">Your subscription expired on <?php echo date('j M Y, g:i a', strtotime($latest_subscription['expires_at'])); ?>. Renew to restore access to subscription features.</p>
                    <?php endif; ?>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <div>
                                <h2 class="h5 mb-1">Subscription payment history</h2>
                                <p class="text-muted small mb-0">Confirmed payments count as your recorded subscription spending; they do not add to or deduct from a wallet balance.</p>
                            </div>
                            <strong><?php echo formatCurrency($subscription_spending); ?> total confirmed</strong>
                        </div>
                        <?php if ($subscription_history): ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead><tr><th>Amount</th><th>Status</th><th>Confirmed</th><th>Access period</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($subscription_history as $payment): ?>
                                            <?php
                                            $payment_status = $payment['approved_at'] && $payment['expires_at'] && strtotime($payment['expires_at']) <= time()
                                                ? 'expired'
                                                : ($payment['approved_at'] ? 'confirmed' : $payment['status']);
                                            ?>
                                            <tr>
                                                <td><?php echo formatCurrency($payment['amount']); ?></td>
                                                <td><?php echo htmlspecialchars(ucfirst($payment_status), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo $payment['approved_at'] ? date('j M Y', strtotime($payment['approved_at'])) : '—'; ?></td>
                                                <td><?php echo $payment['approved_at'] && $payment['expires_at'] ? date('j M Y', strtotime($payment['approved_at'])) . ' – ' . date('j M Y', strtotime($payment['expires_at'])) : '—'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">No subscription payments have been confirmed yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card border-0 bg-light mb-4">
                    <div class="card-body">
                        <h2 class="h5"><i class="bi bi-phone me-1" aria-hidden="true"></i> Pay by mobile money</h2>
                        <?php if ($mobile_money_number !== ''): ?>
                            <p class="mb-2">Send <strong>ZMW <?php echo number_format($price, 2); ?></strong> to:</p>
                            <p class="fs-4 fw-bold text-primary mb-2"><a class="text-decoration-none" href="tel:<?php echo htmlspecialchars(preg_replace('/[^+0-9]/', '', $mobile_money_number), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($mobile_money_number, ENT_QUOTES, 'UTF-8'); ?></a></p>
                            <p class="mb-0">Use your MentorMatch user reference <strong class="user-payment-reference"><?php echo htmlspecialchars(getUserReference($user['id']), ENT_QUOTES, 'UTF-8'); ?></strong> as the payment reference. <?php if ($account_active): ?>After paying, request confirmation below; an administrator will verify the payment and activate your features.<?php else: ?>You may pay now. Keep this reference and request payment confirmation after your account is activated.<?php endif; ?></p>
                        <?php else: ?>
                            <p class="mb-0">The administrator has not added a payment number yet. Your user reference is <strong class="user-payment-reference"><?php echo htmlspecialchars(getUserReference($user['id']), ENT_QUOTES, 'UTF-8'); ?></strong>. Please contact the administrator for the payment number. <?php if ($account_active): ?>You can request payment confirmation after paying.<?php else: ?>You can request payment confirmation after your account is activated.<?php endif; ?></p>
                        <?php endif; ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-3" onclick="copyPaymentReference(this)">
                            <i class="bi bi-clipboard me-1" aria-hidden="true"></i> Copy payment reference
                        </button>
                    </div>
                </div>

                <?php if ($account_active): ?>
                <h2 class="h5">What an active subscription unlocks</h2>
                <ul class="mb-4">
                    <?php if ($user['role'] === 'mentee'): ?>
                        <li>Search and browse available mentors.</li>
                        <li>Request mentorship sessions.</li>
                        <li>Use the local AI study assistant.</li>
                    <?php else: ?>
                        <li>Show your mentor profile in mentee search results.</li>
                        <li>Use the local AI mentor coach.</li>
                        <li>Your private ratings are not shown to you.</li>
                    <?php endif; ?>
                </ul>
                <p class="small text-muted">Access begins when an administrator confirms payment and ends automatically one calendar month after that confirmation. After expiry, the subscription features are disabled until the next payment is confirmed.</p>

                <?php if (!$active_subscription && !$pending_request): ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" name="request_confirmation" value="1" class="btn btn-primary btn-lg">
                            <i class="bi bi-check2-circle me-1" aria-hidden="true"></i> I have paid — request admin confirmation
                        </button>
                    </form>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($active_subscription): ?>
            <div class="mt-4"><a href="<?php echo BASE_URL . htmlspecialchars($dashboard, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary">Return to dashboard</a></div>
        <?php endif; ?>
    </main>
<script>
function copyPaymentReference(button) {
    const reference = document.querySelector('.user-payment-reference').textContent.trim();
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(reference).then(() => {
            button.textContent = 'Reference copied';
        }).catch(() => {
            window.prompt('Copy your payment reference:', reference);
        });
        return;
    }
    window.prompt('Copy your payment reference:', reference);
}
</script>
</body>
</html>
