<?php
// includes/footer.php - Mobile-optimized footer with bottom nav
?>
    <!-- Mobile Bottom Navigation -->
    <?php if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])): ?>
    <nav class="bottom-nav d-md-none">
        <?php if ($_SESSION['user_role'] === 'admin'): ?>
            <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="bi bi-grid"></i>
                <span>Home</span>
            </a>
            <a href="<?php echo BASE_URL; ?>admin/applications.php" class="nav-item position-relative">
                <i class="bi bi-file-earmark-check"></i>
                <span>Applications</span>
                <?php 
                $stmt = $pdo->query("SELECT COUNT(*) FROM mentor_applications WHERE status = 'pending'");
                $pending_count = $stmt->fetchColumn();
                if ($pending_count > 0): ?>
                    <span class="badge bg-danger"><?php echo $pending_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo BASE_URL; ?>admin/users.php" class="nav-item">
                <i class="bi bi-people"></i>
                <span>Users</span>
            </a>
            <a href="<?php echo BASE_URL; ?>admin/payments.php" class="nav-item">
                <i class="bi bi-credit-card"></i>
                <span>Payments</span>
            </a>
            <a href="<?php echo BASE_URL; ?>admin/settings.php" class="nav-item">
                <i class="bi bi-gear"></i>
                <span>Settings</span>
            </a>
            
        <?php elseif ($_SESSION['user_role'] === 'mentor'): ?>
            <a href="<?php echo BASE_URL; ?>mentor/dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="bi bi-grid"></i>
                <span>Home</span>
            </a>
            <a href="<?php echo BASE_URL; ?>mentor/sessions.php" class="nav-item position-relative">
                <i class="bi bi-calendar-check"></i>
                <span>Sessions</span>
                <?php 
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE mentor_id = ? AND status IN ('pending', 'confirmed')");
                $stmt->execute([$_SESSION['user_id']]);
                $pending_sessions = $stmt->fetchColumn();
                if ($pending_sessions > 0): ?>
                    <span class="badge bg-danger"><?php echo $pending_sessions; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo BASE_URL; ?>mentor/availability.php" class="nav-item">
                <i class="bi bi-clock"></i>
                <span>Availability</span>
            </a>
            <a href="<?php echo BASE_URL; ?>mentor/earnings.php" class="nav-item">
                <i class="bi bi-wallet"></i>
                <span>Earnings</span>
            </a>
            <a href="<?php echo BASE_URL; ?>mentor/profile.php" class="nav-item">
                <i class="bi bi-person"></i>
                <span>Profile</span>
            </a>
            
        <?php elseif ($_SESSION['user_role'] === 'mentee'): ?>
            <a href="<?php echo BASE_URL; ?>mentee/dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="bi bi-grid"></i>
                <span>Home</span>
            </a>
            <a href="<?php echo BASE_URL; ?>mentee/search.php" class="nav-item">
                <i class="bi bi-search"></i>
                <span>Find</span>
            </a>
            <a href="<?php echo BASE_URL; ?>mentee/sessions.php" class="nav-item position-relative">
                <i class="bi bi-calendar-check"></i>
                <span>Sessions</span>
                <?php 
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE mentee_id = ? AND payment_status = 'pending'");
                $stmt->execute([$_SESSION['user_id']]);
                $pending_payments = $stmt->fetchColumn();
                if ($pending_payments > 0): ?>
                    <span class="badge bg-danger"><?php echo $pending_payments; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo BASE_URL; ?>mentee/payments.php" class="nav-item">
                <i class="bi bi-credit-card"></i>
                <span>Payments</span>
            </a>
            <a href="<?php echo BASE_URL; ?>mentee/profile.php" class="nav-item">
                <i class="bi bi-person"></i>
                <span>Profile</span>
            </a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>

    <!-- Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 9999;">
        <!-- Toasts will be dynamically added here -->
    </div>

    <!-- Bootstrap Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Scripts -->
    <script src="<?php echo BASE_URL; ?>assets/js/mobile.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/app.js"></script>
</body>
</html>