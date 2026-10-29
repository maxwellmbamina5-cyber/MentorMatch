<?php
// admin/applications.php
require_once dirname(__DIR__, 2) . '/backend/includes/functions.php';
requireAdmin();

$user = getCurrentUser();
$error = '';
$success = '';

// Handle application approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $application_id = (int)$_POST['application_id'];
    $action = $_POST['action'];
    $comments = sanitize($_POST['comments'] ?? '');
    $type = $_POST['type'] ?? 'mentor';
    $send_email = isset($_POST['send_email']) ? 1 : 0;
    
    if ($action === 'approve') {
        if ($type === 'mentor') {
            // Get application details
            $stmt = $pdo->prepare("SELECT * FROM mentor_applications WHERE id = ?");
            $stmt->execute([$application_id]);
            $app = $stmt->fetch();
            
            if ($app) {
                // Update user status to active
                $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                $stmt->execute([$app['mentor_id']]);
                
                // Update application status
                $stmt = $pdo->prepare("UPDATE mentor_applications SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(), comments = ? WHERE id = ?");
                $stmt->execute([$user['id'], $comments, $application_id]);
                
                // Send email notification
                if ($send_email) {
                    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
                    $stmt->execute([$app['mentor_id']]);
                    $mentor = $stmt->fetch();
                    
                    if ($mentor) {
                        sendApprovalEmail($mentor['name'], $mentor['email'], 'Mentor');
                    }
                }
                
                logActivity($user['id'], 'Approved mentor application #' . $application_id);
                $success = 'Mentor application approved successfully!';
            }
        } else {
            // Approve mentee
            $stmt = $pdo->prepare("SELECT * FROM mentee_enrollments WHERE id = ?");
            $stmt->execute([$application_id]);
            $app = $stmt->fetch();
            
            if ($app) {
                $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                $stmt->execute([$app['mentee_id']]);
                
                $stmt = $pdo->prepare("UPDATE mentee_enrollments SET status = 'active', reviewed_by = ?, reviewed_at = NOW(), comments = ? WHERE id = ?");
                $stmt->execute([$user['id'], $comments, $application_id]);
                
                // Send email notification
                if ($send_email) {
                    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
                    $stmt->execute([$app['mentee_id']]);
                    $mentee = $stmt->fetch();
                    
                    if ($mentee) {
                        sendApprovalEmail($mentee['name'], $mentee['email'], 'Mentee');
                    }
                }
                
                logActivity($user['id'], 'Approved mentee enrollment #' . $application_id);
                $success = 'Mentee enrollment approved successfully!';
            }
        }
    } elseif ($action === 'reject') {
        if ($type === 'mentor') {
            $stmt = $pdo->prepare("UPDATE mentor_applications SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), comments = ? WHERE id = ?");
            $stmt->execute([$user['id'], $comments, $application_id]);
            
            $stmt = $pdo->prepare("SELECT mentor_id FROM mentor_applications WHERE id = ?");
            $stmt->execute([$application_id]);
            $app = $stmt->fetch();
            
            if ($app) {
                $stmt = $pdo->prepare("UPDATE users SET status = 'rejected' WHERE id = ?");
                $stmt->execute([$app['mentor_id']]);
                
                // Send email notification
                if ($send_email) {
                    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
                    $stmt->execute([$app['mentor_id']]);
                    $mentor = $stmt->fetch();
                    
                    if ($mentor) {
                        sendRejectionEmail($mentor['name'], $mentor['email'], $comments);
                    }
                }
            }
            
            logActivity($user['id'], 'Rejected mentor application #' . $application_id);
            $success = 'Mentor application rejected.';
        } else {
            $stmt = $pdo->prepare("UPDATE mentee_enrollments SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), comments = ? WHERE id = ?");
            $stmt->execute([$user['id'], $comments, $application_id]);
            
            $stmt = $pdo->prepare("SELECT mentee_id FROM mentee_enrollments WHERE id = ?");
            $stmt->execute([$application_id]);
            $app = $stmt->fetch();
            
            if ($app) {
                $stmt = $pdo->prepare("UPDATE users SET status = 'rejected' WHERE id = ?");
                $stmt->execute([$app['mentee_id']]);
                
                // Send email notification
                if ($send_email) {
                    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
                    $stmt->execute([$app['mentee_id']]);
                    $mentee = $stmt->fetch();
                    
                    if ($mentee) {
                        sendRejectionEmail($mentee['name'], $mentee['email'], $comments);
                    }
                }
            }
            
            logActivity($user['id'], 'Rejected mentee enrollment #' . $application_id);
            $success = 'Mentee enrollment rejected.';
        }
    }
}

