<?php
// admin/dashboard.php - Complete Admin Dashboard
require_once dirname(__DIR__, 2) . '/backend/includes/functions.php';
requireAdmin();

$user = getCurrentUser();

// Get active semester
$stmt = $pdo->query("SELECT * FROM semesters WHERE status = 'active' ORDER BY id DESC LIMIT 1");
$active_semester = $stmt->fetch();

// ==================== STATISTICS ====================

// Total Users
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role != 'admin'");
$total_users = $stmt->fetchColumn();

// Total Mentors
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'mentor' AND status = 'active'");
$total_mentors = $stmt->fetchColumn();

// Total Mentees
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'mentee' AND status = 'active'");
$total_mentees = $stmt->fetchColumn();

// Pending Applications
$stmt = $pdo->query("SELECT COUNT(*) as total FROM mentor_applications WHERE status = 'pending'");
$pending_mentors = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total FROM mentee_enrollments WHERE status = 'pending'");
$pending_mentees = $stmt->fetchColumn();
$total_pending = $pending_mentors + $pending_mentees;

// Pending Courses (Mentee Enrollments)
$stmt = $pdo->query("SELECT COUNT(*) as total FROM mentee_enrollments WHERE status = 'pending'");
$pending_courses = $stmt->fetchColumn();

// Orphan Users
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM users u
    WHERE u.role = 'mentor' 
      AND u.status = 'pending'
      AND NOT EXISTS (SELECT 1 FROM mentor_applications ma WHERE ma.mentor_id = u.id)
");
$stmt->execute();
$orphan_mentors = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM users u
    WHERE u.role = 'mentee' 
      AND u.status = 'pending'
      AND NOT EXISTS (SELECT 1 FROM mentee_enrollments me WHERE me.mentee_id = u.id)
");
$stmt->execute();
$orphan_mentees = $stmt->fetchColumn();
$total_orphans = $orphan_mentors + $orphan_mentees;

// Total Sessions
$stmt = $pdo->query("SELECT COUNT(*) as total FROM sessions");
$total_sessions = $stmt->fetchColumn();

// Completed Sessions
$stmt = $pdo->query("SELECT COUNT(*) as total FROM sessions WHERE status = 'completed'");
$completed_sessions = $stmt->fetchColumn();

// Pending Sessions
$stmt = $pdo->query("SELECT COUNT(*) as total FROM sessions WHERE status IN ('pending', 'confirmed')");
$pending_sessions = $stmt->fetchColumn();

// Cancelled Sessions
$stmt = $pdo->query("SELECT COUNT(*) as total FROM sessions WHERE status = 'cancelled'");
$cancelled_sessions = $stmt->fetchColumn();

// Total Revenue
$stmt = $pdo->query("SELECT SUM(total_amount) as total FROM sessions WHERE payment_status = 'paid_to_admin'");
$session_revenue = (float)($stmt->fetchColumn() ?? 0);

// Subscription payments are separate admin revenue, not mentor session earnings.
$stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM subscriptions WHERE approved_at IS NOT NULL");
$subscription_revenue = (float)$stmt->fetchColumn();
$total_revenue = $session_revenue + $subscription_revenue;

// Total Service Fees
$stmt = $pdo->query("SELECT SUM(admin_fee) as total FROM sessions WHERE payment_status = 'released_to_mentor'");
$total_fees = $stmt->fetchColumn() ?? 0;

// Total Payouts
$stmt = $pdo->query("SELECT SUM(mentor_payout) as total FROM sessions WHERE payment_status = 'released_to_mentor'");
$total_payouts = $stmt->fetchColumn() ?? 0;

// Total Certificates
$stmt = $pdo->query("SELECT COUNT(*) as total FROM certificates WHERE status = 'issued'");
$total_certificates = $stmt->fetchColumn();

// Average Rating
$stmt = $pdo->query("SELECT AVG(rating) as avg FROM feedback");
$avg_rating = $stmt->fetchColumn() ?? 0;

// Total Mentee Courses
$stmt = $pdo->query("SELECT COUNT(*) as total FROM mentee_enrollments");
$total_courses = $stmt->fetchColumn();

