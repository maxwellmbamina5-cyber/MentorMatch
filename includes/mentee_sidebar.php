<?php
// includes/mentee_sidebar.php
// Mentee Sidebar Navigation
?>
<div class="list-group">
    <a href="dashboard.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
        <i class="bi bi-grid"></i> Dashboard
    </a>
    <a href="search.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'search.php' ? 'active' : ''; ?>">
        <i class="bi bi-search"></i> Find a Mentor
    </a>
    <a href="../subscription.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'subscription.php' ? 'active' : ''; ?>">
        <i class="bi bi-card-checklist"></i> Subscription
    </a>
    <a href="courses.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'courses.php' ? 'active' : ''; ?>">
        <i class="bi bi-book"></i> My Courses
        <?php 
        // Get pending courses count for badge
        if (isset($_SESSION['user_id'])) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM mentee_enrollments WHERE mentee_id = ? AND status = 'pending'");
            $stmt->execute([$_SESSION['user_id']]);
            $pending_courses = $stmt->fetchColumn();
            if ($pending_courses > 0): ?>
                <span class="badge bg-warning text-dark rounded-pill float-end"><?php echo $pending_courses; ?></span>
            <?php endif; 
        } ?>
    </a>
    <a href="book.php?mentor_id=<?php echo isset($_GET['mentor_id']) ? (int)$_GET['mentor_id'] : 0; ?>&course=<?php echo isset($_GET['course']) ? urlencode($_GET['course']) : ''; ?>" 
       class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'book.php' ? 'active' : ''; ?>">
        <i class="bi bi-calendar-plus"></i> Book Session
    </a>
    <a href="sessions.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'sessions.php' ? 'active' : ''; ?>">
        <i class="bi bi-calendar-check"></i> My Sessions
        <?php 
        // Get pending sessions count for badge
        if (isset($_SESSION['user_id'])) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE mentee_id = ? AND payment_status = 'pending' AND status IN ('pending', 'confirmed')");
            $stmt->execute([$_SESSION['user_id']]);
            $pending_count = $stmt->fetchColumn();
            if ($pending_count > 0): ?>
                <span class="badge bg-danger rounded-pill float-end"><?php echo $pending_count; ?></span>
            <?php endif; 
        } ?>
    </a>
    <a href="payments.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'payments.php' ? 'active' : ''; ?>">
        <i class="bi bi-credit-card"></i> Payments
        <?php 
        // Get pending payments count
        if (isset($_SESSION['user_id'])) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE mentee_id = ? AND payment_status = 'pending'");
            $stmt->execute([$_SESSION['user_id']]);
            $pending_payments = $stmt->fetchColumn();
            if ($pending_payments > 0): ?>
                <span class="badge bg-warning text-dark rounded-pill float-end"><?php echo $pending_payments; ?></span>
            <?php endif; 
        } ?>
    </a>
    <a href="history.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'history.php' ? 'active' : ''; ?>">
        <i class="bi bi-clock-history"></i> History
    </a>
    <a href="feedback.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'feedback.php' ? 'active' : ''; ?>">
        <i class="bi bi-star"></i> My Feedback
    </a>
    <a href="profile.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : ''; ?>">
        <i class="bi bi-person"></i> Profile
    </a>
    <hr class="my-2">
    <a href="../logout.php" class="list-group-item list-group-item-action text-danger" onclick="return confirm('Are you sure you want to logout?')">
        <i class="bi bi-box-arrow-right"></i> Logout
    </a>
</div>

<!-- Mobile Bottom Navigation (for small screens) -->
<?php if (basename($_SERVER['PHP_SELF']) !== 'dashboard.php'): ?>
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
        hr {
            display: none;
        }
        .list-group-item.text-danger {
            color: #dc3545 !important;
        }
        .list-group-item.text-danger.active {
            background: #dc3545;
            color: white !important;
        }
    }
</style>
<?php endif; ?>