// Bulk action - Approve all
if (isset($_GET['bulk_approve']) && $_GET['bulk_approve'] === 'mentors') {
    $stmt = $pdo->prepare("
        SELECT ma.id, ma.mentor_id, u.name, u.email
        FROM mentor_applications ma
        JOIN users u ON ma.mentor_id = u.id
        WHERE ma.status = 'pending'
    ");
    $stmt->execute();
    $pending = $stmt->fetchAll();
    
    $count = 0;
    foreach ($pending as $app) {
        $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $stmt->execute([$app['mentor_id']]);
        
        $stmt = $pdo->prepare("UPDATE mentor_applications SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
        $stmt->execute([$user['id'], $app['id']]);
        $count++;
    }
    
    logActivity($user['id'], "Bulk approved $count mentor applications");
    $success = "Successfully approved $count mentor applications!";
}

if (isset($_GET['bulk_approve']) && $_GET['bulk_approve'] === 'mentees') {
    $stmt = $pdo->prepare("
        SELECT me.id, me.mentee_id, u.name, u.email
        FROM mentee_enrollments me
        JOIN users u ON me.mentee_id = u.id
        WHERE me.status = 'pending'
    ");
    $stmt->execute();
    $pending = $stmt->fetchAll();
    
    $count = 0;
    foreach ($pending as $app) {
        $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $stmt->execute([$app['mentee_id']]);
        
        $stmt = $pdo->prepare("UPDATE mentee_enrollments SET status = 'active', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
        $stmt->execute([$user['id'], $app['id']]);
        $count++;
    }
    
    logActivity($user['id'], "Bulk approved $count mentee applications");
    $success = "Successfully approved $count mentee applications!";
}

// ============================================
// GET ALL PENDING APPLICATIONS
// ============================================

// Get all pending mentor applications AND mentors without applications
$stmt = $pdo->prepare("
    SELECT 
        u.id as user_id,
        u.name,
        u.email,
        u.phone,
        u.programme,
        u.year_of_study,
        u.created_at,
        ma.id as application_id,
        ma.course_code,
        ma.course_name,
        ma.grade,
        ma.status as app_status,
        (SELECT COUNT(*) FROM sessions WHERE mentor_id = u.id AND status = 'completed') as total_sessions,
        (SELECT AVG(rating) FROM feedback WHERE mentor_id = u.id) as avg_rating,
        CASE WHEN ma.id IS NULL THEN 'No Application' ELSE 'Has Application' END as app_exists
    FROM users u
    LEFT JOIN mentor_applications ma ON u.id = ma.mentor_id
    WHERE u.role = 'mentor' 
      AND u.status = 'pending'
    ORDER BY u.created_at ASC
");
$stmt->execute();
$pending_mentors = $stmt->fetchAll();

// Get all pending mentee enrollments AND mentees without enrollments
$stmt = $pdo->prepare("
    SELECT 
        u.id as user_id,
        u.name,
        u.email,
        u.phone,
        u.programme,
        u.year_of_study,
        u.created_at,
        me.id as enrollment_id,
        me.course_code,
        me.course_name,
        me.status as app_status,
        CASE WHEN me.id IS NULL THEN 'No Enrollment' ELSE 'Has Enrollment' END as app_exists
    FROM users u
    LEFT JOIN mentee_enrollments me ON u.id = me.mentee_id
    WHERE u.role = 'mentee' 
      AND u.status = 'pending'
    ORDER BY u.created_at ASC
");
$stmt->execute();
$pending_mentees = $stmt->fetchAll();

// Get approved applications (for history)
$stmt = $pdo->prepare("
    SELECT ma.*, u.name, u.email, 
           a.name as reviewer_name
    FROM mentor_applications ma
    JOIN users u ON ma.mentor_id = u.id
    LEFT JOIN users a ON ma.reviewed_by = a.id
    WHERE ma.status != 'pending'
    ORDER BY ma.reviewed_at DESC
    LIMIT 20
");
$stmt->execute();
$approved_applications = $stmt->fetchAll();

// Get rejected applications
$stmt = $pdo->prepare("
    SELECT ma.*, u.name, u.email, 
           a.name as reviewer_name
    FROM mentor_applications ma
    JOIN users u ON ma.mentor_id = u.id
    LEFT JOIN users a ON ma.reviewed_by = a.id
    WHERE ma.status = 'rejected'
    ORDER BY ma.reviewed_at DESC
    LIMIT 20
");
$stmt->execute();
$rejected_applications = $stmt->fetchAll();

// Get active semester
$stmt = $pdo->query("SELECT * FROM semesters WHERE status = 'active' ORDER BY id DESC LIMIT 1");
$active_semester = $stmt->fetch();

// Get all semesters for filter
$stmt = $pdo->query("SELECT id, semester_name, status FROM semesters ORDER BY start_date DESC");
$all_semesters = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'pending'");
$pending_users_count = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
$active_users_count = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'mentor' AND status = 'active'");
$active_mentors_count = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'mentee' AND status = 'active'");
$active_mentees_count = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total FROM mentor_applications WHERE status = 'pending'");
$pending_mentor_apps = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total FROM mentee_enrollments WHERE status = 'pending'");
$pending_mentee_apps = $stmt->fetchColumn();

// Check for orphan users (pending but no application)
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

// Get application trend (last 7 days)
$app_trend = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $day_label = date('D', strtotime("-$i days"));
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM mentor_applications 
        WHERE DATE(created_at) = ? AND status = 'pending'
    ");
    $stmt->execute([$date]);
    $app_trend[$day_label] = (int)$stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications - Admin</title>
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
        .application-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            border-radius: 12px;
        }
        .application-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .application-card.pending-mentor {
            border-left-color: #0d6efd;
        }
        .application-card.pending-mentee {
            border-left-color: #28a745;
        }
        .application-card.no-application {
            border-left-color: #ffc107;
            background-color: #fff8e1;
        }
        .application-card.approved {
            border-left-color: #198754;
            background-color: #f8fff8;
        }
        .application-card.rejected {
            border-left-color: #dc3545;
            background-color: #fff8f8;
        }
        .application-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
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
        .avatar-pending {
            background: linear-gradient(135deg, #ffc107, #d39e00);
        }
        .badge-role-mentor {
            background-color: #0d6efd;
            color: white;
        }
        .badge-role-mentee {
            background-color: #28a745;
            color: white;
        }
        .btn-action {
            min-width: 80px;
        }
        .orphan-warning {
            border-left: 4px solid #ffc107;
            background: #fff8e1;
            padding: 10px 15px;
            border-radius: 4px;
        }
        .badge-no-app {
            background-color: #ffc107;
            color: #856404;
        }
        .filter-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 16px;
        }
        .chart-container {
            height: 150px;
        }
        @media (max-width: 768px) {
            .stat-card .stat-icon { font-size: 1.5rem; }
            .application-card .card-body { padding: 12px; }
            .application-avatar { width: 40px; height: 40px; font-size: 1rem; }
            .btn-action { min-width: 60px; font-size: 0.75rem; }
            .chart-container { height: 120px; }
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
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                    <div>
                        <h3><i class="bi bi-file-earmark-check"></i> Applications</h3>
                        <p class="text-muted">Review and manage mentor and mentee applications.</p>
                    </div>
                    <div>
                        <span class="badge bg-primary fs-6 me-2">
                            <i class="bi bi-clock"></i> Pending: <?php echo count($pending_mentors) + count($pending_mentees); ?>
                        </span>
                        <a href="users.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-people"></i> View All Users
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
                        <div class="card stat-card bg-warning text-dark">
                            <div class="card-body">
                                <h6 class="card-title">Pending Applications</h6>
                                <h2 class="mb-0"><?php echo count($pending_mentors) + count($pending_mentees); ?></h2>
                                <small><?php echo count($pending_mentors); ?> Mentors | <?php echo count($pending_mentees); ?> Mentees</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <h6 class="card-title">Active Users</h6>
                                <h2 class="mb-0"><?php echo $active_users_count; ?></h2>
                                <small><?php echo $active_mentors_count; ?> Mentors | <?php echo $active_mentees_count; ?> Mentees</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <h6 class="card-title">Total Approved</h6>
                                <h2 class="mb-0"><?php 
                                    $stmt = $pdo->query("SELECT COUNT(*) FROM mentor_applications WHERE status = 'approved'");
                                    $mentor_approved = $stmt->fetchColumn();
                                    $stmt = $pdo->query("SELECT COUNT(*) FROM mentee_enrollments WHERE status = 'active'");
                                    $mentee_approved = $stmt->fetchColumn();
                                    echo $mentor_approved + $mentee_approved;
                                ?></h2>
                                <small>All time approvals</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <h6 class="card-title">Active Semester</h6>
                                <h2 class="mb-0"><?php echo $active_semester ? htmlspecialchars($active_semester['semester_name']) : 'None'; ?></h2>
                                <small><?php echo $active_semester ? 'Registration: ' . date('d M', strtotime($active_semester['registration_open'])) . ' - ' . date('d M', strtotime($active_semester['registration_close'])) : 'Create one'; ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Orphan Users Warning -->
                <?php if ($orphan_mentors > 0 || $orphan_mentees > 0): ?>
                    <div class="orphan-warning mb-4">
                        <i class="bi bi-exclamation-triangle text-warning"></i>
                        <strong>Orphan Users Detected!</strong> 
                        There are <?php echo $orphan_mentors + $orphan_mentees; ?> pending user(s) without applications.
                        <a href="fix_applications.php" class="btn btn-sm btn-warning ms-2">
                            <i class="bi bi-hammer"></i> Fix Now
                        </a>
                        <button class="btn btn-sm btn-outline-secondary ms-2" data-bs-toggle="collapse" data-bs-target="#orphanDetails">
                            View Details
                        </button>
                        <div id="orphanDetails" class="collapse mt-2">
                            <ul class="mb-0">
                                <?php if ($orphan_mentors > 0): ?>
                                    <li><strong><?php echo $orphan_mentors; ?></strong> mentor(s) without applications</li>
                                <?php endif; ?>
                                <?php if ($orphan_mentees > 0): ?>
                                    <li><strong><?php echo $orphan_mentees; ?></strong> mentee(s) without enrollments</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Quick Actions -->
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h6 class="mb-0"><i class="bi bi-lightning-charge"></i> Quick Actions</h6>
                            </div>
                            <div class="col-md-6 text-end">
                                <?php if (count($pending_mentors) > 0): ?>
                                    <a href="applications.php?bulk_approve=mentors" 
                                       class="btn btn-success btn-sm me-2"
                                       onclick="return confirm('Approve all pending mentor applications?')">
                                        <i class="bi bi-check-all"></i> Approve All Mentors
                                    </a>
                                <?php endif; ?>
                                <?php if (count($pending_mentees) > 0): ?>
                                    <a href="applications.php?bulk_approve=mentees" 
                                       class="btn btn-success btn-sm"
                                       onclick="return confirm('Approve all pending mentee applications?')">
                                        <i class="bi bi-check-all"></i> Approve All Mentees
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Application Trend Chart -->
                <?php if (!empty($app_trend)): ?>
                    <div class="card shadow mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-graph-up"></i> New Applications (Last 7 Days)</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="trendChart" class="chart-container"></canvas>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- No Active Semester Warning -->
                <?php if (!$active_semester): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> 
                        <strong>No active semester found!</strong> 
                        Please <a href="semesters.php" class="alert-link">create a semester</a> to enable registrations.
                    </div>
                <?php endif; ?>
                
                <!-- Tabs -->
                <ul class="nav nav-tabs mb-4" id="applicationTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="mentors-tab" data-bs-toggle="tab" data-bs-target="#mentors" type="button" role="tab">
                            <i class="bi bi-person-badge"></i> Mentor Applications
                            <?php if (count($pending_mentors) > 0): ?>
                                <span class="badge bg-danger"><?php echo count($pending_mentors); ?></span>
                            <?php endif; ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="mentees-tab" data-bs-toggle="tab" data-bs-target="#mentees" type="button" role="tab">
                            <i class="bi bi-person"></i> Mentee Enrollments
                            <?php if (count($pending_mentees) > 0): ?>
                                <span class="badge bg-danger"><?php echo count($pending_mentees); ?></span>
                            <?php endif; ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved" type="button" role="tab">
                            <i class="bi bi-check-circle"></i> Approved
                            <span class="badge bg-success"><?php echo count($approved_applications); ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="rejected-tab" data-bs-toggle="tab" data-bs-target="#rejected" type="button" role="tab">
                            <i class="bi bi-x-circle"></i> Rejected
                            <span class="badge bg-danger"><?php echo count($rejected_applications); ?></span>
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content">
                    <!-- Mentor Applications Tab -->
                    <div class="tab-pane fade show active" id="mentors" role="tabpanel">
                        <div class="card shadow">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-person-badge"></i> Pending Mentor Applications</h5>
                                <span class="badge bg-primary"><?php echo count($pending_mentors); ?> pending</span>
                            </div>
                            <div class="card-body">
                                <?php if ($pending_mentors): ?>
                                    <div class="row g-3">
                                        <?php foreach ($pending_mentors as $app): ?>
                                            <div class="col-md-6 col-lg-4">
                                                <div class="card application-card <?php echo $app['app_exists'] == 'No Application' ? 'no-application' : 'pending-mentor'; ?> h-100">
                                                    <div class="card-body">
                                                        <div class="d-flex align-items-start mb-3">
                                                            <div class="application-avatar <?php echo $app['app_exists'] == 'No Application' ? 'avatar-pending' : 'avatar-mentor'; ?> me-3">
                                                                <?php echo strtoupper(substr($app['name'], 0, 1)); ?>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <h6 class="mb-0"><?php echo htmlspecialchars($app['name']); ?></h6>
                                                                <span class="badge badge-role-mentor">Mentor</span>
                                                                <?php if ($app['app_exists'] == 'No Application'): ?>
                                                                    <span class="badge badge-no-app">No Application</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-warning text-dark">Pending</span>
                                                                <?php endif; ?>
                                                                <?php if ($app['avg_rating']): ?>
                                                                    <span class="badge bg-success">
                                                                        <i class="bi bi-star-fill"></i> <?php echo number_format($app['avg_rating'], 1); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="small text-muted">
                                                            <div><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($app['email']); ?></div>
                                                            <div><i class="bi bi-phone"></i> <?php echo htmlspecialchars($app['phone']); ?></div>
                                                            <?php if ($app['programme']): ?>
                                                                <div><i class="bi bi-book"></i> <?php echo htmlspecialchars($app['programme']); ?></div>
                                                            <?php endif; ?>
                                                            <?php if ($app['year_of_study']): ?>
                                                                <div><i class="bi bi-graph-up"></i> Year <?php echo $app['year_of_study']; ?></div>
                                                            <?php endif; ?>
                                                            <div><i class="bi bi-journal"></i> Course: <strong><?php echo htmlspecialchars($app['course_code'] ?? 'Not Set'); ?></strong></div>
                                                            <?php if ($app['course_name']): ?>
                                                                <div><i class="bi bi-bookmark"></i> <?php echo htmlspecialchars($app['course_name']); ?></div>
                                                            <?php endif; ?>
                                                            <?php if ($app['grade']): ?>
                                                                <div><i class="bi bi-award"></i> Grade: <span class="badge bg-success"><?php echo htmlspecialchars($app['grade']); ?></span></div>
                                                            <?php endif; ?>
                                                            <?php if ($app['total_sessions'] > 0): ?>
                                                                <div><i class="bi bi-clock-history"></i> <?php echo $app['total_sessions']; ?> completed sessions</div>
                                                            <?php endif; ?>
                                                            <div><i class="bi bi-calendar3"></i> Applied: <?php echo date('d M Y', strtotime($app['created_at'])); ?></div>
                                                            <?php if ($app['app_exists'] == 'No Application'): ?>
                                                                <div class="text-warning mt-1">
                                                                    <i class="bi bi-exclamation-triangle"></i> No application record found
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <hr>
                                                        
                                                        <?php if ($app['app_exists'] != 'No Application'): ?>
                                                            <div class="d-flex gap-2">
                                                                <button class="btn btn-sm btn-success flex-grow-1 btn-action" 
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#reviewModal"
                                                                        data-id="<?php echo $app['application_id']; ?>"
                                                                        data-name="<?php echo htmlspecialchars($app['name']); ?>"
                                                                        data-email="<?php echo htmlspecialchars($app['email']); ?>"
                                                                        data-role="mentor"
                                                                        data-course="<?php echo htmlspecialchars($app['course_code']); ?>"
                                                                        data-grade="<?php echo htmlspecialchars($app['grade']); ?>"
                                                                        data-action="approve">
                                                                    <i class="bi bi-check"></i> Approve
                                                                </button>
                                                                <button class="btn btn-sm btn-danger flex-grow-1 btn-action" 
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#reviewModal"
                                                                        data-id="<?php echo $app['application_id']; ?>"
                                                                        data-name="<?php echo htmlspecialchars($app['name']); ?>"
                                                                        data-email="<?php echo htmlspecialchars($app['email']); ?>"
                                                                        data-role="mentor"
                                                                        data-course="<?php echo htmlspecialchars($app['course_code']); ?>"
                                                                        data-grade="<?php echo htmlspecialchars($app['grade']); ?>"
                                                                        data-action="reject">
                                                                    <i class="bi bi-x"></i> Reject
                                                                </button>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="d-grid">
                                                                <a href="fix_user.php?id=<?php echo $app['user_id']; ?>" class="btn btn-warning">
                                                                    <i class="bi bi-hammer"></i> Fix Application
                                                                </a>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-check-circle display-1 text-success"></i>
                                        <p class="text-muted mt-3">No pending mentor applications.</p>
                                        <p class="text-muted small">All mentor applications have been reviewed.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Mentee Enrollments Tab -->
                    <div class="tab-pane fade" id="mentees" role="tabpanel">
                        <div class="card shadow">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-person"></i> Pending Mentee Enrollments</h5>
                                <span class="badge bg-primary"><?php echo count($pending_mentees); ?> pending</span>
                            </div>
                            <div class="card-body">
                                <?php if ($pending_mentees): ?>
                                    <div class="row g-3">
                                        <?php foreach ($pending_mentees as $app): ?>
                                            <div class="col-md-6 col-lg-4">
                                                <div class="card application-card <?php echo $app['app_exists'] == 'No Enrollment' ? 'no-application' : 'pending-mentee'; ?> h-100">
                                                    <div class="card-body">
                                                        <div class="d-flex align-items-start mb-3">
                                                            <div class="application-avatar <?php echo $app['app_exists'] == 'No Enrollment' ? 'avatar-pending' : 'avatar-mentee'; ?> me-3">
                                                                <?php echo strtoupper(substr($app['name'], 0, 1)); ?>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <h6 class="mb-0"><?php echo htmlspecialchars($app['name']); ?></h6>
                                                                <span class="badge badge-role-mentee">Mentee</span>
                                                                <?php if ($app['app_exists'] == 'No Enrollment'): ?>
                                                                    <span class="badge badge-no-app">No Enrollment</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-warning text-dark">Pending</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="small text-muted">
                                                            <div><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($app['email']); ?></div>
                                                            <div><i class="bi bi-phone"></i> <?php echo htmlspecialchars($app['phone']); ?></div>
                                                            <?php if ($app['programme']): ?>
                                                                <div><i class="bi bi-book"></i> <?php echo htmlspecialchars($app['programme']); ?></div>
                                                            <?php endif; ?>
                                                            <?php if ($app['year_of_study']): ?>
                                                                <div><i class="bi bi-graph-up"></i> Year <?php echo $app['year_of_study']; ?></div>
                                                            <?php endif; ?>
                                                            <div><i class="bi bi-journal"></i> Course: <strong><?php echo htmlspecialchars($app['course_code'] ?? 'Not Set'); ?></strong></div>
                                                            <?php if ($app['course_name']): ?>
                                                                <div><i class="bi bi-bookmark"></i> <?php echo htmlspecialchars($app['course_name']); ?></div>
                                                            <?php endif; ?>
                                                            <div><i class="bi bi-calendar3"></i> Applied: <?php echo date('d M Y', strtotime($app['created_at'])); ?></div>
                                                            <?php if ($app['app_exists'] == 'No Enrollment'): ?>
                                                                <div class="text-warning mt-1">
                                                                    <i class="bi bi-exclamation-triangle"></i> No enrollment record found
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <hr>
                                                        
                                                        <?php if ($app['app_exists'] != 'No Enrollment'): ?>
                                                            <div class="d-flex gap-2">
                                                                <button class="btn btn-sm btn-success flex-grow-1 btn-action" 
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#reviewModal"
                                                                        data-id="<?php echo $app['enrollment_id']; ?>"
                                                                        data-name="<?php echo htmlspecialchars($app['name']); ?>"
                                                                        data-email="<?php echo htmlspecialchars($app['email']); ?>"
                                                                        data-role="mentee"
                                                                        data-course="<?php echo htmlspecialchars($app['course_code']); ?>"
                                                                        data-action="approve">
                                                                    <i class="bi bi-check"></i> Approve
                                                                </button>
                                                                <button class="btn btn-sm btn-danger flex-grow-1 btn-action" 
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#reviewModal"
                                                                        data-id="<?php echo $app['enrollment_id']; ?>"
                                                                        data-name="<?php echo htmlspecialchars($app['name']); ?>"
                                                                        data-email="<?php echo htmlspecialchars($app['email']); ?>"
                                                                        data-role="mentee"
                                                                        data-course="<?php echo htmlspecialchars($app['course_code']); ?>"
                                                                        data-action="reject">
                                                                    <i class="bi bi-x"></i> Reject
                                                                </button>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="d-grid">
                                                                <a href="fix_user.php?id=<?php echo $app['user_id']; ?>" class="btn btn-warning">
                                                                    <i class="bi bi-hammer"></i> Fix Enrollment
                                                                </a>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-check-circle display-1 text-success"></i>
                                        <p class="text-muted mt-3">No pending mentee enrollments.</p>
                                        <p class="text-muted small">All mentee enrollments have been reviewed.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Approved Tab -->
                    <div class="tab-pane fade" id="approved" role="tabpanel">
                        <div class="card shadow">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-check-circle"></i> Approved Applications</h5>
                                <span class="badge bg-success"><?php echo count($approved_applications); ?> approved</span>
                            </div>
                            <div class="card-body">
                                <?php if ($approved_applications): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>Course</th>
                                                    <th>Grade</th>
                                                    <th>Reviewed By</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($approved_applications as $app): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($app['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($app['email']); ?></td>
                                                    <td><?php echo htmlspecialchars($app['course_code']); ?></td>
                                                    <td><span class="badge bg-success"><?php echo htmlspecialchars($app['grade']); ?></span></td>
                                                    <td><?php echo htmlspecialchars($app['reviewer_name'] ?? 'N/A'); ?></td>
                                                    <td><?php echo date('d M Y', strtotime($app['reviewed_at'] ?? $app['created_at'])); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No approved applications yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Rejected Tab -->
                    <div class="tab-pane fade" id="rejected" role="tabpanel">
                        <div class="card shadow">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-x-circle"></i> Rejected Applications</h5>
                                <span class="badge bg-danger"><?php echo count($rejected_applications); ?> rejected</span>
                            </div>
                            <div class="card-body">
                                <?php if ($rejected_applications): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>Course</th>
                                                    <th>Grade</th>
                                                    <th>Reason</th>
                                                    <th>Reviewed By</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($rejected_applications as $app): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($app['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($app['email']); ?></td>
                                                    <td><?php echo htmlspecialchars($app['course_code']); ?></td>
                                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($app['grade']); ?></span></td>
                                                    <td><?php echo htmlspecialchars($app['comments'] ?? 'No reason provided'); ?></td>
                                                    <td><?php echo htmlspecialchars($app['reviewer_name'] ?? 'N/A'); ?></td>
                                                    <td><?php echo date('d M Y', strtotime($app['reviewed_at'] ?? $app['created_at'])); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No rejected applications.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Review Modal -->
    <div class="modal fade" id="reviewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Review Application</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="application_id" id="appId">
                        <input type="hidden" name="action" id="appAction">
                        <input type="hidden" name="type" id="appType">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="text-muted small">Applicant</label>
                                    <p class="fw-bold" id="modalName">-</p>
                                </div>
                                <div class="mb-3">
                                    <label class="text-muted small">Email</label>
                                    <p class="fw-bold" id="modalEmail">-</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="text-muted small">Role</label>
                                    <p class="fw-bold" id="modalRole">-</p>
                                </div>
                                <div class="mb-3">
                                    <label class="text-muted small">Course</label>
                                    <p class="fw-bold" id="modalCourse">-</p>
                                </div>
                                <div class="mb-3" id="gradeContainer">
                                    <label class="text-muted small">Grade</label>
                                    <p class="fw-bold" id="modalGrade">-</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            You are about to <strong id="actionText"></strong> <span id="appNameDisplay"></span>'s application.
                        </div>
                        
                        <div class="mb-3">
                            <label for="comments" class="form-label">Comments <span class="text-muted">(Optional)</span></label>
                            <textarea class="form-control" id="comments" name="comments" rows="3" 
                                      placeholder="Add any notes or reason for your decision..."></textarea>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="send_email" name="send_email" checked>
                            <label class="form-check-label" for="send_email">
                                <i class="bi bi-envelope"></i> Send email notification to applicant
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="confirmBtn">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // ============================================
        // APPLICATION TREND CHART
        // ============================================
        <?php if (!empty($app_trend)): ?>
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        const trendLabels = <?php echo json_encode(array_keys($app_trend)); ?>;
        const trendData = <?php echo json_encode(array_values($app_trend)); ?>;
        
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'New Applications',
                    data: trendData,
                    backgroundColor: 'rgba(255, 193, 7, 0.2)',
                    borderColor: 'rgba(255, 193, 7, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(255, 193, 7, 1)',
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
        <?php endif; ?>
        
        // ============================================
        // REVIEW MODAL
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            const reviewModal = document.getElementById('reviewModal');
            reviewModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const id = button.dataset.id;
                const name = button.dataset.name;
                const email = button.dataset.email || 'N/A';
                const role = button.dataset.role || 'mentor';
                const course = button.dataset.course || 'N/A';
                const grade = button.dataset.grade || 'N/A';
                const action = button.dataset.action;
                
                document.getElementById('appId').value = id;
                document.getElementById('appAction').value = action;
                document.getElementById('appType').value = role;
                
                document.getElementById('modalName').textContent = name;
                document.getElementById('modalEmail').textContent = email;
                document.getElementById('modalRole').textContent = role.charAt(0).toUpperCase() + role.slice(1);
                document.getElementById('modalCourse').textContent = course;
                document.getElementById('modalGrade').textContent = grade;
                document.getElementById('appNameDisplay').textContent = name;
                
                // Hide grade for mentees
                if (role === 'mentee') {
                    document.getElementById('gradeContainer').style.display = 'none';
                } else {
                    document.getElementById('gradeContainer').style.display = 'block';
                }
                
                const actionText = document.getElementById('actionText');
                const confirmBtn = document.getElementById('confirmBtn');
                
                if (action === 'approve') {
                    actionText.textContent = 'APPROVE';
                    actionText.className = 'text-success';
                    confirmBtn.className = 'btn btn-success';
                    confirmBtn.textContent = '✅ Approve Application';
                    document.getElementById('comments').placeholder = 'Optional: Add approval notes...';
                } else {
                    actionText.textContent = 'REJECT';
                    actionText.className = 'text-danger';
                    confirmBtn.className = 'btn btn-danger';
                    confirmBtn.textContent = '❌ Reject Application';
                    document.getElementById('comments').placeholder = 'Please provide a reason for rejection...';
                }
            });
        });
    </script>
</body>
</html>