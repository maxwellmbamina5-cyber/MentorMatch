<?php
// admin/users.php
require_once dirname(__DIR__) . '/includes/functions.php';
requireAdmin();

$user = getCurrentUser();
$error = '';
$success = '';

// Handle account actions.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $user_id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    $action = $_POST['action'];

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your action could not be verified. Reload the page and try again.';
    } elseif (!$user_id || !in_array($action, ['approve', 'activate', 'reject', 'suspend', 'delete'], true)) {
        $error = 'Choose a valid user action.';
    } elseif ($action === 'approve' || $action === 'activate') {
        $expected_status = $action === 'approve' ? 'pending' : 'suspended';
        $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role IN ('mentor', 'mentee') AND status = ?");
        $stmt->execute([$user_id, $expected_status]);
        if ($stmt->rowCount() !== 1) {
            $error = $action === 'approve'
                ? 'This account is no longer pending approval. Refresh the user list.'
                : 'This account is no longer suspended. Refresh the user list.';
        } else {
            logActivity($user['id'], ucfirst($action) . ' user account #' . $user_id);
            $success = 'User account activated successfully. The user can now request subscription confirmation.';
        }
    } elseif ($action === 'reject' || $action === 'suspend') {
        $expected_status = $action === 'reject' ? 'pending' : 'active';
        $new_status = $action === 'reject' ? 'rejected' : 'suspended';
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND role IN ('mentor', 'mentee') AND status = ?");
        $stmt->execute([$new_status, $user_id, $expected_status]);
        if ($stmt->rowCount() !== 1) {
            $error = 'This account status has changed. Refresh the user list.';
        } else {
            logActivity($user['id'], ucfirst($action) . ' user account #' . $user_id);
            $success = $action === 'reject' ? 'User registration rejected.' : 'User suspended successfully.';
        }
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE mentor_id = ? OR mentee_id = ?");
        $stmt->execute([$user_id, $user_id]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'Cannot delete user with existing sessions. Suspend instead.';
        } else {
            $pdo->prepare("DELETE FROM mentor_applications WHERE mentor_id = ?")->execute([$user_id]);
            $pdo->prepare("DELETE FROM mentee_enrollments WHERE mentee_id = ?")->execute([$user_id]);
            $pdo->prepare("DELETE FROM mentor_rates WHERE mentor_id = ?")->execute([$user_id]);
            $pdo->prepare("DELETE FROM feedback WHERE mentor_id = ? OR mentee_id = ?")->execute([$user_id, $user_id]);

            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role IN ('mentor', 'mentee')");
            $stmt->execute([$user_id]);
            if ($stmt->rowCount() === 1) {
                logActivity($user['id'], 'Deleted user #' . $user_id);
                $success = 'User deleted successfully.';
            } else {
                $error = 'This user could not be deleted. Refresh the user list.';
            }
        }
    }
}

// Get filter parameters
$role_filter = $_GET['role'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query with additional details
$query = "
    SELECT 
        u.*,
        (SELECT COUNT(*) FROM sessions WHERE mentor_id = u.id) as total_sessions,
        (SELECT COUNT(*) FROM sessions WHERE mentor_id = u.id AND status = 'completed') as completed_sessions,
        (SELECT AVG(rating) FROM feedback WHERE mentor_id = u.id) as avg_rating,
        (SELECT COUNT(*) FROM feedback WHERE mentor_id = u.id) as total_feedback,
        (SELECT COUNT(*) FROM mentor_applications WHERE mentor_id = u.id AND status = 'approved') as approved_courses,
        (SELECT COUNT(*) FROM mentee_enrollments WHERE mentee_id = u.id AND status = 'active') as enrolled_courses,
        (SELECT COUNT(*) FROM certificates WHERE mentor_id = u.id) as total_certificates,
        (SELECT semester_name FROM semesters WHERE id = (SELECT semester_id FROM mentor_applications WHERE mentor_id = u.id AND status = 'approved' LIMIT 1)) as active_semester,
        (SELECT expires_at FROM subscriptions WHERE user_id = u.id AND status = 'active' AND expires_at > NOW() ORDER BY expires_at DESC LIMIT 1) as active_subscription_until,
        (SELECT status FROM subscriptions WHERE user_id = u.id ORDER BY requested_at DESC, id DESC LIMIT 1) as latest_subscription_status,
        (SELECT COALESCE(SUM(amount), 0) FROM subscriptions WHERE user_id = u.id AND approved_at IS NOT NULL) as subscription_spending
    FROM users u
    WHERE u.role != 'admin'
";

$params = [];

if ($role_filter !== 'all') {
    $query .= " AND u.role = ?";
    $params[] = $role_filter;
}

if ($status_filter !== 'all') {
    $query .= " AND u.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR CONCAT('MM-', LPAD(u.id::text, 8, '0')) LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role != 'admin'");
$total_users = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'mentor' AND status = 'active'");
$active_mentors = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'mentee' AND status = 'active'");
$active_mentees = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'pending'");
$pending_users = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'suspended'");
$suspended_users = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'rejected'");
$rejected_users = $stmt->fetchColumn();

// Get role distribution
$role_distribution = [
    'mentor' => $active_mentors,
    'mentee' => $active_mentees,
    'pending' => $pending_users,
    'suspended' => $suspended_users,
    'rejected' => $rejected_users
];

// Get recent user registrations (last 7 days)
$recent_registrations = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $day_label = date('D', strtotime("-$i days"));
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role != 'admin' AND DATE(created_at) = ?");
    $stmt->execute([$date]);
    $recent_registrations[$day_label] = (int)$stmt->fetchColumn();
}

