<?php
// mentor/history.php
require_once dirname(__DIR__, 2) . '/backend/includes/functions.php';
requireMentor();

$user = getCurrentUser();
$error = '';
$success = '';

// Get filter parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_semester = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query for completed and cancelled sessions
$query = "
    SELECT s.*, 
           u.name as mentee_name, u.email as mentee_email, u.phone as mentee_phone,
           sem.semester_name
    FROM sessions s
    JOIN users u ON s.mentee_id = u.id
    LEFT JOIN semesters sem ON s.semester_id = sem.id
    WHERE s.mentor_id = ?
";

$params = [$user['id']];

if ($filter_status === 'completed') {
    $query .= " AND s.status = 'completed'";
} elseif ($filter_status === 'cancelled') {
    $query .= " AND s.status = 'cancelled'";
} elseif ($filter_status === 'no_show') {
    $query .= " AND s.status = 'no_show'";
} else {
    $query .= " AND s.status IN ('completed', 'cancelled', 'no_show')";
}

if ($filter_semester > 0) {
    $query .= " AND s.semester_id = ?";
    $params[] = $filter_semester;
}

if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR s.course_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY s.session_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$history = $stmt->fetchAll();

// Get semesters for filter
$stmt = $pdo->query("SELECT id, semester_name FROM semesters ORDER BY start_date DESC");
$semesters = $stmt->fetchAll();

// Get summary statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM sessions WHERE mentor_id = ? AND status = 'completed'");
$stmt->execute([$user['id']]);
$total_sessions = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT SUM(duration_minutes) / 60.0 as total_hours FROM sessions WHERE mentor_id = ? AND status = 'completed'");
$stmt->execute([$user['id']]);
$total_hours = $stmt->fetchColumn() ?? 0;

$stmt = $pdo->prepare("SELECT SUM(mentor_payout) as total FROM sessions WHERE mentor_id = ? AND payment_status = 'released_to_mentor'");
$stmt->execute([$user['id']]);
$total_earnings = $stmt->fetchColumn() ?? 0;

// Get certificate info
$stmt = $pdo->query("SELECT * FROM semesters WHERE status = 'active' ORDER BY id DESC LIMIT 1");
$active_semester = $stmt->fetch();
$certificate = null;

if ($active_semester) {
    $stmt = $pdo->prepare("SELECT * FROM certificates WHERE mentor_id = ? AND semester_id = ?");
    $stmt->execute([$user['id'], $active_semester['id']]);
    $certificate = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History - Mentor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .stat-card {
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .rating-stars {
            color: #ffc107;
        }
        .history-table tr {
            transition: background 0.2s ease;
        }
        .history-table tr:hover {
            background-color: #f8f9fa;
        }
        .badge-completed {
            background-color: #28a745;
            color: white;
        }
        .badge-cancelled {
            background-color: #dc3545;
            color: white;
        }
        .badge-no_show {
            background-color: #6c757d;
            color: white;
        }
    </style>
</head>
<body>
    <?php include dirname(__DIR__, 2) . '/frontend/includes/mentor_nav.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3 col-lg-2 mb-3">
                <?php include dirname(__DIR__, 2) . '/frontend/includes/mentor_sidebar.php'; ?>
            </div>
            
            <div class="col-md-9 col-lg-10">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h3><i class="bi bi-clock-history"></i> My History</h3>
                        <p class="text-muted">View your completed sessions and performance.</p>
                    </div>
                    <div>
                        <?php if ($certificate): ?>
                            <a href="download_certificate.php?id=<?php echo $certificate['id']; ?>" 
                               class="btn btn-success">
                                <i class="bi bi-award"></i> Download Certificate
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <h6 class="card-title">Total Sessions</h6>
                                <h2 class="mb-0"><?php echo $total_sessions; ?></h2>
                                <small>Completed sessions</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <h6 class="card-title">Total Hours</h6>
                                <h2 class="mb-0"><?php echo round($total_hours, 1); ?></h2>
                                <small>Mentoring hours</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <h6 class="card-title">Total Earnings</h6>
                                <h2 class="mb-0"><?php echo formatCurrency($total_earnings); ?></h2>
                                <small>Released payments</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" onchange="this.form.submit()">
                                    <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All History</option>
                                    <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    <option value="no_show" <?php echo $filter_status === 'no_show' ? 'selected' : ''; ?>>No Show</option>
                                </select>
                            </div>
                            <div class="col-md-3">
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
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" name="search" placeholder="Search by mentee or course..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100 me-2">Filter</button>
                                <a href="history.php" class="btn btn-secondary w-100">Clear</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- History Table -->
                <div class="card shadow">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-list-ul"></i> Session History</h6>
                        <span class="badge bg-secondary"><?php echo count($history); ?> entries</span>
                    </div>
                    <div class="card-body">
                        <?php if ($history): ?>
                            <div class="table-responsive">
                                <table class="table table-hover history-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Mentee</th>
                                            <th>Course</th>
                                            <th>Date</th>
                                            <th>Duration</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($history as $session): ?>
                                        <tr>
                                            <td>#<?php echo $session['id']; ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($session['mentee_name']); ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($session['mentee_email']); ?></small>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($session['course_code']); ?></strong>
                                                <?php if ($session['semester_name']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($session['semester_name']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo date('d M Y', strtotime($session['session_date'])); ?>
                                                <br><small class="text-muted"><?php echo date('h:i A', strtotime($session['session_date'])); ?></small>
                                            </td>
                                            <td><?php echo $session['duration_minutes']; ?> min</td>
                                            <td><?php echo formatCurrency($session['mentor_payout']); ?></td>
                                            <td>
                                                <?php 
                                                    $status_class = $session['status'] === 'completed' ? 'badge-completed' : 
                                                                    ($session['status'] === 'cancelled' ? 'badge-cancelled' : 'badge-no_show');
                                                ?>
                                                <span class="badge <?php echo $status_class; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $session['status'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-clock-history display-1 text-muted"></i>
                                <p class="text-muted mt-3">No history found.</p>
                                <a href="sessions.php" class="btn btn-primary">View Your Sessions</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>