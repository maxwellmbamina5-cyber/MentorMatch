<?php
// admin/courses.php - Admin Course Enrollment Management
require_once dirname(__DIR__, 2) . '/backend/includes/functions.php';
requireAdmin();

$user = getCurrentUser();
$error = '';
$success = '';

// Handle course approval/rejection
if (isset($_GET['action']) && isset($_GET['id'])) {
    $enrollment_id = (int)$_GET['id'];
    $action = $_GET['action'];
    
    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE mentee_enrollments SET status = 'active', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
        $stmt->execute([$user['id'], $enrollment_id]);
        $success = 'Course enrollment approved successfully!';
        logActivity($user['id'], 'Approved course enrollment #' . $enrollment_id);
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE mentee_enrollments SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
        $stmt->execute([$user['id'], $enrollment_id]);
        $success = 'Course enrollment rejected.';
        logActivity($user['id'], 'Rejected course enrollment #' . $enrollment_id);
    }
}

// Get all course enrollments
$stmt = $pdo->prepare("
    SELECT me.*, u.name as mentee_name, u.email as mentee_email, u.phone as mentee_phone,
           a.name as reviewer_name,
           (SELECT COUNT(*) FROM sessions WHERE mentee_id = me.mentee_id AND course_code = me.course_code) as total_sessions
    FROM mentee_enrollments me
    JOIN users u ON me.mentee_id = u.id
    LEFT JOIN users a ON me.reviewed_by = a.id
    ORDER BY me.created_at DESC
");
$stmt->execute();
$enrollments = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM mentee_enrollments");
$total_enrollments = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total FROM mentee_enrollments WHERE status = 'pending'");
$pending_enrollments = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total FROM mentee_enrollments WHERE status = 'active'");
$active_enrollments = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total FROM mentee_enrollments WHERE status = 'rejected'");
$rejected_enrollments = $stmt->fetchColumn();

// Get unique mentees with enrollments
$stmt = $pdo->query("SELECT COUNT(DISTINCT mentee_id) as total FROM mentee_enrollments");
$unique_mentees = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Enrollments - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/mobile.css" rel="stylesheet">
    <style>
        .stat-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: default;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        .enrollment-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        .enrollment-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .enrollment-card.pending {
            border-left-color: #ffc107;
            background-color: #fff8e1;
        }
        .enrollment-card.active {
            border-left-color: #28a745;
            background-color: #f8fff8;
        }
        .enrollment-card.rejected {
            border-left-color: #dc3545;
            background-color: #fff8f8;
        }
        @media (max-width: 768px) {
            .stat-card .stat-icon { font-size: 1.5rem; }
            .table-responsive { font-size: 0.85rem; }
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h3><i class="bi bi-book"></i> Course Enrollments</h3>
                        <p class="text-muted">Manage mentee course enrollments and approvals.</p>
                    </div>
                    <div>
                        <span class="badge bg-warning text-dark fs-6"><?php echo $pending_enrollments; ?> Pending</span>
                    </div>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <!-- Statistics -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3 col-6">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <h6 class="card-title">Total</h6>
                                <h2 class="mb-0"><?php echo $total_enrollments; ?></h2>
                                <small><?php echo $unique_mentees; ?> mentees</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card stat-card bg-warning text-dark">
                            <div class="card-body">
                                <h6 class="card-title">Pending</h6>
                                <h2 class="mb-0"><?php echo $pending_enrollments; ?></h2>
                                <small>Awaiting review</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <h6 class="card-title">Active</h6>
                                <h2 class="mb-0"><?php echo $active_enrollments; ?></h2>
                                <small>Approved</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card stat-card bg-danger text-white">
                            <div class="card-body">
                                <h6 class="card-title">Rejected</h6>
                                <h2 class="mb-0"><?php echo $rejected_enrollments; ?></h2>
                                <small>Declined</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Enrollments Table -->
                <div class="card shadow">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-list-ul"></i> All Enrollments</h6>
                        <span class="badge bg-secondary"><?php echo count($enrollments); ?> entries</span>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($enrollments): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Mentee</th>
                                            <th>Course</th>
                                            <th>Sessions</th>
                                            <th>Status</th>
                                            <th>Applied</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($enrollments as $enrollment): ?>
                                            <?php
                                            $status_class = $enrollment['status'] === 'active' ? 'success' : 
                                                            ($enrollment['status'] === 'pending' ? 'warning' : 'danger');
                                            ?>
                                            <tr class="enrollment-card <?php echo $enrollment['status']; ?>">
                                                <td><?php echo $enrollment['id']; ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($enrollment['mentee_name']); ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($enrollment['mentee_email']); ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($enrollment['course_code']); ?></strong>
                                                    <?php if ($enrollment['course_name']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($enrollment['course_name']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $enrollment['total_sessions'] ?? 0; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $status_class; ?>">
                                                        <?php echo ucfirst($enrollment['status']); ?>
                                                    </span>
                                                    <?php if ($enrollment['reviewer_name']): ?>
                                                        <br><small class="text-muted">By: <?php echo htmlspecialchars($enrollment['reviewer_name']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('d M Y', strtotime($enrollment['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($enrollment['status'] === 'pending'): ?>
                                                        <a href="courses.php?action=approve&id=<?php echo $enrollment['id']; ?>" 
                                                           class="btn btn-sm btn-success"
                                                           onclick="return confirm('Approve this course enrollment?')">
                                                            <i class="bi bi-check"></i> Approve
                                                        </a>
                                                        <br>
                                                        <a href="courses.php?action=reject&id=<?php echo $enrollment['id']; ?>" 
                                                           class="btn btn-sm btn-danger mt-1"
                                                           onclick="return confirm('Reject this course enrollment?')">
                                                            <i class="bi bi-x"></i> Reject
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-book display-1 text-muted"></i>
                                <p class="text-muted mt-3">No course enrollments yet.</p>
                                <p class="text-muted small">Mentees will appear here when they add courses.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>