// Get user growth (monthly)
$monthly_growth = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_label = date('M Y', strtotime("-$i months"));
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role != 'admin' AND TO_CHAR(created_at, 'YYYY-MM') = ?");
    $stmt->execute([$month]);
    $monthly_growth[$month_label] = (int)$stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - Admin</title>
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
        .user-card {
            transition: all 0.3s ease;
            border-radius: 16px;
            border: 1px solid #e9ecef;
        }
        .user-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
        }
        .avatar-mentor {
            background: linear-gradient(135deg, #0d6efd, #0a58ca);
        }
        .avatar-mentee {
            background: linear-gradient(135deg, #28a745, #1e7e34);
        }
        .avatar-suspended {
            background: linear-gradient(135deg, #dc3545, #b02a37);
        }
        .avatar-pending {
            background: linear-gradient(135deg, #ffc107, #d39e00);
        }
        .avatar-rejected {
            background: linear-gradient(135deg, #6c757d, #495057);
        }
        .badge-role-mentor {
            background-color: #0d6efd;
            color: white;
        }
        .badge-role-mentee {
            background-color: #28a745;
            color: white;
        }
        .filter-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 16px;
        }
        .chart-container {
            height: 200px;
        }
        .user-stat-item {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
        }
        .user-stat-item.mentor {
            background: #e3f2fd;
            color: #0d6efd;
        }
        .user-stat-item.mentee {
            background: #d4edda;
            color: #155724;
        }
        .user-stat-item.pending {
            background: #fff3cd;
            color: #856404;
        }
        .user-stat-item.suspended {
            background: #f8d7da;
            color: #721c24;
        }
        .user-stat-item.rejected {
            background: #e2e3e5;
            color: #383d41;
        }
        .rating-stars {
            color: #ffc107;
            font-size: 0.8rem;
        }
        @media (max-width: 768px) {
            .user-avatar {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            .stat-card .stat-icon { font-size: 1.5rem; }
            .chart-container { height: 150px; }
            .user-card .card-body { padding: 12px; }
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
                        <h3><i class="bi bi-people"></i> Users Management</h3>
                        <p class="text-muted">Manage all mentors and mentees on the platform.</p>
                    </div>
                    <div>
                        <a href="../register.php" class="btn btn-success" target="_blank">
                            <i class="bi bi-person-plus"></i> Add New User
                        </a>
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
                    <div class="col-md-3 col-6">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <h6 class="card-title">Total Users</h6>
                                <h2 class="mb-0"><?php echo $total_users; ?></h2>
                                <small>All registered users</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <h6 class="card-title">Active Mentors</h6>
                                <h2 class="mb-0"><?php echo $active_mentors; ?></h2>
                                <small><i class="bi bi-star-fill"></i> Mentors</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <h6 class="card-title">Active Mentees</h6>
                                <h2 class="mb-0"><?php echo $active_mentees; ?></h2>
                                <small><i class="bi bi-person"></i> Mentees</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card stat-card bg-warning text-dark">
                            <div class="card-body">
                                <h6 class="card-title">Pending / Suspended</h6>
                                <h2 class="mb-0"><?php echo $pending_users + $suspended_users; ?></h2>
                                <small><?php echo $pending_users; ?> pending, <?php echo $suspended_users; ?> suspended</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts -->
                <div class="row g-3 mb-4">
                    <div class="col-md-7">
                        <div class="card shadow">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-graph-up"></i> User Growth</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="growthChart" class="chart-container"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="card shadow">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-pie-chart"></i> User Distribution</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="distributionChart" class="chart-container"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Registrations -->
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-clock-history"></i> New Users (Last 7 Days)</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="dailyChart" style="height: 80px;"></canvas>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card shadow mb-4">
                    <div class="card-body filter-section">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">Role</label>
                                <select class="form-select" name="role" onchange="this.form.submit()">
                                    <option value="all" <?php echo $role_filter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                                    <option value="mentor" <?php echo $role_filter === 'mentor' ? 'selected' : ''; ?>>Mentors</option>
                                    <option value="mentee" <?php echo $role_filter === 'mentee' ? 'selected' : ''; ?>>Mentees</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" onchange="this.form.submit()">
                                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" name="search" placeholder="Search by name, email, phone..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-50 me-2">
                                    <i class="bi bi-search"></i> Filter
                                </button>
                                <a href="users.php" class="btn btn-secondary w-50">Clear</a>
                            </div>
                        </form>
                        
                        <!-- Quick status filters -->
                        <div class="mt-3 d-flex flex-wrap gap-2">
                            <span class="user-stat-item mentor" onclick="filterByStatus('mentor')">👨‍🏫 Mentors (<?php echo $active_mentors; ?>)</span>
                            <span class="user-stat-item mentee" onclick="filterByStatus('mentee')">👨‍🎓 Mentees (<?php echo $active_mentees; ?>)</span>
                            <span class="user-stat-item pending" onclick="filterByStatus('pending')">⏳ Pending (<?php echo $pending_users; ?>)</span>
                            <span class="user-stat-item suspended" onclick="filterByStatus('suspended')">🚫 Suspended (<?php echo $suspended_users; ?>)</span>
                            <span class="user-stat-item rejected" onclick="filterByStatus('rejected')">❌ Rejected (<?php echo $rejected_users; ?>)</span>
                        </div>
                    </div>
                </div>
                
                <!-- Users Grid -->
                <div class="row g-3">
                    <?php if ($users): ?>
                        <?php foreach ($users as $u): ?>
                            <div class="col-xl-3 col-lg-4 col-md-6">
                                <div class="card user-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start mb-3">
                                            <div class="user-avatar 
                                                <?php 
                                                    if ($u['status'] === 'active') {
                                                        echo $u['role'] === 'mentor' ? 'avatar-mentor' : 'avatar-mentee';
                                                    } elseif ($u['status'] === 'suspended') {
                                                        echo 'avatar-suspended';
                                                    } elseif ($u['status'] === 'rejected') {
                                                        echo 'avatar-rejected';
                                                    } else {
                                                        echo 'avatar-pending';
                                                    }
                                                ?> 
                                                me-3">
                                                <?php echo strtoupper(substr($u['name'], 0, 1)); ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0"><?php echo htmlspecialchars($u['name']); ?></h6>
                                                <small class="d-block text-muted"><?php echo htmlspecialchars(getUserReference($u['id']), ENT_QUOTES, 'UTF-8'); ?></small>
                                                <span class="badge <?php echo $u['role'] === 'mentor' ? 'badge-role-mentor' : 'badge-role-mentee'; ?>">
                                                    <?php echo ucfirst($u['role']); ?>
                                                </span>
                                                <span class="badge bg-<?php 
                                                    echo $u['status'] === 'active' ? 'success' : 
                                                        ($u['status'] === 'pending' ? 'warning' : 
                                                        ($u['status'] === 'suspended' ? 'danger' : 'secondary')); 
                                                ?>">
                                                    <?php echo ucfirst($u['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="small text-muted">
                                            <div><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($u['email']); ?></div>
                                            <div><i class="bi bi-phone"></i> <?php echo htmlspecialchars($u['phone']); ?></div>
                                            <?php if ($u['programme']): ?>
                                                <div><i class="bi bi-book"></i> <?php echo htmlspecialchars($u['programme']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($u['year_of_study']): ?>
                                                <div><i class="bi bi-graph-up"></i> Year <?php echo $u['year_of_study']; ?></div>
                                            <?php endif; ?>
                                            <div class="mt-1">
                                                <i class="bi bi-calendar-check"></i> Subscription:
                                                <?php if ($u['active_subscription_until']): ?>
                                                    <span class="badge bg-success">Active until <?php echo date('j M Y', strtotime($u['active_subscription_until'])); ?></span>
                                                <?php elseif ($u['latest_subscription_status'] === 'pending'): ?>
                                                    <span class="badge bg-warning text-dark">Awaiting confirmation</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </div>
                                            <div><i class="bi bi-wallet2"></i> Confirmed subscription spending: <?php echo formatCurrency($u['subscription_spending']); ?></div>
                                        </div>
                                        
                                        <hr>
                                        
                                        <!-- Role-specific details -->
                                        <?php if ($u['role'] === 'mentor'): ?>
                                            <div class="row text-center small">
                                                <div class="col-3">
                                                    <div class="fw-bold"><?php echo $u['total_sessions'] ?? 0; ?></div>
                                                    <div class="text-muted">Sessions</div>
                                                </div>
                                                <div class="col-3">
                                                    <div class="fw-bold"><?php echo $u['total_certificates'] ?? 0; ?></div>
                                                    <div class="text-muted">Certificates</div>
                                                </div>
                                                <div class="col-3">
                                                    <div class="fw-bold"><?php echo $u['approved_courses'] ?? 0; ?></div>
                                                    <div class="text-muted">Courses</div>
                                                </div>
                                                <div class="col-3">
                                                    <div class="fw-bold">
                                                        <?php if ($u['avg_rating']): ?>
                                                            <?php echo number_format($u['avg_rating'], 1); ?>
                                                        <?php else: ?>
                                                            N/A
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-muted">Rating</div>
                                                </div>
                                            </div>
                                            <?php if ($u['avg_rating']): ?>
                                                <div class="rating-stars text-center mt-1">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="bi bi-star-fill <?php echo $i <= round($u['avg_rating']) ? 'text-warning' : 'text-muted opacity-25'; ?>"></i>
                                                    <?php endfor; ?>
                                                    <small class="text-muted">(<?php echo $u['total_feedback'] ?? 0; ?> reviews)</small>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="row text-center small">
                                                <div class="col-4">
                                                    <div class="fw-bold"><?php echo $u['total_sessions'] ?? 0; ?></div>
                                                    <div class="text-muted">Sessions</div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="fw-bold"><?php echo $u['completed_sessions'] ?? 0; ?></div>
                                                    <div class="text-muted">Completed</div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="fw-bold"><?php echo $u['enrolled_courses'] ?? 0; ?></div>
                                                    <div class="text-muted">Courses</div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-footer bg-transparent border-top-0">
                                        <div class="d-flex justify-content-between">
                                            <div class="btn-group btn-group-sm">
                                                <?php if ($u['status'] === 'pending'): ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                                        <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                                        <button type="submit" name="action" value="approve" class="btn btn-success" title="Approve and activate account">
                                                            <i class="bi bi-check-circle"></i> Approve
                                                        </button>
                                                    </form>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                                        <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                                        <button type="submit" name="action" value="reject" class="btn btn-outline-danger" title="Reject account" onclick="return confirm('Reject this user account?')">
                                                            <i class="bi bi-x-circle"></i>
                                                        </button>
                                                    </form>
                                                <?php elseif ($u['status'] === 'suspended'): ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                                        <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                                        <button type="submit" name="action" value="activate" class="btn btn-success" title="Reactivate account">
                                                            <i class="bi bi-check-circle"></i>
                                                        </button>
                                                    </form>
                                                <?php elseif ($u['status'] === 'active'): ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                                        <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                                        <button type="submit" name="action" value="suspend" class="btn btn-warning" title="Suspend account" onclick="return confirm('Suspend this user?')">
                                                            <i class="bi bi-pause-circle"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                                    <button type="submit" name="action" value="delete" class="btn btn-danger" title="Delete user" onclick="return confirm('Delete this user? This cannot be undone.')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#userModal"
                                                    data-user='<?php echo json_encode($u); ?>'>
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="text-center py-5">
                                <i class="bi bi-people display-1 text-muted"></i>
                                <p class="text-muted mt-3">No users found matching your criteria.</p>
                                <a href="users.php" class="btn btn-primary">Clear Filters</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- User Details Modal -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="userDetails">
                    <!-- Dynamic content -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // ============================================
        // GROWTH CHART
        // ============================================
        const growthCtx = document.getElementById('growthChart').getContext('2d');
        const growthLabels = <?php echo json_encode(array_keys($monthly_growth)); ?>;
        const growthData = <?php echo json_encode(array_values($monthly_growth)); ?>;
        
        new Chart(growthCtx, {
            type: 'bar',
            data: {
                labels: growthLabels,
                datasets: [{
                    label: 'New Users',
                    data: growthData,
                    backgroundColor: 'rgba(13, 110, 253, 0.6)',
                    borderColor: 'rgba(13, 110, 253, 1)',
                    borderWidth: 2,
                    borderRadius: 4
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
        // DISTRIBUTION CHART
        // ============================================
        const distCtx = document.getElementById('distributionChart').getContext('2d');
        const distLabels = ['Mentors', 'Mentees', 'Pending', 'Suspended', 'Rejected'];
        const distData = [
            <?php echo $role_distribution['mentor']; ?>,
            <?php echo $role_distribution['mentee']; ?>,
            <?php echo $role_distribution['pending']; ?>,
            <?php echo $role_distribution['suspended']; ?>,
            <?php echo $role_distribution['rejected']; ?>
        ];
        const distColors = ['#0d6efd', '#28a745', '#ffc107', '#dc3545', '#6c757d'];
        
        new Chart(distCtx, {
            type: 'doughnut',
            data: {
                labels: distLabels,
                datasets: [{
                    data: distData,
                    backgroundColor: distColors,
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
        
        // ============================================
        // DAILY CHART
        // ============================================
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        const dailyLabels = <?php echo json_encode(array_keys($recent_registrations)); ?>;
        const dailyData = <?php echo json_encode(array_values($recent_registrations)); ?>;
        
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: dailyLabels,
                datasets: [{
                    label: 'Registrations',
                    data: dailyData,
                    backgroundColor: 'rgba(40, 167, 69, 0.2)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(40, 167, 69, 1)',
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
        // FILTER BY STATUS
        // ============================================
        function filterByStatus(status) {
            const url = new URL(window.location.href);
            url.searchParams.set('status', status);
            url.searchParams.set('role', 'all');
            window.location.href = url.toString();
        }
        
        // ============================================
        // USER MODAL
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            const userModal = document.getElementById('userModal');
            userModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const user = JSON.parse(button.dataset.user);
                
                const ratingStars = user.avg_rating ? 
                    Array.from({length: 5}, (_, i) => 
                        `<i class="bi bi-star-fill ${i < Math.round(user.avg_rating) ? 'text-warning' : 'text-muted opacity-25'}"></i>`
                    ).join('') : 'N/A';
                
                const html = `
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <div class="user-avatar 
                                ${user.status === 'active' ? (user.role === 'mentor' ? 'avatar-mentor' : 'avatar-mentee') : 
                                (user.status === 'suspended' ? 'avatar-suspended' : 
                                (user.status === 'rejected' ? 'avatar-rejected' : 'avatar-pending'))} 
                                mx-auto mb-3" 
                                style="width: 100px; height: 100px; font-size: 2.5rem;">
                                ${user.name.charAt(0).toUpperCase()}
                            </div>
                            <h5>${user.name}</h5>
                            <span class="badge ${user.role === 'mentor' ? 'badge-role-mentor' : 'badge-role-mentee'}">${user.role}</span>
                            <span class="badge bg-${user.status === 'active' ? 'success' : (user.status === 'pending' ? 'warning' : 'danger')}">${user.status}</span>
                            <div class="mt-2">
                                <small class="text-muted">Joined: ${new Date(user.created_at).toLocaleDateString()}</small>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <table class="table table-borderless">
                                <tr><td><strong>Email:</strong></td><td>${user.email}</td></tr>
                                <tr><td><strong>Phone:</strong></td><td>${user.phone || 'N/A'}</td></tr>
                                <tr><td><strong>Programme:</strong></td><td>${user.programme || 'N/A'}</td></tr>
                                <tr><td><strong>Year of Study:</strong></td><td>${user.year_of_study || 'N/A'}</td></tr>
                                ${user.role === 'mentor' ? `
                                    <tr><td><strong>Total Sessions:</strong></td><td>${user.total_sessions || 0}</td></tr>
                                    <tr><td><strong>Completed Sessions:</strong></td><td>${user.completed_sessions || 0}</td></tr>
                                    <tr><td><strong>Average Rating:</strong></td><td>${ratingStars} (${user.total_feedback || 0} reviews)</td></tr>
                                    <tr><td><strong>Approved Courses:</strong></td><td>${user.approved_courses || 0}</td></tr>
                                    <tr><td><strong>Total Certificates:</strong></td><td>${user.total_certificates || 0}</td></tr>
                                    <tr><td><strong>Active Semester:</strong></td><td>${user.active_semester || 'N/A'}</td></tr>
                                ` : `
                                    <tr><td><strong>Total Sessions:</strong></td><td>${user.total_sessions || 0}</td></tr>
                                    <tr><td><strong>Completed Sessions:</strong></td><td>${user.completed_sessions || 0}</td></tr>
                                    <tr><td><strong>Enrolled Courses:</strong></td><td>${user.enrolled_courses || 0}</td></tr>
                                `}
                            </table>
                        </div>
                    </div>
                `;
                
                document.getElementById('userDetails').innerHTML = html;
            });
        });
    </script>
</body>
</html>