<?php
// includes/admin_sidebar.php
// Admin Sidebar Navigation
?>
<div class="list-group">
    <a href="dashboard.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
        <i class="bi bi-grid"></i> Dashboard
    </a>
    <a href="users.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : ''; ?>">
        <i class="bi bi-people"></i> Users
        <?php 
        // Get pending users count
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending' AND role != 'admin'");
        $pending_users = $stmt->fetchColumn();
        if ($pending_users > 0): ?>
            <span class="badge bg-warning text-dark rounded-pill float-end"><?php echo $pending_users; ?></span>
        <?php endif; ?>
    </a>
    <a href="subscriptions.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'subscriptions.php' ? 'active' : ''; ?>">
        <i class="bi bi-calendar-check"></i> Subscriptions
        <?php
        $stmt = $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'pending'");
        $pending_subscriptions = $stmt->fetchColumn();
        if ($pending_subscriptions > 0): ?>
            <span class="badge bg-warning text-dark rounded-pill float-end"><?php echo $pending_subscriptions; ?></span>
        <?php endif; ?>
    </a>
    <a href="applications.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'applications.php' ? 'active' : ''; ?>">
        <i class="bi bi-file-earmark-check"></i> Applications
        <?php 
        // Get pending applications count
        $stmt = $pdo->query("SELECT COUNT(*) FROM mentor_applications WHERE status = 'pending'");
        $pending_apps = $stmt->fetchColumn();
        if ($pending_apps > 0): ?>
            <span class="badge bg-danger rounded-pill float-end"><?php echo $pending_apps; ?></span>
        <?php endif; ?>
    </a>
    <a href="courses.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'courses.php' ? 'active' : ''; ?>">
        <i class="bi bi-book"></i> Course Enrollments
        <?php 
        // Get pending courses count
        $stmt = $pdo->query("SELECT COUNT(*) FROM mentee_enrollments WHERE status = 'pending'");
        $pending_courses = $stmt->fetchColumn();
        if ($pending_courses > 0): ?>
            <span class="badge bg-info rounded-pill float-end"><?php echo $pending_courses; ?></span>
        <?php endif; ?>
    </a>
    <a href="semesters.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'semesters.php' ? 'active' : ''; ?>">
        <i class="bi bi-calendar-range"></i> Semesters
    </a>
    <a href="sessions.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'sessions.php' ? 'active' : ''; ?>">
        <i class="bi bi-calendar-check"></i> Sessions
    </a>
    <a href="payments.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'payments.php' ? 'active' : ''; ?>">
        <i class="bi bi-currency-dollar"></i> Payments
        <?php 
        // Get pending payments count
        $stmt = $pdo->query("SELECT COUNT(*) FROM payments WHERE payment_status = 'pending'");
        $pending_payments = $stmt->fetchColumn();
        if ($pending_payments > 0): ?>
            <span class="badge bg-warning text-dark rounded-pill float-end"><?php echo $pending_payments; ?></span>
        <?php endif; ?>
    </a>
    <a href="payouts.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'payouts.php' ? 'active' : ''; ?>">
        <i class="bi bi-cash-stack"></i> Payouts
        <?php 
        // Get pending payouts count
        $stmt = $pdo->query("SELECT COUNT(*) FROM sessions WHERE payment_status = 'paid_to_admin' AND status = 'completed'");
        $pending_payouts = $stmt->fetchColumn();
        if ($pending_payouts > 0): ?>
            <span class="badge bg-success rounded-pill float-end"><?php echo $pending_payouts; ?></span>
        <?php endif; ?>
    </a>
    <a href="feedback.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'feedback.php' ? 'active' : ''; ?>">
        <i class="bi bi-star"></i> Feedback
    </a>
    <a href="certificates.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'certificates.php' ? 'active' : ''; ?>">
        <i class="bi bi-award"></i> Certificates
    </a>
    <a href="reports.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : ''; ?>">
        <i class="bi bi-file-earmark-text"></i> Reports
    </a>
    <a href="logs.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'logs.php' ? 'active' : ''; ?>">
        <i class="bi bi-clipboard-data"></i> System Logs
    </a>
    <a href="settings.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>">
        <i class="bi bi-gear"></i> Settings
    </a>
</div>

<!-- Mobile Bottom Navigation -->
<style>
    @media (max-width: 767.98px) {
        .list-group {
            display: flex;
            flex-direction: row;
            flex-wrap: nowrap;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            gap: 6px;
            padding: 4px 0 12px 0;
            margin-bottom: 0;
            scrollbar-width: none;
        }
        .list-group::-webkit-scrollbar {
            display: none;
        }
        .list-group-item {
            flex: 0 0 auto;
            border: none;
            border-radius: 50px !important;
            padding: 8px 16px;
            font-size: 0.8rem;
            white-space: nowrap;
            background: #f8f9fa;
            margin-right: 4px;
            min-height: 40px;
        }
        .list-group-item i {
            margin-right: 4px;
        }
        .list-group-item.active {
            background: #0d6efd;
            color: white;
        }
        .list-group-item .badge {
            margin-left: 4px;
            position: relative;
            top: -1px;
        }
    }
</style>