// Courses by Status
$course_status = [];
$stmt = $pdo->query("SELECT status, COUNT(*) as count FROM mentee_enrollments GROUP BY status");
$course_status_raw = $stmt->fetchAll();
foreach ($course_status_raw as $cs) {
    $course_status[$cs['status']] = $cs['count'];
}

// ==================== CHART DATA ====================

// Sessions by Month (Last 6 Months)
$monthly_sessions = [];
$monthly_revenue = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_label = date('M Y', strtotime("-$i months"));
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM sessions WHERE TO_CHAR(created_at, 'YYYY-MM') = ?");
    $stmt->execute([$month]);
    $monthly_sessions[$month_label] = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT SUM(total_amount) as total FROM sessions WHERE TO_CHAR(created_at, 'YYYY-MM') = ? AND payment_status = 'paid_to_admin'");
    $stmt->execute([$month]);
    $monthly_session_revenue = (float)($stmt->fetchColumn() ?? 0);

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM subscriptions WHERE approved_at IS NOT NULL AND TO_CHAR(approved_at, 'YYYY-MM') = ?");
    $stmt->execute([$month]);
    $monthly_subscription_revenue = (float)$stmt->fetchColumn();
    $monthly_revenue[$month_label] = $monthly_session_revenue + $monthly_subscription_revenue;
}

