<?php
// admin/reports.php
require_once dirname(__DIR__) . '/includes/functions.php';
requireAdmin();

$user = getCurrentUser();
$error = '';
$success = '';

// Get report type
$report_type = isset($_GET['type']) ? $_GET['type'] : 'overview';
$export_format = isset($_GET['export']) ? $_GET['export'] : '';

// Get semester filter
$stmt = $pdo->query("SELECT id, semester_name, start_date, end_date, status FROM semesters ORDER BY start_date DESC");
$semesters = $stmt->fetchAll();

$semester_id = isset($_GET['semester_id']) ? (int)$_GET['semester_id'] : 0;

// Get date range filter
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Build report data based on type
if ($report_type === 'overview') {
    // Overview statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role != 'admin'");
    $total_users = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'mentor' AND status = 'active'");
    $active_mentors = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'mentee' AND status = 'active'");
    $active_mentees = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM sessions");
    $total_sessions = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM sessions WHERE status = 'completed'");
    $completed_sessions = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT SUM(total_amount) as total FROM sessions WHERE payment_status = 'paid_to_admin'");
    $session_revenue = (float)($stmt->fetchColumn() ?? 0);

    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM subscriptions WHERE approved_at IS NOT NULL");
    $subscription_revenue = (float)$stmt->fetchColumn();
    $total_revenue = $session_revenue + $subscription_revenue;
    
    $stmt = $pdo->query("SELECT SUM(admin_fee) as total FROM sessions WHERE payment_status = 'released_to_mentor'");
    $total_fees = $stmt->fetchColumn() ?? 0;
    
    $stmt = $pdo->query("SELECT AVG(rating) as avg FROM feedback");
    $avg_rating = $stmt->fetchColumn() ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM certificates WHERE status = 'issued'");
    $total_certificates = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM mentee_enrollments WHERE status = 'active'");
    $active_enrollments = $stmt->fetchColumn();
    
    // Growth rates
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role != 'admin' AND created_at >= date_trunc('month', CURRENT_DATE) AND created_at < date_trunc('month', CURRENT_DATE) + INTERVAL '1 month'");
    $new_users_this_month = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role != 'admin' AND created_at >= date_trunc('month', CURRENT_DATE) - INTERVAL '1 month' AND created_at < date_trunc('month', CURRENT_DATE)");
    $new_users_last_month = $stmt->fetchColumn();
    $user_growth = $new_users_last_month > 0 ? round((($new_users_this_month - $new_users_last_month) / $new_users_last_month) * 100, 1) : ($new_users_this_month > 0 ? 100 : 0);
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM sessions WHERE created_at >= date_trunc('month', CURRENT_DATE) AND created_at < date_trunc('month', CURRENT_DATE) + INTERVAL '1 month'");
    $sessions_this_month = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM sessions WHERE created_at >= date_trunc('month', CURRENT_DATE) - INTERVAL '1 month' AND created_at < date_trunc('month', CURRENT_DATE)");
    $sessions_last_month = $stmt->fetchColumn();
    $session_growth = $sessions_last_month > 0 ? round((($sessions_this_month - $sessions_last_month) / $sessions_last_month) * 100, 1) : ($sessions_this_month > 0 ? 100 : 0);
    
} elseif ($report_type === 'mentors') {
    // Mentor performance report
    $query = "
        SELECT u.id, u.name, u.email, u.phone, u.programme,
               COUNT(DISTINCT s.id) as total_sessions,
               COUNT(DISTINCT CASE WHEN s.status = 'completed' THEN s.id END) as completed_sessions,
               COUNT(DISTINCT CASE WHEN s.status IN ('pending', 'confirmed') THEN s.id END) as pending_sessions,
               SUM(CASE WHEN s.payment_status = 'released_to_mentor' THEN s.mentor_payout ELSE 0 END) as total_earnings,
               SUM(s.duration_minutes) / 60.0 as total_hours,
               AVG(f.rating) as avg_rating,
               COUNT(DISTINCT f.id) as total_feedback,
               COUNT(DISTINCT r.course_code) as total_courses,
               (SELECT COUNT(*) FROM certificates c WHERE c.mentor_id = u.id) as total_certificates
        FROM users u
        LEFT JOIN sessions s ON u.id = s.mentor_id
        LEFT JOIN feedback f ON u.id = f.mentor_id
        LEFT JOIN mentor_rates r ON u.id = r.mentor_id
        WHERE u.role = 'mentor' AND u.status = 'active'
    ";
    
    if ($semester_id > 0) {
        $query .= " AND (s.semester_id = $semester_id OR s.semester_id IS NULL)";
    }
    
    if (!empty($date_from) && !empty($date_to)) {
        $query .= " AND (s.session_date BETWEEN '$date_from' AND '$date_to' OR s.session_date IS NULL)";
    }
    
    $query .= " GROUP BY u.id ORDER BY avg_rating IS NULL, avg_rating DESC, completed_sessions DESC";
    
    $stmt = $pdo->query($query);
    $mentor_reports = $stmt->fetchAll();
    
} elseif ($report_type === 'mentees') {
    // Mentee engagement report
    $query = "
        SELECT u.id, u.name, u.email, u.phone, u.programme,
               COUNT(DISTINCT s.id) as total_sessions,
               COUNT(DISTINCT CASE WHEN s.status = 'completed' THEN s.id END) as completed_sessions,
               COUNT(DISTINCT CASE WHEN s.status IN ('pending', 'confirmed') THEN s.id END) as pending_sessions,
               SUM(CASE WHEN s.payment_status = 'paid_to_admin' THEN s.total_amount ELSE 0 END) as total_spent,
               (SELECT COALESCE(SUM(sub.amount), 0) FROM subscriptions sub WHERE sub.user_id = u.id AND sub.approved_at IS NOT NULL) as subscription_spending,
               COUNT(DISTINCT s.course_code) as total_courses,
               COUNT(DISTINCT me.course_code) as enrolled_courses,
               (SELECT COUNT(*) FROM feedback f WHERE f.mentee_id = u.id) as total_feedback
        FROM users u
        LEFT JOIN sessions s ON u.id = s.mentee_id
        LEFT JOIN mentee_enrollments me ON u.id = me.mentee_id AND me.status = 'active'
        WHERE u.role = 'mentee' AND u.status = 'active'
    ";
    
    if ($semester_id > 0) {
        $query .= " AND (s.semester_id = $semester_id OR s.semester_id IS NULL)";
    }
    
    if (!empty($date_from) && !empty($date_to)) {
        $query .= " AND (s.session_date BETWEEN '$date_from' AND '$date_to' OR s.session_date IS NULL)";
    }
    
    $query .= " GROUP BY u.id ORDER BY completed_sessions DESC";
    
    $stmt = $pdo->query($query);
    $mentee_reports = $stmt->fetchAll();
    
} elseif ($report_type === 'courses') {
    // Course popularity report
    $query = "
        SELECT course_code,
               COUNT(DISTINCT s.mentor_id) as mentor_count,
               COUNT(DISTINCT s.mentee_id) as mentee_count,
               COUNT(*) as total_sessions,
               SUM(CASE WHEN s.status = 'completed' THEN 1 ELSE 0 END) as completed_sessions,
               SUM(CASE WHEN s.status IN ('pending', 'confirmed') THEN 1 ELSE 0 END) as active_sessions,
               SUM(s.total_amount) as total_revenue,
               SUM(s.admin_fee) as total_fees,
               SUM(s.mentor_payout) as total_payouts,
               AVG(f.rating) as avg_rating
        FROM sessions s
        LEFT JOIN feedback f ON s.id = f.session_id
        WHERE 1=1
    ";
    
    if ($semester_id > 0) {
        $query .= " AND s.semester_id = $semester_id";
    }
    
    if (!empty($date_from) && !empty($date_to)) {
        $query .= " AND s.session_date BETWEEN '$date_from' AND '$date_to'";
    }
    
    $query .= " GROUP BY course_code ORDER BY total_sessions DESC";
    
    $stmt = $pdo->query($query);
    $course_reports = $stmt->fetchAll();
    
} elseif ($report_type === 'payments') {
    // Payment summary report
    $query = "
        SELECT 
            TO_CHAR(p.created_at, 'YYYY-MM') as month,
            COUNT(*) as total_transactions,
            SUM(p.amount) as total_amount,
            SUM(p.admin_fee) as total_fees,
            SUM(p.mentor_payout) as total_payouts,
            SUM(p.amount) - SUM(p.mentor_payout) as net_revenue,
            COUNT(CASE WHEN p.payment_status = 'captured' THEN 1 END) as captured_count,
            COUNT(CASE WHEN p.payment_status = 'released' THEN 1 END) as released_count
        FROM payments p
        JOIN sessions s ON s.id = p.session_id
        WHERE 1=1
    ";
    
    if ($semester_id > 0) {
        $query .= " AND s.semester_id = $semester_id";
    }
    
    if (!empty($date_from) && !empty($date_to)) {
        $query .= " AND DATE(p.created_at) BETWEEN '$date_from' AND '$date_to'";
    }
    
    $query .= " GROUP BY month ORDER BY month DESC";
    
    $stmt = $pdo->query($query);
    $payment_reports = $stmt->fetchAll();
    
} elseif ($report_type === 'certificates') {
    // Certificate report
    $query = "
        SELECT c.*, 
               u.name as mentor_name, u.email as mentor_email,
               s.semester_name,
               (SELECT AVG(rating) FROM feedback WHERE mentor_id = u.id) as avg_rating,
               (SELECT COUNT(*) FROM feedback WHERE mentor_id = u.id) as total_reviews
        FROM certificates c
        JOIN users u ON c.mentor_id = u.id
        JOIN semesters s ON c.semester_id = s.id
        WHERE 1=1
    ";
    
    if ($semester_id > 0) {
        $query .= " AND c.semester_id = $semester_id";
    }
    
    if (!empty($date_from) && !empty($date_to)) {
        $query .= " AND c.issue_date BETWEEN '$date_from' AND '$date_to'";
    }
    
    $query .= " ORDER BY c.issue_date DESC";
    
    $stmt = $pdo->query($query);
    $certificate_reports = $stmt->fetchAll();
    
} elseif ($report_type === 'feedback') {
    // Feedback report
    $query = "
        SELECT f.*,
               m.name as mentor_name,
               u.name as mentee_name,
               s.course_code,
               s.session_date
        FROM feedback f
        JOIN users m ON f.mentor_id = m.id
        JOIN users u ON f.mentee_id = u.id
        JOIN sessions s ON f.session_id = s.id
        WHERE 1=1
    ";
    
    if ($semester_id > 0) {
        $query .= " AND s.semester_id = $semester_id";
    }
    
    if (!empty($date_from) && !empty($date_to)) {
        $query .= " AND f.submitted_at BETWEEN '$date_from' AND '$date_to'";
    }
    
    $query .= " ORDER BY f.submitted_at DESC";
    
    $stmt = $pdo->query($query);
    $feedback_reports = $stmt->fetchAll();
}

