<?php
require_once dirname(__DIR__, 2) . '/backend/includes/functions.php';
requireAdmin();

$admin = getCurrentUser();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your action could not be verified. Reload the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'confirm_reference') {
        $reference = strtoupper(trim($_POST['user_reference'] ?? ''));
        if (!preg_match('/^MM-[0-9]{8,}$/', $reference)) {
            $error = 'Enter a valid user reference, for example MM-00000042.';
        } else {
            try {
                $pdo->beginTransaction();
                $userLookup = $pdo->prepare("
                    SELECT id, name, role, status
                    FROM users
                    WHERE CONCAT('MM-', LPAD(id::text, 8, '0')) = ?
                    FOR UPDATE
                ");
                $userLookup->execute([$reference]);
                $paymentUser = $userLookup->fetch();
                if (!$paymentUser || !in_array($paymentUser['role'], ['mentor', 'mentee'], true)) {
                    throw new RuntimeException('No mentor or mentee account matches that reference.');
                }
                if ($paymentUser['status'] !== 'active') {
                        throw new RuntimeException('Approve and activate this user account before confirming a subscription.');
                }

                $activeLookup = $pdo->prepare("
                    SELECT id FROM subscriptions
                    WHERE user_id = ? AND status = 'active' AND expires_at > NOW()
                    LIMIT 1 FOR UPDATE
                ");
                $activeLookup->execute([$paymentUser['id']]);
                if ($activeLookup->fetch()) {
                    throw new RuntimeException('This user already has an active subscription.');
                }

                $pendingLookup = $pdo->prepare("
                    SELECT id FROM subscriptions
                    WHERE user_id = ? AND status = 'pending'
                    ORDER BY requested_at DESC
                    LIMIT 1 FOR UPDATE
                ");
                $pendingLookup->execute([$paymentUser['id']]);
                $pendingSubscriptionId = $pendingLookup->fetchColumn();

                if ($pendingSubscriptionId) {
                    $activate = $pdo->prepare("
                        UPDATE subscriptions
                        SET status = 'active', approved_at = NOW(),
                            expires_at = NOW() + INTERVAL '1 month',
                            reviewed_by = ?, reviewed_at = NOW()
                        WHERE id = ?
                    ");
                    $activate->execute([$admin['id'], $pendingSubscriptionId]);
                } else {
                    $amount = getSubscriptionPrice($paymentUser['role']);
                    $activate = $pdo->prepare("
                        INSERT INTO subscriptions
                            (user_id, amount, status, approved_at, expires_at, reviewed_by, reviewed_at)
                        VALUES (?, ?, 'active', NOW(), NOW() + INTERVAL '1 month', ?, NOW())
                    ");
                    $activate->execute([$paymentUser['id'], $amount, $admin['id']]);
                }
                $closeOtherRequests = $pdo->prepare("
                    UPDATE subscriptions
                    SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW()
                    WHERE user_id = ? AND status = 'pending'
                ");
                $closeOtherRequests->execute([$admin['id'], $paymentUser['id']]);
                $pdo->commit();
                logActivity($admin['id'], 'Confirmed subscription payment for ' . $reference);
                $success = 'Payment confirmed for ' . $paymentUser['name'] . ' (' . $reference . '). Features are active for one month.';
            } catch (RuntimeException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $exception->getMessage();
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Subscription payment confirmation failed: ' . $exception->getMessage());
                $error = 'The payment could not be confirmed. Please try again.';
            }
        }
    } else {
        $subscription_id = (int)($_POST['subscription_id'] ?? 0);
        $action = $_POST['action'] ?? '';
        if ($subscription_id < 1 || !in_array($action, ['approve', 'reject'], true)) {
            $error = 'Choose a valid pending subscription request.';
        } else {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("
                    SELECT s.id, s.user_id, u.name, u.role, u.status
                    FROM subscriptions s
                    JOIN users u ON u.id = s.user_id
                    WHERE s.id = ? AND s.status = 'pending'
                    FOR UPDATE
                ");
                $stmt->execute([$subscription_id]);
                $request = $stmt->fetch();
                if (!$request || !in_array($request['role'], ['mentor', 'mentee'], true)) {
                    throw new RuntimeException('This request is no longer pending or is invalid.');
                }
                if ($action === 'approve' && $request['status'] !== 'active') {
                    throw new RuntimeException('Only active user accounts can have a subscription activated.');
                }
                if ($action === 'approve') {
                    $activeLookup = $pdo->prepare("
                        SELECT id FROM subscriptions
                        WHERE user_id = ? AND status = 'active' AND expires_at > NOW() AND id != ?
                        LIMIT 1 FOR UPDATE
                    ");
                    $activeLookup->execute([$request['user_id'], $subscription_id]);
                    if ($activeLookup->fetch()) {
                        throw new RuntimeException('This user already has an active subscription.');
                    }
                }

                if ($action === 'approve') {
                    $update = $pdo->prepare("
                        UPDATE subscriptions
                        SET status = 'active', approved_at = NOW(),
                            expires_at = NOW() + INTERVAL '1 month',
                            reviewed_by = ?, reviewed_at = NOW()
                        WHERE id = ? AND status = 'pending'
                    ");
                    $success = 'Subscription confirmed for ' . $request['name'] . '. Access is active for one month.';
                } else {
                    $update = $pdo->prepare("
                        UPDATE subscriptions
                        SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW()
                        WHERE id = ? AND status = 'pending'
                    ");
                    $success = 'Subscription request declined for ' . $request['name'] . '.';
                }
                $update->execute([$admin['id'], $subscription_id]);
                if ($update->rowCount() !== 1) {
                    throw new RuntimeException('This request was already reviewed. Refresh the list.');
                }
                $pdo->commit();
                logActivity($admin['id'], ucfirst($action) . ' subscription request #' . $subscription_id);
            } catch (RuntimeException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $exception->getMessage();
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Subscription review failed: ' . $exception->getMessage());
                $error = 'The request could not be reviewed. Please try again.';
            }
        }
    }
}

$stmt = $pdo->query("
    SELECT s.*, u.name, u.email, u.role, u.status AS user_status,
           CONCAT('MM-', LPAD(u.id::text, 8, '0')) AS user_reference,
           (SELECT COALESCE(SUM(confirmed.amount), 0) FROM subscriptions confirmed
            WHERE confirmed.user_id = u.id AND confirmed.approved_at IS NOT NULL) AS user_subscription_spending,
           reviewer.name AS reviewer_name,
           CASE
               WHEN s.status = 'active' AND (s.expires_at IS NULL OR s.expires_at <= NOW()) THEN 'expired'
               ELSE s.status
           END AS display_status
    FROM subscriptions s
    JOIN users u ON u.id = s.user_id
    LEFT JOIN users reviewer ON reviewer.id = s.reviewed_by
    ORDER BY (s.status = 'pending') DESC, s.requested_at DESC
");
$subscriptions = $stmt->fetchAll();
$stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM subscriptions WHERE approved_at IS NOT NULL");
$confirmed_subscription_revenue = (float)$stmt->fetchColumn();
$pending_count = count(array_filter($subscriptions, function ($subscription) {
    return $subscription['status'] === 'pending';
}));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Subscriptions | MentorMatch Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/mobile.css" rel="stylesheet">
</head>
<body>
    <?php include dirname(__DIR__, 2) . '/frontend/includes/admin_nav.php'; ?>
    <div class="container-fluid mt-4">
        <div class="row">
            <aside class="col-md-3 col-lg-2 mb-3"><?php include dirname(__DIR__, 2) . '/frontend/includes/admin_sidebar.php'; ?></aside>
            <main class="col-md-9 col-lg-10">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                    <div><h1 class="h3 mb-1">Monthly subscriptions</h1><p class="text-muted mb-0">Review offline payments and activate one-month access.</p></div>
                    <span class="badge text-bg-warning fs-6"><?php echo $pending_count; ?> awaiting review</span>
                </div>
                <?php if ($error): ?><div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success" role="status"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
                <div class="alert alert-info"><i class="bi bi-info-circle me-1" aria-hidden="true"></i> Confirm payment outside the system before approving. The system does not process subscription payments.</div>
                <div class="card shadow-sm mb-4">
                    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div><h2 class="h6 mb-1">Confirmed subscription revenue</h2><p class="text-muted small mb-0">Payments recorded after admin confirmation; separate from mentoring-session earnings and wallet balances.</p></div>
                        <strong class="fs-4"><?php echo formatCurrency($confirmed_subscription_revenue); ?></strong>
                    </div>
                </div>
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h2 class="h5 mb-1">Confirm payment by user reference</h2>
                        <p class="text-muted">Use the reference the user provided when paying. Approve their account first if it is still pending. This can activate a subscription even if the user did not submit a request.</p>
                        <form method="post" class="row g-2 align-items-end">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="confirm_reference">
                            <div class="col-sm-8 col-md-6">
                                <label for="user_reference" class="form-label">User reference</label>
                                <input class="form-control" id="user_reference" name="user_reference" placeholder="MM-00000042" pattern="MM-[0-9]{8,}" required>
                            </div>
                            <div class="col-sm-auto">
                                <button type="submit" class="btn btn-primary" onclick="return confirm('Confirm this user’s offline payment and activate subscription access for one month?')">
                                    <i class="bi bi-check-circle me-1" aria-hidden="true"></i> Confirm payment
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card shadow-sm">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead><tr><th>User / reference</th><th>Role</th><th>Account</th><th>Price</th><th>Subscription</th><th>Requested</th><th>Access period</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php if (!$subscriptions): ?>
                                    <tr><td colspan="8" class="text-center text-muted py-4">No subscription requests yet.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($subscriptions as $subscription): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($subscription['name'], ENT_QUOTES, 'UTF-8'); ?></strong><br><small><?php echo htmlspecialchars($subscription['email'], ENT_QUOTES, 'UTF-8'); ?></small><br><span class="badge text-bg-light"><?php echo htmlspecialchars($subscription['user_reference'], ENT_QUOTES, 'UTF-8'); ?></span><br><small class="text-muted">Lifetime confirmed subscriptions: <?php echo formatCurrency($subscription['user_subscription_spending']); ?></small></td>
                                        <td><?php echo htmlspecialchars(ucfirst($subscription['role']), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><span class="badge text-bg-<?php echo $subscription['user_status'] === 'active' ? 'success' : 'warning'; ?>"><?php echo htmlspecialchars(ucfirst($subscription['user_status']), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td>ZMW <?php echo number_format((float)$subscription['amount'], 2); ?></td>
                                        <td><span class="badge text-bg-<?php echo $subscription['display_status'] === 'active' ? 'success' : ($subscription['display_status'] === 'pending' ? 'warning' : 'secondary'); ?>"><?php echo htmlspecialchars(ucfirst($subscription['display_status']), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td><?php echo date('j M Y', strtotime($subscription['requested_at'])); ?></td>
                                        <td><?php echo $subscription['approved_at'] ? date('j M Y', strtotime($subscription['approved_at'])) . ' – ' . date('j M Y', strtotime($subscription['expires_at'])) : '—'; ?></td>
                                        <td>
                                            <?php if ($subscription['status'] === 'pending' && $subscription['user_status'] === 'active'): ?>
                                                <form method="post" class="d-flex flex-wrap gap-1">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="subscription_id" value="<?php echo (int)$subscription['id']; ?>">
                                                    <button name="action" value="approve" class="btn btn-sm btn-success" onclick="return confirm('Confirm the offline payment and activate for one month?')">Confirm payment</button>
                                                    <button name="action" value="reject" class="btn btn-sm btn-outline-danger" onclick="return confirm('Decline this subscription request?')">Decline</button>
                                                </form>
                                            <?php elseif ($subscription['status'] === 'pending'): ?>
                                                <a class="btn btn-sm btn-outline-primary" href="users.php?role=<?php echo rawurlencode($subscription['role']); ?>&amp;status=pending">Approve account first</a>
                                            <?php else: ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($subscription['reviewer_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