// Sessions by Course (Top 5)
$stmt = $pdo->query("
    SELECT course_code, COUNT(*) as total 
    FROM sessions 
    GROUP BY course_code 
    ORDER BY total DESC LIMIT 5
");
$course_data = $stmt->fetchAll();

// Mentor Performance (Top 5 by sessions)
$stmt = $pdo->query("
    SELECT u.name, COUNT(s.id) as total_sessions, AVG(f.rating) as avg_rating
    FROM users u
    JOIN sessions s ON u.id = s.mentor_id
    LEFT JOIN feedback f ON u.id = f.mentor_id
    WHERE u.role = 'mentor'
    GROUP BY u.id
    ORDER BY total_sessions DESC LIMIT 5
");
$top_mentors = $stmt->fetchAll();

// Payment Status Distribution
$stmt = $pdo->query("
    SELECT payment_status, COUNT(*) as total 
    FROM payments 
    GROUP BY payment_status
");
$payment_status_data = $stmt->fetchAll();

// User Growth (Last 6 Months)
$user_growth = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_label = date('M Y', strtotime("-$i months"));
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role != 'admin' AND TO_CHAR(created_at, 'YYYY-MM') = ?");
    $stmt->execute([$month]);
    $user_growth[$month_label] = (int)$stmt->fetchColumn();
}

// ==================== RECENT DATA ====================

// Recent Sessions
$stmt = $pdo->query("
    SELECT s.*, m.name as mentor_name, u.name as mentee_name
    FROM sessions s
    JOIN users m ON s.mentor_id = m.id
    JOIN users u ON s.mentee_id = u.id
    ORDER BY s.created_at DESC LIMIT 5
");
$recent_sessions = $stmt->fetchAll();

// Recent Users
$stmt = $pdo->query("
    SELECT * FROM users WHERE role != 'admin' 
    ORDER BY created_at DESC LIMIT 5
");
$recent_users = $stmt->fetchAll();

// Recent Payments
$stmt = $pdo->query("
    SELECT p.*, m.name as mentor_name, u.name as mentee_name
    FROM payments p
    JOIN users m ON p.mentor_id = m.id
    JOIN users u ON p.mentee_id = u.id
    ORDER BY p.created_at DESC LIMIT 5
");
$recent_payments = $stmt->fetchAll();

// Recent Course Enrollments
$stmt = $pdo->query("
    SELECT me.*, u.name as mentee_name, u.email as mentee_email
    FROM mentee_enrollments me
    JOIN users u ON me.mentee_id = u.id
    ORDER BY me.created_at DESC LIMIT 5
");
$recent_courses = $stmt->fetchAll();

// Recent System Logs (Latest 5)
$stmt = $pdo->query("
    SELECT l.*, u.name as user_name 
    FROM system_logs l
    LEFT JOIN users u ON l.user_id = u.id
    ORDER BY l.created_at DESC LIMIT 5
");
$recent_logs = $stmt->fetchAll();

// ==================== NOTIFICATIONS ====================

$notifications = [];

// Check for pending applications
if ($total_pending > 0) {
    $notifications[] = [
        'type' => 'warning',
        'icon' => 'bi-file-earmark-check',
        'message' => "$total_pending pending application(s) need your review",
        'link' => 'applications.php'
    ];
}

// Check for pending courses
if ($pending_courses > 0) {
    $notifications[] = [
        'type' => 'info',
        'icon' => 'bi-book',
        'message' => "$pending_courses course enrollment(s) pending approval",
        'link' => 'courses.php'
    ];
}

// Check for orphan users
if ($total_orphans > 0) {
    $notifications[] = [
        'type' => 'danger',
        'icon' => 'bi-exclamation-triangle',
        'message' => "$total_orphans orphan user(s) found without applications! Click to fix.",
        'link' => 'applications.php'
    ];
}

// Check for active semester
if (!$active_semester) {
    $notifications[] = [
        'type' => 'danger',
        'icon' => 'bi-calendar-x',
        'message' => 'No active semester found. Please create a semester.',
        'link' => 'semesters.php'
    ];
}

// Check for sessions pending payment release
$stmt = $pdo->query("SELECT COUNT(*) as total FROM sessions WHERE payment_status = 'paid_to_admin' AND status = 'completed'");
$pending_payouts = $stmt->fetchColumn();
if ($pending_payouts > 0) {
    $notifications[] = [
        'type' => 'info',
        'icon' => 'bi-cash-stack',
        'message' => "$pending_payouts session(s) ready for payout release",
        'link' => 'payouts.php'
    ];
}

// Check for low balance alerts
$stmt = $pdo->query("SELECT SUM(total_amount) as total FROM sessions WHERE payment_status = 'paid_to_admin'");
$session_balance = (float)($stmt->fetchColumn() ?? 0);
$balance = $session_balance + $subscription_revenue;
if ($balance < 100) {
    $notifications[] = [
        'type' => 'warning',
        'icon' => 'bi-exclamation-triangle',
        'message' => 'Low admin balance: ' . formatCurrency($balance),
        'link' => 'payments.php'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MentorMatch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/mobile.css" rel="stylesheet">
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
            font-size: 2.5rem;
            opacity: 0.3;
            position: absolute;
            right: 15px;
            top: 15px;
        }
        .notification-item {
            border-left: 4px solid;
            padding: 12px 16px;
            margin-bottom: 8px;
            background: #f8f9fa;
            border-radius: 8px;
            transition: background 0.3s ease;
        }
        .notification-item:hover {
            background: #e9ecef;
        }
        .notification-item .btn-link {
            text-decoration: none;
            font-weight: 500;
        }
        .dashboard-chart {
            height: 250px;
        }
        .quick-action-btn {
            padding: 18px 10px;
            text-align: center;
            border-radius: 12px;
            transition: all 0.3s ease;
            background: #f8f9fa;
            text-decoration: none;
            color: #212529;
            display: block;
            cursor: pointer;
        }
        .quick-action-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            background: #e9ecef;
        }
        .quick-action-btn i {
            font-size: 2rem;
            display: block;
            margin-bottom: 6px;
        }
        .quick-action-btn .badge {
            font-size: 0.7rem;
            margin-top: 4px;
        }
        .welcome-banner {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: white;
            padding: 25px;
            border-radius: 16px;
            margin-bottom: 25px;
        }
        .welcome-banner h3 {
            margin-bottom: 5px;
        }
        .welcome-banner small {
            opacity: 0.8;
        }
        .progress-stats {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .progress-stats .stat-item {
            flex: 1;
            min-width: 100px;
            padding: 10px 15px;
            background: rgba(255,255,255,0.15);
            border-radius: 8px;
        }
        .stat-item .number {
            font-size: 1.5rem;
            font-weight: 700;
        }
        .stat-item .label {
            font-size: 0.8rem;
            opacity: 0.8;
        }
        .table-row-clickable {
            cursor: pointer;
        }
        .table-row-clickable:hover {
            background-color: rgba(13, 110, 253, 0.05);
        }
        .badge-gold {
            background-color: #ffd700;
            color: #856404;
        }
        .badge-silver {
            background-color: #c0c0c0;
            color: #495057;
        }
        .badge-bronze {
            background-color: #cd7f32;
            color: white;
        }
        .badge-orphan {
            background-color: #ffc107;
            color: #856404;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .stat-card {
            animation: fadeInUp 0.6s ease forwards;
        }
        .stat-card:nth-child(2) { animation-delay: 0.1s; }
        .stat-card:nth-child(3) { animation-delay: 0.2s; }
        .stat-card:nth-child(4) { animation-delay: 0.3s; }
        .stat-card:nth-child(5) { animation-delay: 0.4s; }
        .stat-card:nth-child(6) { animation-delay: 0.5s; }
        @media (max-width: 768px) {
            .welcome-banner .progress-stats { margin-top: 15px; }
            .welcome-banner .text-end { text-align: left !important; }
            .quick-action-btn { padding: 12px; }
            .quick-action-btn i { font-size: 1.5rem; }
            .dashboard-chart { height: 180px !important; }
            .stat-card .stat-icon { font-size: 1.8rem; }
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
                <!-- Welcome Banner -->
                <div class="welcome-banner">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3><i class="bi bi-person-circle"></i> Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</h3>
                            <small>Here's what's happening with your mentorship platform today.</small>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="progress-stats">
                                <div class="stat-item">
                                    <div class="number"><?php echo date('d M Y'); ?></div>
                                    <div class="label">Today</div>
                                </div>
                                <div class="stat-item">
                                    <div class="number"><?php echo $active_semester ? htmlspecialchars($active_semester['semester_name']) : 'N/A'; ?></div>
                                    <div class="label">Active Semester</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Notifications -->
                <?php if (!empty($notifications)): ?>
                    <div class="mb-4">
                        <?php foreach ($notifications as $notification): ?>
                            <div class="notification-item border-<?php echo $notification['type']; ?>">
                                <i class="bi <?php echo $notification['icon']; ?> text-<?php echo $notification['type']; ?>"></i>
                                <?php echo $notification['message']; ?>
                                <a href="<?php echo $notification['link']; ?>" class="btn-link ms-2">View <i class="bi bi-arrow-right"></i></a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Stats Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-xl-4 col-lg-4 col-md-6">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body position-relative">
                                <i class="bi bi-people stat-icon"></i>
                                <h6 class="card-title">Total Users</h6>
                                <h2 class="mb-0"><?php echo $total_users; ?></h2>
                                <small>
                                    <i class="bi bi-person-check"></i> <?php echo $total_mentors; ?> Mentors 
                                    | <i class="bi bi-person"></i> <?php echo $total_mentees; ?> Mentees
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-4 col-lg-4 col-md-6">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body position-relative">
                                <i class="bi bi-calendar-check stat-icon"></i>
                                <h6 class="card-title">Sessions</h6>
                                <h2 class="mb-0"><?php echo $total_sessions; ?></h2>
                                <small>
                                    <i class="bi bi-check-circle"></i> <?php echo $completed_sessions; ?> Completed 
                                    | <i class="bi bi-clock"></i> <?php echo $pending_sessions; ?> Pending
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-4 col-lg-4 col-md-6">
                        <div class="card stat-card bg-warning text-dark">
                            <div class="card-body position-relative">
                                <i class="bi bi-currency-dollar stat-icon"></i>
                                <h6 class="card-title">Revenue</h6>
                                <h2 class="mb-0"><?php echo formatCurrency($total_revenue); ?></h2>
                                <small>
                                    Subscriptions: <?php echo formatCurrency($subscription_revenue); ?>
                                    | Fees: <?php echo formatCurrency($total_fees); ?> 
                                    | <i class="bi bi-arrow-down"></i> Payouts: <?php echo formatCurrency($total_payouts); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-4 col-lg-4 col-md-6">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body position-relative">
                                <i class="bi bi-award stat-icon"></i>
                                <h6 class="card-title">Certificates & Ratings</h6>
                                <h2 class="mb-0"><?php echo $total_certificates; ?></h2>
                                <small>
                                    <i class="bi bi-star-fill"></i> <?php echo number_format($avg_rating, 1); ?> ★ Avg Rating
                                    <br><i class="bi bi-clock"></i> <?php echo $total_pending; ?> Pending Applications
                                    <?php if ($total_orphans > 0): ?>
                                        <br><span class="badge badge-orphan"><i class="bi bi-exclamation-triangle"></i> <?php echo $total_orphans; ?> Orphans</span>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-4 col-lg-4 col-md-6">
                        <div class="card stat-card bg-secondary text-white">
                            <div class="card-body position-relative">
                                <i class="bi bi-book stat-icon"></i>
                                <h6 class="card-title">Mentee Courses</h6>
                                <h2 class="mb-0"><?php echo $total_courses; ?></h2>
                                <small>
                                    <i class="bi bi-clock"></i> <?php echo $course_status['pending'] ?? 0; ?> Pending
                                    | <i class="bi bi-check-circle"></i> <?php echo $course_status['active'] ?? 0; ?> Active
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-4 col-lg-4 col-md-6">
                        <div class="card stat-card bg-danger text-white">
                            <div class="card-body position-relative">
                                <i class="bi bi-exclamation-triangle stat-icon"></i>
                                <h6 class="card-title">Issues</h6>
                                <h2 class="mb-0"><?php echo $cancelled_sessions; ?></h2>
                                <small>
                                    <i class="bi bi-x-circle"></i> <?php echo $cancelled_sessions; ?> Cancelled Sessions
                                    <br><i class="bi bi-clock"></i> <?php echo $total_pending; ?> Pending Approvals
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="row g-3 mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title mb-3"><i class="bi bi-lightning-charge"></i> Quick Actions</h6>
                                <div class="row g-2">
                                    <div class="col-md-2 col-4">
                                        <a href="applications.php" class="quick-action-btn">
                                            <i class="bi bi-file-earmark-check text-warning"></i>
                                            <small>Review Apps</small>
                                            <?php if ($total_pending > 0): ?>
                                                <span class="badge bg-danger d-block"><?php echo $total_pending; ?></span>
                                            <?php endif; ?>
                                            <?php if ($total_orphans > 0): ?>
                                                <span class="badge badge-orphan d-block"><?php echo $total_orphans; ?> Orphans</span>
                                            <?php endif; ?>
                                        </a>
                                    </div>
                                    <div class="col-md-2 col-4">
                                        <a href="users.php" class="quick-action-btn">
                                            <i class="bi bi-people text-primary"></i>
                                            <small>Manage Users</small>
                                        </a>
                                    </div>
                                    <div class="col-md-2 col-4">
                                        <a href="payments.php" class="quick-action-btn">
                                            <i class="bi bi-credit-card text-success"></i>
                                            <small>Payments</small>
                                        </a>
                                    </div>
                                    <div class="col-md-2 col-4">
                                        <a href="payouts.php" class="quick-action-btn">
                                            <i class="bi bi-cash-stack text-info"></i>
                                            <small>Payouts</small>
                                            <?php if ($pending_payouts > 0): ?>
                                                <span class="badge bg-danger d-block"><?php echo $pending_payouts; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </div>
                                    <div class="col-md-2 col-4">
                                        <a href="certificates.php" class="quick-action-btn">
                                            <i class="bi bi-award text-warning"></i>
                                            <small>Certificates</small>
                                        </a>
                                    </div>
                                    <div class="col-md-2 col-4">
                                        <a href="reports.php" class="quick-action-btn">
                                            <i class="bi bi-file-earmark-text text-secondary"></i>
                                            <small>Reports</small>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts -->
                <div class="row g-3 mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-graph-up"></i> Sessions & Revenue Trend</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="trendChart" class="dashboard-chart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-pie-chart"></i> Payment Status</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="paymentChart" class="dashboard-chart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Course & Mentor Performance -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-book"></i> Top Courses</h6>
                            </div>
                            <div class="card-body">
                                <?php if ($course_data): ?>
                                    <canvas id="courseChart" style="height: 200px;"></canvas>
                                    <div class="mt-3">
                                        <?php foreach ($course_data as $course): ?>
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span><?php echo htmlspecialchars($course['course_code']); ?></span>
                                                <span class="badge bg-primary"><?php echo $course['total']; ?> sessions</span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center py-3">No course data available</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-trophy"></i> Top Mentors</h6>
                            </div>
                            <div class="card-body">
                                <?php if ($top_mentors): ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($top_mentors as $index => $mentor): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <span class="badge <?php echo $index === 0 ? 'badge-gold' : ($index === 1 ? 'badge-silver' : 'badge-bronze'); ?>">
                                                        #<?php echo $index + 1; ?>
                                                    </span>
                                                    <?php echo htmlspecialchars($mentor['name']); ?>
                                                </div>
                                                <div>
                                                    <span class="badge bg-primary"><?php echo $mentor['total_sessions']; ?> sessions</span>
                                                    <?php if ($mentor['avg_rating']): ?>
                                                        <span class="badge bg-warning text-dark">
                                                            <i class="bi bi-star-fill"></i> <?php echo number_format($mentor['avg_rating'], 1); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center py-3">No mentor data available</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="bi bi-clock-history"></i> Recent Sessions</h6>
                                <a href="sessions.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <?php if ($recent_sessions): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>#</th>
                                                    <th>Mentor</th>
                                                    <th>Mentee</th>
                                                    <th>Course</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_sessions as $session): ?>
                                                    <tr class="table-row-clickable" onclick="window.location='session_details.php?id=<?php echo $session['id']; ?>'">
                                                        <td>#<?php echo $session['id']; ?></td>
                                                        <td><?php echo htmlspecialchars($session['mentor_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($session['mentee_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($session['course_code']); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php 
                                                                echo $session['status'] === 'completed' ? 'success' : 
                                                                    ($session['status'] === 'confirmed' ? 'primary' : 
                                                                    ($session['status'] === 'pending' ? 'warning' : 'danger')); 
                                                            ?>">
                                                                <?php echo getSessionStatus($session['status']); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center py-3">No recent sessions</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="bi bi-activity"></i> Recent Activity</h6>
                                <a href="logs.php" class="btn btn-sm btn-secondary">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <?php if ($recent_logs): ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($recent_logs as $log): ?>
                                            <div class="list-group-item py-2">
                                                <small class="text-muted float-end"><?php echo date('h:i A', strtotime($log['created_at'])); ?></small>
                                                <div>
                                                    <?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?>
                                                    <span class="text-muted">•</span>
                                                    <?php echo htmlspecialchars($log['action']); ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center py-3">No recent activity</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Payments & Courses -->
                <div class="row g-3 mt-3">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="bi bi-credit-card"></i> Recent Payments</h6>
                                <a href="payments.php" class="btn btn-sm btn-success">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <?php if ($recent_payments): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Ref</th>
                                                    <th>Mentor</th>
                                                    <th>Mentee</th>
                                                    <th>Amount</th>
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_payments as $payment): ?>
                                                    <tr>
                                                        <td><code><?php echo substr($payment['transaction_reference'], -8); ?></code></td>
                                                        <td><?php echo htmlspecialchars($payment['mentor_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($payment['mentee_name']); ?></td>
                                                        <td><?php echo formatCurrency($payment['amount']); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php 
                                                                echo $payment['payment_status'] === 'released' ? 'success' : 
                                                                    ($payment['payment_status'] === 'captured' ? 'info' : 'warning'); 
                                                            ?>">
                                                                <?php echo getPaymentStatus($payment['payment_status']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo date('d M Y', strtotime($payment['created_at'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center py-3">No recent payments</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="bi bi-book"></i> Recent Course Enrollments</h6>
                                <a href="courses.php" class="btn btn-sm btn-info">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <?php if ($recent_courses): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Mentee</th>
                                                    <th>Course</th>
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_courses as $course): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($course['mentee_name']); ?></td>
                                                        <td><strong><?php echo htmlspecialchars($course['course_code']); ?></strong></td>
                                                        <td>
                                                            <span class="badge bg-<?php 
                                                                echo $course['status'] === 'active' ? 'success' : 
                                                                    ($course['status'] === 'pending' ? 'warning' : 'danger'); 
                                                            ?>">
                                                                <?php echo ucfirst($course['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo date('d M Y', strtotime($course['created_at'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center py-3">No recent course enrollments</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // ============ TREND CHART ============
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        const trendLabels = <?php echo json_encode(array_keys($monthly_sessions)); ?>;
        const sessionData = <?php echo json_encode(array_values($monthly_sessions)); ?>;
        const revenueData = <?php echo json_encode(array_values($monthly_revenue)); ?>;
        
        new Chart(trendCtx, {
            type: 'bar',
            data: {
                labels: trendLabels,
                datasets: [
                    {
                        label: 'Sessions',
                        data: sessionData,
                        backgroundColor: 'rgba(13, 110, 253, 0.5)',
                        borderColor: 'rgba(13, 110, 253, 1)',
                        borderWidth: 2,
                        order: 1
                    },
                    {
                        label: 'Revenue (K)',
                        data: revenueData,
                        type: 'line',
                        backgroundColor: 'rgba(40, 167, 69, 0.2)',
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 3,
                        pointBackgroundColor: 'rgba(40, 167, 69, 1)',
                        tension: 0.3,
                        fill: true,
                        order: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // ============ PAYMENT CHART ============
        const paymentCtx = document.getElementById('paymentChart').getContext('2d');
        const paymentLabels = <?php echo json_encode(array_column($payment_status_data, 'payment_status')); ?>;
        const paymentData = <?php echo json_encode(array_column($payment_status_data, 'total')); ?>;
        const paymentColors = {
            'pending': '#ffc107',
            'captured': '#0d6efd',
            'released': '#28a745',
            'refunded': '#dc3545',
            'failed': '#6c757d'
        };
        
        new Chart(paymentCtx, {
            type: 'doughnut',
            data: {
                labels: paymentLabels.map(label => label.charAt(0).toUpperCase() + label.slice(1)),
                datasets: [{
                    data: paymentData,
                    backgroundColor: paymentLabels.map(label => paymentColors[label] || '#6c757d'),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 10
                        }
                    }
                }
            }
        });
        
        // ============ COURSE CHART ============
        <?php if ($course_data): ?>
        const courseCtx = document.getElementById('courseChart').getContext('2d');
        const courseLabels = <?php echo json_encode(array_column($course_data, 'course_code')); ?>;
        const courseData = <?php echo json_encode(array_column($course_data, 'total')); ?>;
        
        new Chart(courseCtx, {
            type: 'bar',
            data: {
                labels: courseLabels,
                datasets: [{
                    label: 'Sessions',
                    data: courseData,
                    backgroundColor: [
                        'rgba(13, 110, 253, 0.7)',
                        'rgba(40, 167, 69, 0.7)',
                        'rgba(255, 193, 7, 0.7)',
                        'rgba(220, 53, 69, 0.7)',
                        'rgba(23, 162, 184, 0.7)'
                    ],
                    borderColor: [
                        '#0d6efd',
                        '#28a745',
                        '#ffc107',
                        '#dc3545',
                        '#17a2b8'
                    ],
                    borderWidth: 2
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
                            stepSize: 1
                        }
                    }
                }
            }
        });
        <?php endif; ?>
        
        // ============ AUTO REFRESH ============
        // Refresh notifications every 60 seconds
        setTimeout(function() {
            location.reload();
        }, 60000);
    </script>
</body>
</html>