<?php
// admin/sessions.php
require_once dirname(__DIR__) . '/includes/functions.php';
requireAdmin();

$user = getCurrentUser();

// Get filter parameters
$filter_status = $_GET['status'] ?? 'all';
$filter_semester = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;
$filter_mentor = isset($_GET['mentor']) ? (int)$_GET['mentor'] : 0;
$filter_course = isset($_GET['course']) ? $_GET['course'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$query = "
    SELECT s.*, 
           m.name as mentor_name, m.email as mentor_email,
           u.name as mentee_name, u.email as mentee_email,
           sem.semester_name
    FROM sessions s
    JOIN users m ON s.mentor_id = m.id
    JOIN users u ON s.mentee_id = u.id
    JOIN semesters sem ON s.semester_id = sem.id
    WHERE 1=1
";

$params = [];

if ($filter_status !== 'all') {
    $query .= " AND s.status = ?";
    $params[] = $filter_status;
}

if ($filter_semester > 0) {
    $query .= " AND s.semester_id = ?";
    $params[] = $filter_semester;
}

if ($filter_mentor > 0) {
    $query .= " AND s.mentor_id = ?";
    $params[] = $filter_mentor;
}

if (!empty($filter_course)) {
    $query .= " AND s.course_code = ?";
    $params[] = $filter_course;
}

if (!empty($search)) {
    $query .= " AND (m.name LIKE ? OR u.name LIKE ? OR s.course_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY s.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$sessions = $stmt->fetchAll();

// Get semesters for filter
$stmt = $pdo->query("SELECT id, semester_name FROM semesters ORDER BY start_date DESC");
$semesters = $stmt->fetchAll();

// Get mentors for filter
$stmt = $pdo->query("
    SELECT DISTINCT u.id, u.name 
    FROM users u
    JOIN sessions s ON u.id = s.mentor_id
    WHERE u.role = 'mentor'
    ORDER BY u.name
");
$mentors = $stmt->fetchAll();

// Get courses for filter
$stmt = $pdo->query("
    SELECT DISTINCT course_code 
    FROM sessions 
    WHERE course_code IS NOT NULL
    ORDER BY course_code
");
$courses = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM sessions");
$total_sessions = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total FROM sessions WHERE status = 'completed'");
$completed_sessions = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total FROM sessions WHERE status IN ('pending', 'confirmed')");
$active_sessions = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total FROM sessions WHERE status = 'cancelled'");
$cancelled_sessions = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total FROM sessions WHERE status = 'no_show'");
$no_show_sessions = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT SUM(total_amount) as total FROM sessions WHERE payment_status = 'paid_to_admin'");
$total_revenue = $stmt->fetchColumn() ?? 0;

$stmt = $pdo->query("SELECT AVG(total_amount) as avg FROM sessions WHERE payment_status = 'paid_to_admin'");
$avg_revenue = $stmt->fetchColumn() ?? 0;

// Get monthly session data (last 6 months)
$monthly_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_label = date('M Y', strtotime("-$i months"));
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM sessions WHERE TO_CHAR(created_at, 'YYYY-MM') = ?");
    $stmt->execute([$month]);
    $monthly_data[$month_label] = (int)$stmt->fetchColumn();
}

// Get status distribution
$status_distribution = [
    'completed' => $completed_sessions,
    'pending' => $active_sessions,
    'cancelled' => $cancelled_sessions,
    'no_show' => $no_show_sessions
];

// Get top courses by sessions
$stmt = $pdo->query("
    SELECT course_code, COUNT(*) as total 
    FROM sessions 
    GROUP BY course_code 
    ORDER BY total DESC 
    LIMIT 5
");
$top_courses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Sessions - Admin</title>
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
        .session-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        .session-card:hover {
            background-color: #f8f9fa;
        }
        .session-card.status-completed {
            border-left-color: #28a745;
        }
        .session-card.status-pending {
            border-left-color: #ffc107;
        }
        .session-card.status-confirmed {
            border-left-color: #0d6efd;
        }
        .session-card.status-cancelled {
            border-left-color: #dc3545;
        }
        .session-card.status-no_show {
            border-left-color: #6c757d;
        }
        .chart-container {
            height: 200px;
        }
        .filter-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 16px;
        }
        @media (max-width: 768px) {
            .stat-card .stat-icon { font-size: 1.5rem; }
            .chart-container { height: 150px; }
            .table-responsive { font-size: 0.85rem; }
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
                        <h3><i class="bi bi-calendar-check"></i> All Sessions</h3>
                        <p class="text-muted">View and manage all sessions across the platform.</p>
                    </div>
                    <div>
                        <span class="badge bg-primary fs-6">
                            <i class="bi bi-calendar"></i> <?php echo $total_sessions; ?> Total
                        </span>
                        <span class="badge bg-success fs-6 ms-2">
                            <i class="bi bi-check-circle"></i> <?php echo $completed_sessions; ?> Completed
                        </span>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3 col-6">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <h6 class="card-title">Total Sessions</h6>
                                <h2 class="mb-0"><?php echo $total_sessions; ?></h2>
                                <small>All time</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <h6 class="card-title">Completed</h6>
                                <h2 class="mb-0"><?php echo $completed_sessions; ?></h2>
                                <small><?php echo $total_sessions > 0 ? round(($completed_sessions / $total_sessions) * 100) : 0; ?>%</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card stat-card bg-warning text-dark">
                            <div class="card-body">
                                <h6 class="card-title">Active</h6>
                                <h2 class="mb-0"><?php echo $active_sessions; ?></h2>
                                <small>Pending/Confirmed</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <h6 class="card-title">Revenue</h6>
                                <h2 class="mb-0"><?php echo formatCurrency($total_revenue); ?></h2>
                                <small>Avg: <?php echo formatCurrency($avg_revenue); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts -->
                <div class="row g-3 mb-4">
                    <div class="col-md-7">
                        <div class="card shadow">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-graph-up"></i> Sessions Trend</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="trendChart" class="chart-container"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="card shadow">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-pie-chart"></i> Status Distribution</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="statusChart" class="chart-container"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Top Courses -->
                <?php if ($top_courses): ?>
                    <div class="card shadow mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-book"></i> Top Courses</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <?php foreach ($top_courses as $course): ?>
                                    <div class="col-md-2 col-4">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <h5><?php echo htmlspecialchars($course['course_code']); ?></h5>
                                                <span class="badge bg-primary"><?php echo $course['total']; ?> sessions</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Filters -->
                <div class="card shadow mb-4">
                    <div class="card-body filter-section">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" onchange="this.form.submit()">
                                    <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All</option>
                                    <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo $filter_status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    <option value="no_show" <?php echo $filter_status === 'no_show' ? 'selected' : ''; ?>>No Show</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Semester</label>
                                <select class="form-select" name="semester" onchange="this.form.submit()">
                                    <option value="0">All Semesters</option>
                                    <?php foreach ($semesters as $sem): ?>
                                        <option value="<?php echo $sem['id']; ?>" <?php echo $filter_semester == $sem['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($sem['semester_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Mentor</label>
                                <select class="form-select" name="mentor" onchange="this.form.submit()">
                                    <option value="0">All Mentors</option>
                                    <?php foreach ($mentors as $m): ?>
                                        <option value="<?php echo $m['id']; ?>" <?php echo $filter_mentor == $m['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($m['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Course</label>
                                <select class="form-select" name="course" onchange="this.form.submit()">
                                    <option value="">All Courses</option>
                                    <?php foreach ($courses as $c): ?>
                                        <option value="<?php echo htmlspecialchars($c['course_code']); ?>" <?php echo $filter_course == $c['course_code'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($c['course_code']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" name="search" placeholder="Mentor, Mentee, Course..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Sessions Table -->
                <div class="card shadow">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-list-ul"></i> Sessions</h6>
                        <span class="badge bg-secondary"><?php echo count($sessions); ?> entries</span>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($sessions): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Mentor</th>
                                            <th>Mentee</th>
                                            <th>Course</th>
                                            <th>Date & Time</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Payment</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sessions as $session): ?>
                                            <?php
                                            $status_class = $session['status'] === 'completed' ? 'success' : 
                                                            ($session['status'] === 'confirmed' ? 'primary' : 
                                                            ($session['status'] === 'pending' ? 'warning' : 
                                                            ($session['status'] === 'cancelled' ? 'danger' : 'secondary')));
                                            ?>
                                            <tr class="session-card status-<?php echo $session['status']; ?>">
                                                <td>#<?php echo $session['id']; ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($session['mentor_name']); ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($session['mentor_email']); ?></small>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($session['mentee_name']); ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($session['mentee_email']); ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($session['course_code']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($session['semester_name']); ?></small>
                                                </td>
                                                <td>
                                                    <?php echo date('d M Y', strtotime($session['session_date'])); ?>
                                                    <br><small class="text-muted"><?php echo date('h:i A', strtotime($session['session_date'])); ?></small>
                                                </td>
                                                <td><?php echo formatCurrency($session['total_amount']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $status_class; ?>">
                                                        <?php echo getSessionStatus($session['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $session['payment_status'] === 'released_to_mentor' ? 'success' : 
                                                            ($session['payment_status'] === 'paid_to_admin' ? 'info' : 'secondary'); 
                                                    ?>">
                                                        <?php echo getPaymentStatus($session['payment_status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="session_details.php?id=<?php echo $session['id']; ?>" 
                                                       class="btn btn-sm btn-primary">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php if ($session['payment_status'] === 'paid_to_admin' && $session['status'] === 'completed'): ?>
                                                        <a href="../admin/payouts.php?release=<?php echo $session['id']; ?>" 
                                                           class="btn btn-sm btn-success">
                                                            <i class="bi bi-cash"></i>
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
                                <i class="bi bi-calendar-x display-1 text-muted"></i>
                                <p class="text-muted mt-3">No sessions found.</p>
                                <p class="text-muted small">Sessions will appear here once bookings are made.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // ============================================
        // TREND CHART
        // ============================================
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        const trendLabels = <?php echo json_encode(array_keys($monthly_data)); ?>;
        const trendData = <?php echo json_encode(array_values($monthly_data)); ?>;
        
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'Sessions',
                    data: trendData,
                    backgroundColor: 'rgba(13, 110, 253, 0.2)',
                    borderColor: 'rgba(13, 110, 253, 1)',
                    borderWidth: 3,
                    pointBackgroundColor: 'rgba(13, 110, 253, 1)',
                    tension: 0.3,
                    fill: true
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
        
        // ============================================
        // STATUS DISTRIBUTION CHART
        // ============================================
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusLabels = ['Completed', 'Active', 'Cancelled', 'No Show'];
        const statusData = [
            <?php echo $status_distribution['completed']; ?>,
            <?php echo $status_distribution['pending']; ?>,
            <?php echo $status_distribution['cancelled']; ?>,
            <?php echo $status_distribution['no_show']; ?>
        ];
        const statusColors = ['#28a745', '#ffc107', '#dc3545', '#6c757d'];
        
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusData,
                    backgroundColor: statusColors,
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
    </script>
</body>
</html>