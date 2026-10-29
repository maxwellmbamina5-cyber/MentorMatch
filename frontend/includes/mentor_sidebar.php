<?php
// includes/mentor_sidebar.php
?>
<div class="list-group">
    <a href="dashboard.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
        <i class="bi bi-grid"></i> Dashboard
    </a>
    <a href="sessions.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'sessions.php' ? 'active' : ''; ?>">
        <i class="bi bi-calendar-check"></i> My Sessions
    </a>
    <a href="availability.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'availability.php' ? 'active' : ''; ?>">
        <i class="bi bi-clock"></i> Availability
    </a>
    <a href="../subscription.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'subscription.php' ? 'active' : ''; ?>">
        <i class="bi bi-card-checklist"></i> Subscription
    </a>
    <a href="rates.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'rates.php' ? 'active' : ''; ?>">
        <i class="bi bi-currency-dollar"></i> My Rates
    </a>
    <a href="earnings.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'earnings.php' ? 'active' : ''; ?>">
        <i class="bi bi-wallet"></i> Earnings
    </a>
    <a href="history.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'history.php' ? 'active' : ''; ?>">
        <i class="bi bi-clock-history"></i> History
    </a>
    <a href="certificate.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'certificate.php' ? 'active' : ''; ?>">
        <i class="bi bi-award"></i> Certificate
    </a>
    <a href="profile.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : ''; ?>">
        <i class="bi bi-person"></i> Profile
    </a>
</div>