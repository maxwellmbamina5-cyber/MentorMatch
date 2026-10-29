<?php
// admin/logs.php
require_once dirname(__DIR__) . '/includes/functions.php';
requireAdmin();

$user = getCurrentUser();
$error = '';
$success = '';

// Handle log clearing
if (isset($_GET['clear']) && $_GET['clear'] === 'all') {
    $stmt = $pdo->prepare("TRUNCATE TABLE system_logs");
    $stmt->execute();
    $success = 'All logs cleared successfully!';
    logActivity($user['id'], 'Cleared all system logs');
}

// Handle bulk delete
if (isset($_GET['bulk_delete']) && isset($_GET['ids'])) {
    $ids = explode(',', $_GET['ids']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("DELETE FROM system_logs WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $success = count($ids) . ' log entries deleted successfully!';
    logActivity($user['id'], 'Bulk deleted ' . count($ids) . ' log entries');
}

// Handle individual log deletion
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM system_logs WHERE id = ?");
    $stmt->execute([$id]);
    $success = 'Log entry deleted successfully!';
    logActivity($user['id'], 'Deleted log entry #' . $id);
}

// Get filter parameters
$filter_user = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$filter_action = isset($_GET['action']) ? $_GET['action'] : '';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Pagination
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Build query
$query = "
    SELECT l.*, u.name as user_name, u.email as user_email, u.role as user_role
    FROM system_logs l
    LEFT JOIN users u ON l.user_id = u.id
    WHERE 1=1
";

$params = [];

if ($filter_user > 0) {
    $query .= " AND l.user_id = ?";
    $params[] = $filter_user;
}

if (!empty($filter_action)) {
    $query .= " AND l.action LIKE ?";
    $params[] = "%$filter_action%";
}

if (!empty($filter_date)) {
    $query .= " AND DATE(l.created_at) = ?";
    $params[] = $filter_date;
}

if (!empty($search)) {
    $query .= " AND (l.action LIKE ? OR l.details LIKE ? OR u.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Filter by log type
if ($filter_type === 'user') {
    $query .= " AND u.role = 'mentee'";
} elseif ($filter_type === 'mentor') {
    $query .= " AND u.role = 'mentor'";
} elseif ($filter_type === 'admin') {
    $query .= " AND u.role = 'admin'";
} elseif ($filter_type === 'system') {
    $query .= " AND u.id IS NULL";
}

// Get total count for pagination
$count_query = str_replace("l.*, u.name as user_name, u.email as user_email, u.role as user_role", "COUNT(*) as total", $query);
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_logs = $stmt->fetchColumn();
$total_pages = ceil($total_logs / $limit);

// Get logs with pagination
$query .= " ORDER BY l.created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get users for filter
$stmt = $pdo->query("
    SELECT DISTINCT u.id, u.name, u.email, u.role
    FROM users u
    JOIN system_logs l ON u.id = l.user_id
    ORDER BY u.name
");
$users = $stmt->fetchAll();

// Get action types for filter
$stmt = $pdo->query("
    SELECT DISTINCT action, COUNT(*) as count
    FROM system_logs 
    GROUP BY action
    ORDER BY action
");
$action_types = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM system_logs");
$total_entries = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) as total FROM system_logs WHERE user_id IS NOT NULL");
$unique_users = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(DISTINCT DATE(created_at)) as total FROM system_logs");
$active_days = $stmt->fetchColumn();

// Today's logs
$stmt = $pdo->query("SELECT COUNT(*) as total FROM system_logs WHERE created_at::date = CURRENT_DATE");
$today_logs = $stmt->fetchColumn();

// User activity breakdown
$stmt = $pdo->query("
    SELECT u.role, COUNT(l.id) as log_count
    FROM system_logs l
    LEFT JOIN users u ON l.user_id = u.id
    GROUP BY u.role
    ORDER BY log_count DESC
");
$role_breakdown = $stmt->fetchAll();

// Get log activity by hour (last 24 hours)
$hourly_data = [];
for ($i = 23; $i >= 0; $i--) {
    $hour = date('H', strtotime("-$i hours"));
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM system_logs WHERE EXTRACT(HOUR FROM created_at) = ? AND created_at::date = CURRENT_DATE");
    $stmt->execute([$hour]);
    $hourly_data[$hour] = (int)$stmt->fetchColumn();
}

// Get activity by day (last 7 days)
$daily_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $day_label = date('D', strtotime("-$i days"));
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM system_logs WHERE DATE(created_at) = ?");
    $stmt->execute([$date]);
    $daily_data[$day_label] = (int)$stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - Admin</title>
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
        .log-entry {
            transition: background 0.2s ease;
        }
        .log-entry:hover {
            background-color: #f8f9fa;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
        }
        .stat-label {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.7;
        }
        .log-details {
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 4px;
            margin-top: 4px;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .action-badge {
            font-size: 0.7rem;
            padding: 2px 10px;
            border-radius: 12px;
            white-space: nowrap;
        }
        .action-badge.user {
            background: #e3f2fd;
            color: #0d6efd;
        }
        .action-badge.admin {
            background: #fce4ec;
            color: #dc3545;
        }
        .action-badge.system {
            background: #e8f5e9;
            color: #198754;
        }
        .action-badge.payment {
            background: #fff3e0;
            color: #fd7e14;
        }
        .action-badge.session {
            background: #f3e5f5;
            color: #6f42c1;
        }
        .action-badge.certificate {
            background: #e8f5e9;
            color: #198754;
        }
        .action-badge.registration {
            background: #e3f2fd;
            color: #0d6efd;
        }
        .filter-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 16px;
        }
        .chart-container {
            height: 150px;
        }
        .role-badge {
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
        }
        .role-badge.mentor {
            background: #0d6efd;
            color: white;
        }
        .role-badge.mentee {
            background: #28a745;
            color: white;
        }
        .role-badge.admin {
            background: #dc3545;
            color: white;
        }
        .role-badge.system {
            background: #6c757d;
            color: white;
        }
        .log-type-filter {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 10px;
        }
        .log-type-filter .filter-btn {
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            border: 2px solid #dee2e6;
            background: white;
            color: #6c757d;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .log-type-filter .filter-btn.active {
            border-color: #0d6efd;
            background: #e7f1ff;
            color: #0d6efd;
        }
        .log-type-filter .filter-btn:hover {
            background: #f8f9fa;
        }
        @media (max-width: 768px) {
            .stat-card .stat-icon { font-size: 1.5rem; }
            .stat-number { font-size: 1.5rem; }
            .chart-container { height: 120px; }
            .table-responsive { font-size: 0.8rem; }
            .action-badge { font-size: 0.6rem; padding: 2px 6px; }
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
                        <h3><i class="bi bi-clipboard-data"></i> System Logs</h3>
                        <p class="text-muted">View all system activities and user actions.</p>
                    </div>
                    <div>
                        <?php if ($total_logs > 0): ?>
                            <a href="logs.php?clear=all" class="btn btn-danger btn-sm" 
                               onclick="return confirm('Are you sure you want to clear ALL logs? This cannot be undone.')">
                                <i class="bi bi-trash3"></i> Clear All
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
                    <div class="col-md-3 col-6">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <div class="stat-label">Total Entries</div>
                                <div class="stat-number"><?php echo number_format($total_entries); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <div class="stat-label">Unique Users</div>
                                <div class="stat-number"><?php echo $unique_users; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <div class="stat-label">Active Days</div>
                                <div class="stat-number"><?php echo $active_days; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card stat-card bg-warning text-dark">
                            <div class="card-body">
                                <div class="stat-label">Today's Logs</div>
                                <div class="stat-number"><?php echo $today_logs; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Activity Charts -->
                <div class="row g-3 mb-4">
                    <div class="col-md-8">
                        <div class="card shadow">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-graph-up"></i> Activity by Hour (Today)</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="hourlyChart" class="chart-container"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card shadow">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-pie-chart"></i> User Activity</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="roleChart" class="chart-container"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Daily Activity -->
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-calendar-week"></i> Activity (Last 7 Days)</h6>
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
                                <label class="form-label">User</label>
                                <select class="form-select" name="user" onchange="this.form.submit()">
                                    <option value="0">All Users</option>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?php echo $u['id']; ?>" <?php echo $filter_user == $u['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($u['name']); ?>
                                            (<?php echo ucfirst($u['role'] ?? 'user'); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Action</label>
                                <select class="form-select" name="action" onchange="this.form.submit()">
                                    <option value="">All Actions</option>
                                    <?php foreach ($action_types as $action): ?>
                                        <option value="<?php echo htmlspecialchars($action['action']); ?>" <?php echo $filter_action == $action['action'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($action['action']); ?>
                                            (<?php echo $action['count']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Date</label>
                                <input type="date" class="form-control" name="date" value="<?php echo $filter_date; ?>" onchange="this.form.submit()">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" name="search" placeholder="Search logs..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary w-50">Filter</button>
                                <a href="logs.php" class="btn btn-secondary w-50">Clear</a>
                            </div>
                        </form>
                        
                        <!-- Log Type Filter -->
                        <div class="log-type-filter mt-3">
                            <span class="filter-btn <?php echo $filter_type === 'all' ? 'active' : ''; ?>" onclick="setLogType('all')">All</span>
                            <span class="filter-btn <?php echo $filter_type === 'admin' ? 'active' : ''; ?>" onclick="setLogType('admin')">👤 Admin</span>
                            <span class="filter-btn <?php echo $filter_type === 'mentor' ? 'active' : ''; ?>" onclick="setLogType('mentor')">👨‍🏫 Mentor</span>
                            <span class="filter-btn <?php echo $filter_type === 'user' ? 'active' : ''; ?>" onclick="setLogType('user')">👨‍🎓 Mentee</span>
                            <span class="filter-btn <?php echo $filter_type === 'system' ? 'active' : ''; ?>" onclick="setLogType('system')">⚙️ System</span>
                        </div>
                    </div>
                </div>
                
                <!-- Logs Table -->
                <div class="card shadow">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-list-ul"></i> Log Entries</h6>
                        <span class="badge bg-secondary"><?php echo number_format($total_logs); ?> entries</span>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($logs): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 50px;">#</th>
                                            <th>User</th>
                                            <th style="min-width: 120px;">Action</th>
                                            <th>IP Address</th>
                                            <th>Details</th>
                                            <th>Time</th>
                                            <th style="width: 80px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($logs as $log): ?>
                                        <tr class="log-entry">
                                            <td><?php echo $log['id']; ?></td>
                                            <td>
                                                <?php if ($log['user_name']): ?>
                                                    <strong><?php echo htmlspecialchars($log['user_name']); ?></strong>
                                                    <br>
                                                    <span class="role-badge <?php echo $log['user_role'] ?? 'system'; ?>">
                                                        <?php echo ucfirst($log['user_role'] ?? 'System'); ?>
                                                    </span>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($log['user_email'] ?? 'N/A'); ?></small>
                                                <?php else: ?>
                                                    <span class="role-badge system">System</span>
                                                    <br><small class="text-muted">User #<?php echo $log['user_id']; ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    $action_class = 'secondary';
                                                    $action_lower = strtolower($log['action']);
                                                    if (strpos($action_lower, 'login') !== false || strpos($action_lower, 'logout') !== false) {
                                                        $action_class = 'user';
                                                    } elseif (strpos($action_lower, 'admin') !== false || strpos($action_lower, 'approve') !== false || strpos($action_lower, 'reject') !== false) {
                                                        $action_class = 'admin';
                                                    } elseif (strpos($action_lower, 'payment') !== false || strpos($action_lower, 'payout') !== false || strpos($action_lower, 'transaction') !== false) {
                                                        $action_class = 'payment';
                                                    } elseif (strpos($action_lower, 'session') !== false || strpos($action_lower, 'booking') !== false) {
                                                        $action_class = 'session';
                                                    } elseif (strpos($action_lower, 'system') !== false || strpos($action_lower, 'cron') !== false || strpos($action_lower, 'backup') !== false) {
                                                        $action_class = 'system';
                                                    } elseif (strpos($action_lower, 'certificate') !== false || strpos($action_lower, 'accredit') !== false) {
                                                        $action_class = 'certificate';
                                                    } elseif (strpos($action_lower, 'register') !== false || strpos($action_lower, 'registration') !== false) {
                                                        $action_class = 'registration';
                                                    }
                                                ?>
                                                <span class="action-badge <?php echo $action_class; ?>">
                                                    <?php echo htmlspecialchars($log['action']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <code><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></code>
                                            </td>
                                            <td>
                                                <?php if ($log['details']): ?>
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#detailsModal"
                                                            data-details="<?php echo htmlspecialchars($log['details']); ?>">
                                                        <i class="bi bi-eye"></i> View
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo date('d M Y', strtotime($log['created_at'])); ?>
                                                <br><small class="text-muted"><?php echo date('h:i:s A', strtotime($log['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <a href="logs.php?delete=<?php echo $log['id']; ?>" 
                                                   class="btn btn-sm btn-outline-danger"
                                                   onclick="return confirm('Delete this log entry?')">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav class="mt-3 px-3">
                                    <ul class="pagination justify-content-center">
                                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?><?php echo $filter_user ? '&user=' . $filter_user : ''; ?><?php echo $filter_action ? '&action=' . urlencode($filter_action) : ''; ?><?php echo $filter_date ? '&date=' . $filter_date : ''; ?><?php echo $filter_type !== 'all' ? '&type=' . $filter_type : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Previous</a>
                                        </li>
                                        <?php 
                                        $start_page = max(1, $page - 2);
                                        $end_page = min($total_pages, $page + 2);
                                        if ($start_page > 1): ?>
                                            <li class="page-item"><a class="page-link" href="?page=1&limit=<?php echo $limit; ?>">1</a></li>
                                            <?php if ($start_page > 2): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&limit=<?php echo $limit; ?><?php echo $filter_user ? '&user=' . $filter_user : ''; ?><?php echo $filter_action ? '&action=' . urlencode($filter_action) : ''; ?><?php echo $filter_date ? '&date=' . $filter_date : ''; ?><?php echo $filter_type !== 'all' ? '&type=' . $filter_type : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <?php if ($end_page < $total_pages): ?>
                                            <?php if ($end_page < $total_pages - 1): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                            <?php endif; ?>
                                            <li class="page-item"><a class="page-link" href="?page=<?php echo $total_pages; ?>&limit=<?php echo $limit; ?>"><?php echo $total_pages; ?></a></li>
                                        <?php endif; ?>
                                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?><?php echo $filter_user ? '&user=' . $filter_user : ''; ?><?php echo $filter_action ? '&action=' . urlencode($filter_action) : ''; ?><?php echo $filter_date ? '&date=' . $filter_date : ''; ?><?php echo $filter_type !== 'all' ? '&type=' . $filter_type : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Next</a>
                                        </li>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                            
                            <div class="text-center text-muted small py-2">
                                Showing <?php echo count($logs); ?> of <?php echo number_format($total_logs); ?> entries
                                (Page <?php echo $page; ?> of <?php echo $total_pages; ?>)
                            </div>
                            
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-clipboard-x display-1 text-muted"></i>
                                <p class="text-muted mt-3">No logs found matching your criteria.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Log Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="log-details" id="modalDetails"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // ============================================
        // HOURLY ACTIVITY CHART
        // ============================================
        const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
        const hourlyLabels = <?php echo json_encode(array_keys($hourly_data)); ?>;
        const hourlyData = <?php echo json_encode(array_values($hourly_data)); ?>;
        
        new Chart(hourlyCtx, {
            type: 'bar',
            data: {
                labels: hourlyLabels.map(h => h + ':00'),
                datasets: [{
                    label: 'Log Entries',
                    data: hourlyData,
                    backgroundColor: 'rgba(13, 110, 253, 0.6)',
                    borderColor: 'rgba(13, 110, 253, 1)',
                    borderWidth: 1
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
        // ROLE BREAKDOWN CHART
        // ============================================
        const roleCtx = document.getElementById('roleChart').getContext('2d');
        const roleLabels = <?php echo json_encode(array_column($role_breakdown, 'role')); ?>;
        const roleData = <?php echo json_encode(array_column($role_breakdown, 'log_count')); ?>;
        const roleColors = {
            'admin': '#dc3545',
            'mentor': '#0d6efd',
            'mentee': '#28a745',
            NULL: '#6c757d'
        };
        
        new Chart(roleCtx, {
            type: 'doughnut',
            data: {
                labels: roleLabels.map(r => r ? ucfirst(r) : 'System'),
                datasets: [{
                    data: roleData,
                    backgroundColor: roleLabels.map(r => roleColors[r] || '#6c757d'),
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
                            padding: 8,
                            font: { size: 10 }
                        }
                    }
                }
            }
        });
        
        // ============================================
        // DAILY ACTIVITY CHART
        // ============================================
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        const dailyLabels = <?php echo json_encode(array_keys($daily_data)); ?>;
        const dailyData = <?php echo json_encode(array_values($daily_data)); ?>;
        
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: dailyLabels,
                datasets: [{
                    label: 'Activity',
                    data: dailyData,
                    backgroundColor: 'rgba(13, 110, 253, 0.2)',
                    borderColor: 'rgba(13, 110, 253, 1)',
                    borderWidth: 2,
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
        // HELPER FUNCTIONS
        // ============================================
        function ucfirst(str) {
            if (!str) return str;
            return str.charAt(0).toUpperCase() + str.slice(1);
        }
        
        function setLogType(type) {
            // Update URL with type parameter
            const url = new URL(window.location.href);
            url.searchParams.set('type', type);
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        }
        
        // ============================================
        // DETAILS MODAL
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            const detailsModal = document.getElementById('detailsModal');
            detailsModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const details = button.dataset.details || 'No additional details';
                document.getElementById('modalDetails').textContent = details;
            });
        });
        
        // ============================================
        // KEYBOARD SHORTCUTS
        // ============================================
        document.addEventListener('keydown', function(e) {
            // Ctrl + F to focus search
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                const searchInput = document.querySelector('input[name="search"]');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }
        });
    </script>
</body>
</html>