// Get current date range label
$date_range_label = '';
if (!empty($date_from) && !empty($date_to)) {
    $date_range_label = date('d M Y', strtotime($date_from)) . ' - ' . date('d M Y', strtotime($date_to));
} else {
    $date_range_label = 'All Time';
}

// Get semester name for filter
$semester_name = 'All Semesters';
if ($semester_id > 0) {
    foreach ($semesters as $sem) {
        if ($sem['id'] == $semester_id) {
            $semester_name = $sem['semester_name'];
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/mobile.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .report-card {
            transition: all 0.3s ease;
            border-radius: 12px;
            border: none;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        .report-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
        }
        .stat-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6c757d;
        }
        .stat-growth {
            font-size: 0.8rem;
            padding: 2px 10px;
            border-radius: 20px;
        }
        .stat-growth.positive {
            background: #d4edda;
            color: #155724;
        }
        .stat-growth.negative {
            background: #f8d7da;
            color: #721c24;
        }
        .stat-growth.neutral {
            background: #e9ecef;
            color: #6c757d;
        }
        .filter-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 16px;
        }
        .nav-pills .nav-link {
            border-radius: 50px;
            padding: 8px 20px;
            font-size: 0.85rem;
        }
        .nav-pills .nav-link.active {
            background: #0d6efd;
        }
        .report-table {
            font-size: 0.9rem;
        }
        .report-table thead th {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .report-table td {
            vertical-align: middle;
        }
        .export-btn {
            border-radius: 50px;
            padding: 6px 18px;
            font-size: 0.85rem;
        }
        .chart-container {
            height: 250px;
        }
        .metric-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px 20px;
            text-align: center;
        }
        .metric-card .value {
            font-size: 1.8rem;
            font-weight: 700;
        }
        .metric-card .label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .metric-card .trend {
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 20px;
            display: inline-block;
        }
        .trend.up { background: #d4edda; color: #155724; }
        .trend.down { background: #f8d7da; color: #721c24; }
        .trend.flat { background: #e9ecef; color: #6c757d; }
        @media (max-width: 768px) {
            .stat-number { font-size: 1.5rem; }
            .nav-pills .nav-link { padding: 6px 12px; font-size: 0.75rem; }
            .chart-container { height: 180px; }
            .report-table { font-size: 0.75rem; }
            .metric-card .value { font-size: 1.3rem; }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_nav.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3 col-lg-2 mb-3">
                <?php include '../includes/admin_sidebar.php'; ?>
            </div>
            
            <div class="col-md-9 col-lg-10">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                    <div>
                        <h3><i class="bi bi-file-earmark-text"></i> Reports</h3>
                        <p class="text-muted">Generate and view various reports for the platform.</p>
                    </div>
                    <div>
                        <span class="badge bg-secondary">
                            <i class="bi bi-calendar3"></i> <?php echo $date_range_label; ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <!-- Report Navigation -->
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <ul class="nav nav-pills flex-wrap gap-1" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link <?php echo $report_type === 'overview' ? 'active' : ''; ?>" 
                                   href="reports.php?type=overview">
                                    <i class="bi bi-speedometer2"></i> Overview
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $report_type === 'mentors' ? 'active' : ''; ?>" 
                                   href="reports.php?type=mentors">
                                    <i class="bi bi-person-badge"></i> Mentors
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $report_type === 'mentees' ? 'active' : ''; ?>" 
                                   href="reports.php?type=mentees">
                                    <i class="bi bi-person"></i> Mentees
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $report_type === 'courses' ? 'active' : ''; ?>" 
                                   href="reports.php?type=courses">
                                    <i class="bi bi-book"></i> Courses
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $report_type === 'payments' ? 'active' : ''; ?>" 
                                   href="reports.php?type=payments">
                                    <i class="bi bi-currency-dollar"></i> Payments
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $report_type === 'certificates' ? 'active' : ''; ?>" 
                                   href="reports.php?type=certificates">
                                    <i class="bi bi-award"></i> Certificates
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $report_type === 'feedback' ? 'active' : ''; ?>" 
                                   href="reports.php?type=feedback">
                                    <i class="bi bi-star"></i> Feedback
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card shadow mb-4">
                    <div class="card-body filter-section">
                        <form method="GET" action="" class="row g-3 align-items-end">
                            <input type="hidden" name="type" value="<?php echo $report_type; ?>">
                            <div class="col-md-3">
                                <label class="form-label">Semester</label>
                                <select class="form-select" name="semester_id" onchange="this.form.submit()">
                                    <option value="0">All Semesters</option>
                                    <?php foreach ($semesters as $sem): ?>
                                        <option value="<?php echo $sem['id']; ?>" <?php echo $semester_id == $sem['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($sem['semester_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Date From</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Date To</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-md-3 d-flex gap-2">
                                <button type="submit" class="btn btn-primary w-50">
                                    <i class="bi bi-funnel"></i> Filter
                                </button>
                                <a href="reports.php?type=<?php echo $report_type; ?>" class="btn btn-secondary w-50">Clear</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Report Content -->
                <?php if ($report_type === 'overview'): ?>
                    <!-- ==========================================
                    OVERVIEW REPORT
                    ========================================== -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3 col-6">
                            <div class="card stat-card bg-primary text-white">
                                <div class="card-body">
                                    <div class="stat-label">Total Users</div>
                                    <div class="stat-number"><?php echo $total_users; ?></div>
                                    <small>
                                        <?php echo $active_mentors; ?> Mentors | <?php echo $active_mentees; ?> Mentees
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="card stat-card bg-success text-white">
                                <div class="card-body">
                                    <div class="stat-label">Total Sessions</div>
                                    <div class="stat-number"><?php echo $total_sessions; ?></div>
                                    <small>
                                        <?php echo $completed_sessions; ?> Completed | <?php echo $total_sessions - $completed_sessions; ?> Pending
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="card stat-card bg-warning text-dark">
                                <div class="card-body">
                                    <div class="stat-label">Total Revenue</div>
                                    <div class="stat-number"><?php echo formatCurrency($total_revenue); ?></div>
                                    <small>
                                        Subscriptions: <?php echo formatCurrency($subscription_revenue); ?> | Fees: <?php echo formatCurrency($total_fees); ?> | <?php echo $total_certificates; ?> Certificates
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="card stat-card bg-info text-white">
                                <div class="card-body">
                                    <div class="stat-label">Avg Rating</div>
                                    <div class="stat-number"><?php echo number_format($avg_rating, 1); ?> ★</div>
                                    <small>
                                        <?php echo $active_enrollments; ?> Active Enrollments
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Growth Metrics -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="card report-card">
                                <div class="card-body">
                                    <h6 class="mb-3"><i class="bi bi-person-plus"></i> User Growth</h6>
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="metric-card">
                                                <div class="value"><?php echo $new_users_this_month; ?></div>
                                                <div class="label">New Users This Month</div>
                                                <span class="trend <?php echo $user_growth > 0 ? 'up' : ($user_growth < 0 ? 'down' : 'flat'); ?>">
                                                    <?php echo $user_growth >= 0 ? '+' : ''; ?><?php echo $user_growth; ?>% <?php echo $user_growth > 0 ? '↑' : ($user_growth < 0 ? '↓' : '→'); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="metric-card">
                                                <div class="value"><?php echo $new_users_last_month; ?></div>
                                                <div class="label">Last Month</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card report-card">
                                <div class="card-body">
                                    <h6 class="mb-3"><i class="bi bi-calendar-check"></i> Session Growth</h6>
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="metric-card">
                                                <div class="value"><?php echo $sessions_this_month; ?></div>
                                                <div class="label">Sessions This Month</div>
                                                <span class="trend <?php echo $session_growth > 0 ? 'up' : ($session_growth < 0 ? 'down' : 'flat'); ?>">
                                                    <?php echo $session_growth >= 0 ? '+' : ''; ?><?php echo $session_growth; ?>% <?php echo $session_growth > 0 ? '↑' : ($session_growth < 0 ? '↓' : '→'); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="metric-card">
                                                <div class="value"><?php echo $sessions_last_month; ?></div>
                                                <div class="label">Last Month</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($report_type === 'mentors'): ?>
                    <!-- ==========================================
                    MENTOR PERFORMANCE REPORT
                    ========================================== -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0"><i class="bi bi-person-badge"></i> Mentor Performance Report</h6>
                        <?php if ($mentor_reports): ?>
                            <a href="reports.php?type=mentors&export=csv" class="btn btn-sm btn-success export-btn">
                                <i class="bi bi-download"></i> Export CSV
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card shadow">
                        <div class="card-body p-0">
                            <?php if ($mentor_reports): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover report-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Mentor</th>
                                                <th>Programme</th>
                                                <th>Sessions</th>
                                                <th>Completed</th>
                                                <th>Hours</th>
                                                <th>Earnings</th>
                                                <th>Rating</th>
                                                <th>Reviews</th>
                                                <th>Courses</th>
                                                <th>Certificates</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($mentor_reports as $mentor): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($mentor['name']); ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($mentor['email']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($mentor['programme'] ?? 'N/A'); ?></td>
                                                <td><?php echo $mentor['total_sessions']; ?></td>
                                                <td><?php echo $mentor['completed_sessions']; ?></td>
                                                <td><?php echo round($mentor['total_hours'], 1); ?></td>
                                                <td><?php echo formatCurrency($mentor['total_earnings']); ?></td>
                                                <td>
                                                    <?php if ($mentor['avg_rating']): ?>
                                                        <?php echo number_format($mentor['avg_rating'], 1); ?> ★
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $mentor['total_feedback']; ?></td>
                                                <td><?php echo $mentor['total_courses']; ?></td>
                                                <td><?php echo $mentor['total_certificates']; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center py-4">No mentor data available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                <?php elseif ($report_type === 'mentees'): ?>
                    <!-- ==========================================
                    MENTEE ENGAGEMENT REPORT
                    ========================================== -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0"><i class="bi bi-person"></i> Mentee Engagement Report</h6>
                        <?php if ($mentee_reports): ?>
                            <a href="reports.php?type=mentees&export=csv" class="btn btn-sm btn-success export-btn">
                                <i class="bi bi-download"></i> Export CSV
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card shadow">
                        <div class="card-body p-0">
                            <?php if ($mentee_reports): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover report-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Mentee</th>
                                                <th>Programme</th>
                                                <th>Sessions</th>
                                                <th>Completed</th>
                                                <th>Total Spent</th>
                                                <th>Subscription Spending</th>
                                                <th>Courses</th>
                                                <th>Enrolled</th>
                                                <th>Feedback</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($mentee_reports as $mentee): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($mentee['name']); ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($mentee['email']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($mentee['programme'] ?? 'N/A'); ?></td>
                                                <td><?php echo $mentee['total_sessions']; ?></td>
                                                <td><?php echo $mentee['completed_sessions']; ?></td>
                                                <td><?php echo formatCurrency($mentee['total_spent']); ?></td>
                                                <td><?php echo formatCurrency($mentee['subscription_spending']); ?></td>
                                                <td><?php echo $mentee['total_courses']; ?></td>
                                                <td><?php echo $mentee['enrolled_courses']; ?></td>
                                                <td><?php echo $mentee['total_feedback']; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center py-4">No mentee data available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                <?php elseif ($report_type === 'courses'): ?>
                    <!-- ==========================================
                    COURSE POPULARITY REPORT
                    ========================================== -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0"><i class="bi bi-book"></i> Course Performance Report</h6>
                        <?php if ($course_reports): ?>
                            <a href="reports.php?type=courses&export=csv" class="btn btn-sm btn-success export-btn">
                                <i class="bi bi-download"></i> Export CSV
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-3 col-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5><?php echo count($course_reports); ?></h5>
                                    <small class="text-muted">Active Courses</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5><?php echo array_sum(array_column($course_reports, 'total_sessions')); ?></h5>
                                    <small class="text-muted">Total Sessions</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5><?php echo array_sum(array_column($course_reports, 'completed_sessions')); ?></h5>
                                    <small class="text-muted">Completed</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5><?php echo formatCurrency(array_sum(array_column($course_reports, 'total_revenue'))); ?></h5>
                                    <small class="text-muted">Total Revenue</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card shadow">
                        <div class="card-body p-0">
                            <?php if ($course_reports): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover report-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Course</th>
                                                <th>Mentors</th>
                                                <th>Mentees</th>
                                                <th>Total</th>
                                                <th>Completed</th>
                                                <th>Active</th>
                                                <th>Revenue</th>
                                                <th>Fees</th>
                                                <th>Payouts</th>
                                                <th>Rating</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($course_reports as $course): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($course['course_code']); ?></strong></td>
                                                <td><?php echo $course['mentor_count']; ?></td>
                                                <td><?php echo $course['mentee_count']; ?></td>
                                                <td><?php echo $course['total_sessions']; ?></td>
                                                <td><?php echo $course['completed_sessions']; ?></td>
                                                <td><?php echo $course['active_sessions']; ?></td>
                                                <td><?php echo formatCurrency($course['total_revenue']); ?></td>
                                                <td><?php echo formatCurrency($course['total_fees']); ?></td>
                                                <td><?php echo formatCurrency($course['total_payouts']); ?></td>
                                                <td>
                                                    <?php if ($course['avg_rating']): ?>
                                                        <?php echo number_format($course['avg_rating'], 1); ?> ★
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center py-4">No course data available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                <?php elseif ($report_type === 'payments'): ?>
                    <!-- ==========================================
                    PAYMENT SUMMARY REPORT
                    ========================================== -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0"><i class="bi bi-currency-dollar"></i> Payment Summary Report</h6>
                        <?php if ($payment_reports): ?>
                            <a href="reports.php?type=payments&export=csv" class="btn btn-sm btn-success export-btn">
                                <i class="bi bi-download"></i> Export CSV
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-3 col-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5><?php echo array_sum(array_column($payment_reports, 'total_transactions')); ?></h5>
                                    <small class="text-muted">Transactions</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5><?php echo formatCurrency(array_sum(array_column($payment_reports, 'total_amount'))); ?></h5>
                                    <small class="text-muted">Total Amount</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5><?php echo formatCurrency(array_sum(array_column($payment_reports, 'net_revenue'))); ?></h5>
                                    <small class="text-muted">Net Revenue</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5><?php echo formatCurrency(array_sum(array_column($payment_reports, 'total_fees'))); ?></h5>
                                    <small class="text-muted">Service Fees</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card shadow">
                        <div class="card-body p-0">
                            <?php if ($payment_reports): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover report-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Month</th>
                                                <th>Transactions</th>
                                                <th>Total Amount</th>
                                                <th>Service Fees</th>
                                                <th>Payouts</th>
                                                <th>Net Revenue</th>
                                                <th>Captured</th>
                                                <th>Released</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($payment_reports as $payment): ?>
                                            <tr>
                                                <td><?php echo date('M Y', strtotime($payment['month'] . '-01')); ?></td>
                                                <td><?php echo $payment['total_transactions']; ?></td>
                                                <td><?php echo formatCurrency($payment['total_amount']); ?></td>
                                                <td><?php echo formatCurrency($payment['total_fees']); ?></td>
                                                <td><?php echo formatCurrency($payment['total_payouts']); ?></td>
                                                <td><strong><?php echo formatCurrency($payment['net_revenue']); ?></strong></td>
                                                <td><?php echo $payment['captured_count']; ?></td>
                                                <td><?php echo $payment['released_count']; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center py-4">No payment data available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                <?php elseif ($report_type === 'certificates'): ?>
                    <!-- ==========================================
                    CERTIFICATE REPORT
                    ========================================== -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0"><i class="bi bi-award"></i> Certificate Report</h6>
                        <?php if ($certificate_reports): ?>
                            <a href="reports.php?type=certificates&export=csv" class="btn btn-sm btn-success export-btn">
                                <i class="bi bi-download"></i> Export CSV
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-3 col-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5><?php echo count($certificate_reports); ?></h5>
                                    <small class="text-muted">Total Issued</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5><?php echo array_sum(array_column($certificate_reports, 'total_hours')); ?></h5>
                                    <small class="text-muted">Total Hours</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5><?php echo array_sum(array_column($certificate_reports, 'total_sessions')); ?></h5>
                                    <small class="text-muted">Total Sessions</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5><?php echo number_format(array_sum(array_column($certificate_reports, 'avg_rating')) / max(1, count($certificate_reports)), 1); ?> ★</h5>
                                    <small class="text-muted">Avg Rating</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card shadow">
                        <div class="card-body p-0">
                            <?php if ($certificate_reports): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover report-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Mentor</th>
                                                <th>Semester</th>
                                                <th>Hours</th>
                                                <th>Sessions</th>
                                                <th>Rating</th>
                                                <th>Reviews</th>
                                                <th>Issued Date</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($certificate_reports as $cert): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($cert['mentor_name']); ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($cert['mentor_email']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($cert['semester_name']); ?></td>
                                                <td><?php echo number_format($cert['total_hours'], 1); ?></td>
                                                <td><?php echo $cert['total_sessions']; ?></td>
                                                <td>
                                                    <?php if ($cert['avg_rating']): ?>
                                                        <?php echo number_format($cert['avg_rating'], 1); ?> ★
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $cert['total_reviews']; ?></td>
                                                <td><?php echo date('d M Y', strtotime($cert['issue_date'])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $cert['status'] === 'issued' ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst($cert['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center py-4">No certificate data available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                <?php elseif ($report_type === 'feedback'): ?>
                    <!-- ==========================================
                    FEEDBACK REPORT
                    ========================================== -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0"><i class="bi bi-star"></i> Feedback Report</h6>
                        <?php if ($feedback_reports): ?>
                            <a href="reports.php?type=feedback&export=csv" class="btn btn-sm btn-success export-btn">
                                <i class="bi bi-download"></i> Export CSV
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-3 col-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5><?php echo count($feedback_reports); ?></h5>
                                    <small class="text-muted">Total Feedback</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5><?php 
                                        $ratings = array_column($feedback_reports, 'rating');
                                        echo count($ratings) > 0 ? number_format(array_sum($ratings) / count($ratings), 1) . ' ★' : 'N/A';
                                    ?></h5>
                                    <small class="text-muted">Avg Rating</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5><?php 
                                        $positive = array_filter($feedback_reports, function($f) { return $f['rating'] >= 4; });
                                        echo count($positive);
                                    ?></h5>
                                    <small class="text-muted">Positive (4-5★)</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5><?php 
                                        $negative = array_filter($feedback_reports, function($f) { return $f['rating'] <= 2; });
                                        echo count($negative);
                                    ?></h5>
                                    <small class="text-muted">Negative (1-2★)</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card shadow">
                        <div class="card-body p-0">
                            <?php if ($feedback_reports): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover report-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Mentor</th>
                                                <th>Mentee</th>
                                                <th>Course</th>
                                                <th>Rating</th>
                                                <th>Comments</th>
                                                <th>Session Date</th>
                                                <th>Submitted</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($feedback_reports as $feedback): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($feedback['mentor_name']); ?></td>
                                                <td><?php echo htmlspecialchars($feedback['mentee_name']); ?></td>
                                                <td><?php echo htmlspecialchars($feedback['course_code']); ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-1">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="bi bi-star-fill <?php echo $i <= $feedback['rating'] ? 'text-warning' : 'text-muted opacity-25'; ?>" 
                                                               style="font-size: 0.8rem;"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($feedback['comments']): ?>
                                                        <?php echo htmlspecialchars(substr($feedback['comments'], 0, 50)); ?>
                                                        <?php echo strlen($feedback['comments']) > 50 ? '...' : ''; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('d M Y', strtotime($feedback['session_date'])); ?></td>
                                                <td><?php echo date('d M Y', strtotime($feedback['submitted_at'])); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center py-4">No feedback data available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // ============================================
        // KEYBOARD SHORTCUTS
        // ============================================
        document.addEventListener('keydown', function(e) {
            // Alt + 1-7 to switch report tabs
            if (e.altKey) {
                const reportTypes = ['overview', 'mentors', 'mentees', 'courses', 'payments', 'certificates', 'feedback'];
                const num = parseInt(e.key);
                if (num >= 1 && num <= 7) {
                    e.preventDefault();
                    window.location.href = 'reports.php?type=' + reportTypes[num - 1];
                }
            }
        });
    </script>
</body>
</html>