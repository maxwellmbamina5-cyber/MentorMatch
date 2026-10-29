<?php
// mentee/dashboard.php
require_once dirname(__DIR__, 2) . '/backend/includes/functions.php';
requireMentee();

$user = getCurrentUser();
$has_subscription = hasActiveSubscription($user['id']);

// Get active semester
$stmt = $pdo->query("SELECT * FROM semesters WHERE status = 'active' ORDER BY id DESC LIMIT 1");
$active_semester = $stmt->fetch();

// Get statistics
$stats = [];

// Total sessions
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM sessions WHERE mentee_id = ?");
$stmt->execute([$user['id']]);
$stats['total_sessions'] = $stmt->fetchColumn();

// Completed sessions
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM sessions WHERE mentee_id = ? AND status = 'completed'");
$stmt->execute([$user['id']]);
$stats['completed_sessions'] = $stmt->fetchColumn();

// Pending sessions
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM sessions WHERE mentee_id = ? AND status IN ('pending', 'confirmed')");
$stmt->execute([$user['id']]);
$stats['pending_sessions'] = $stmt->fetchColumn();

// Total spent
$stmt = $pdo->prepare("SELECT SUM(total_amount) as total FROM sessions WHERE mentee_id = ? AND payment_status = 'paid_to_admin'");
$stmt->execute([$user['id']]);
$stats['total_spent'] = $stmt->fetchColumn() ?? 0;

// Upcoming sessions
$stmt = $pdo->prepare("
    SELECT s.*, u.name as mentor_name, u.email as mentor_email
    FROM sessions s
    JOIN users u ON s.mentor_id = u.id
    WHERE s.mentee_id = ? AND s.status IN ('pending', 'confirmed') AND s.session_date > NOW()
    ORDER BY s.session_date ASC LIMIT 5
");
$stmt->execute([$user['id']]);
$upcoming_sessions = $stmt->fetchAll();

// Recent sessions
$stmt = $pdo->prepare("
    SELECT s.*, u.name as mentor_name
    FROM sessions s
    JOIN users u ON s.mentor_id = u.id
    WHERE s.mentee_id = ?
    ORDER BY s.created_at DESC LIMIT 5
");
$stmt->execute([$user['id']]);
$recent_sessions = $stmt->fetchAll();

// Check if mentee has pending payments
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM sessions 
    WHERE mentee_id = ? AND payment_status = 'pending' AND status IN ('pending', 'confirmed')
");
$stmt->execute([$user['id']]);
$pending_payments = $stmt->fetchColumn();

// Paid bookings awaiting a mentor response
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM sessions
    WHERE mentee_id = ? AND status = 'pending' AND payment_status = 'paid_to_admin'
");
$stmt->execute([$user['id']]);
$awaiting_mentor_count = $stmt->fetchColumn();

// Get recommended mentors
$recommended_mentors = [];
if ($has_subscription) {
    $stmt = $pdo->prepare("
    SELECT DISTINCT u.id, u.name, u.email, r.course_code,
           COALESCE(NULLIF(c.course_name, ''), NULLIF(ma.course_name, ''), r.course_code) AS course_name,
           r.rate_per_hour,
           (SELECT AVG(rating) FROM feedback WHERE mentor_id = u.id) as avg_rating
    FROM users u
    JOIN mentor_rates r ON u.id = r.mentor_id
    JOIN mentor_applications ma ON ma.mentor_id = u.id AND ma.course_code = r.course_code
    LEFT JOIN courses c ON c.course_code = r.course_code
    WHERE u.role = 'mentor' 
      AND u.status = 'active'
      AND ma.status = 'approved'
      AND ma.semester_id = ?
      AND EXISTS (
          SELECT 1 FROM subscriptions sub
          WHERE sub.user_id = u.id AND sub.status = 'active' AND sub.expires_at > NOW()
      )
    ORDER BY RAND() LIMIT 4
");
    $stmt->execute([$active_semester['id'] ?? 0]);
    $recommended_mentors = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentee Dashboard - MentorMatch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/mobile.css" rel="stylesheet">
    <link href="../assets/css/ai-assistant.css" rel="stylesheet">
    <script src="../assets/js/dashboard-assistant.js" defer></script>
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
            font-size: 2rem;
            opacity: 0.3;
            position: absolute;
            right: 15px;
            top: 15px;
        }
        .welcome-banner {
            background: linear-gradient(135deg, #17a2b8 0%, #0d6efd 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        .mentor-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .mentor-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        .rating-stars {
            color: #ffc107;
        }
        .quick-action-btn {
            padding: 20px;
            text-align: center;
            border-radius: 10px;
            transition: all 0.3s ease;
            background: #f8f9fa;
            text-decoration: none;
            color: #212529;
            display: block;
        }
        .quick-action-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            background: #e9ecef;
        }
        .quick-action-btn i {
            font-size: 2rem;
            display: block;
            margin-bottom: 5px;
        }
        @media (max-width: 768px) {
            .quick-action-btn { padding: 12px; }
            .quick-action-btn i { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <?php include dirname(__DIR__, 2) . '/frontend/includes/mentee_nav.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3 col-lg-2 mb-3">
                <?php include dirname(__DIR__, 2) . '/frontend/includes/mentee_sidebar.php'; ?>
            </div>
            
            <div class="col-md-9 col-lg-10">
                <!-- Welcome Banner -->
                <div class="welcome-banner">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3><i class="bi bi-person-circle"></i> Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</h3>
                            <small>Find the right mentor to help you succeed in your courses.</small>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="bg-white bg-opacity-25 p-2 rounded d-inline-block">
                                <div class="small">Active Semester</div>
                                <div class="fw-bold"><?php echo $active_semester ? htmlspecialchars($active_semester['semester_name']) : 'N/A'; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($pending_payments > 0): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-credit-card"></i> 
                        <strong>Action Required:</strong> You have <?php echo $pending_payments; ?> session(s) awaiting payment. 
                        <a href="sessions.php?filter=pending" class="alert-link">Pay now</a>
                    </div>
                <?php endif; ?>

                <?php if ($awaiting_mentor_count > 0): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-chat-dots"></i>
                        <strong>Booking update:</strong> <?php echo $awaiting_mentor_count; ?> paid session<?php echo (int)$awaiting_mentor_count === 1 ? ' is' : 's are'; ?> waiting for a mentor to respond.
                        <a href="sessions.php?filter=pending" class="alert-link">Check session status</a>
                    </div>
                <?php endif; ?>
                
                <?php if (!$active_semester): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> 
                        <strong>No active semester found.</strong> Please contact the administrator.
                    </div>
                <?php endif; ?>

                <?php if (!$has_subscription): ?>
                    <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span><strong>Subscription required:</strong> Mentor search and session booking are locked until your subscription is active. Open MentorMatch AI for details.</span>
                        <a class="btn btn-primary btn-sm" href="../subscription.php">View subscription — ZMW 200/month</a>
                    </div>
                <?php endif; ?>
                
                <!-- Stats Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body position-relative">
                                <i class="bi bi-calendar-check stat-icon"></i>
                                <h6 class="card-title">Total Sessions</h6>
                                <h2 class="mb-0"><?php echo $stats['total_sessions']; ?></h2>
                                <small><?php echo $stats['completed_sessions']; ?> completed</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body position-relative">
                                <i class="bi bi-clock-history stat-icon"></i>
                                <h6 class="card-title">Upcoming</h6>
                                <h2 class="mb-0"><?php echo $stats['pending_sessions']; ?></h2>
                                <small>Scheduled sessions</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-warning text-dark">
                            <div class="card-body position-relative">
                                <i class="bi bi-currency-dollar stat-icon"></i>
                                <h6 class="card-title">Total Spent</h6>
                                <h2 class="mb-0"><?php echo formatCurrency($stats['total_spent']); ?></h2>
                                <small>All payments</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body position-relative">
                                <i class="bi bi-star stat-icon"></i>
                                <h6 class="card-title">Feedback Given</h6>
                                <h2 class="mb-0">
                                    <?php 
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM feedback WHERE mentee_id = ?");
                                    $stmt->execute([$user['id']]);
                                    echo $stmt->fetchColumn();
                                    ?>
                                </h2>
                                <small>Help others improve</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="row g-2 mb-4">
                    <div class="col-4 col-md-3">
                        <a href="search.php" class="quick-action-btn">
                            <i class="bi bi-search text-primary"></i>
                            <small>Find a Mentor</small>
                        </a>
                    </div>
                    <div class="col-4 col-md-3">
                        <a href="sessions.php" class="quick-action-btn">
                            <i class="bi bi-calendar-check text-success"></i>
                            <small>My Sessions</small>
                        </a>
                    </div>
                    <div class="col-4 col-md-3">
                        <a href="payments.php" class="quick-action-btn">
                            <i class="bi bi-credit-card text-warning"></i>
                            <small>Payments</small>
                        </a>
                    </div>
                    <div class="col-4 col-md-3">
                        <a href="feedback.php" class="quick-action-btn">
                            <i class="bi bi-star text-info"></i>
                            <small>My Feedback</small>
                        </a>
                    </div>
                </div>
                
                <!-- Upcoming Sessions -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-calendar-event"></i> Upcoming Sessions</h6>
                        <a href="sessions.php?filter=upcoming" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if ($upcoming_sessions): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Mentor</th>
                                            <th>Course</th>
                                            <th>Date & Time</th>
                                            <th>Amount</th>
                                            <th>Booking / Payment</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($upcoming_sessions as $session): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($session['mentor_name']); ?></td>
                                                <td><?php echo htmlspecialchars($session['course_code']); ?></td>
                                                <td><?php echo date('d M Y h:i A', strtotime($session['session_date'])); ?></td>
                                                <td><?php echo formatCurrency($session['total_amount']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $session['status'] === 'confirmed' ? 'success' : ($session['status'] === 'cancelled' ? 'danger' : 'warning'); ?>">
                                                        <?php echo htmlspecialchars(getSessionStatus($session['status'])); ?>
                                                    </span>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo getPaymentStatus($session['payment_status']); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <a href="session_details.php?id=<?php echo $session['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php if ($session['payment_status'] === 'pending'): ?>
                                                        <a href="payment.php?session_id=<?php echo $session['id']; ?>" class="btn btn-sm btn-success">
                                                            <i class="bi bi-credit-card"></i> Pay
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center py-3">No upcoming sessions. <a href="search.php">Find a mentor now</a></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recommended Mentors -->
                <?php if ($recommended_mentors): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-lightbulb"></i> Recommended Mentors</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <?php foreach ($recommended_mentors as $mentor): ?>
                                    <div class="col-md-3 col-6">
                                        <div class="card mentor-card h-100">
                                            <div class="card-body text-center">
                                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto mb-2" 
                                                     style="width: 50px; height: 50px; font-size: 1.2rem; font-weight: 700;">
                                                    <?php echo strtoupper(substr($mentor['name'], 0, 1)); ?>
                                                </div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($mentor['name']); ?></h6>
                                                <?php if ($mentor['avg_rating']): ?>
                                                    <div class="rating-stars small">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="bi bi-star-fill <?php echo $i <= round($mentor['avg_rating']) ? 'text-warning' : 'text-muted opacity-25'; ?>"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <small class="d-block text-muted"><?php echo htmlspecialchars($mentor['course_code']); ?> · <?php echo htmlspecialchars($mentor['course_name']); ?></small>
                                                <small class="text-muted"><?php echo formatCurrency($mentor['rate_per_hour']); ?>/hr</small>
                                                <br>
                                                <a href="search.php?course=<?php echo urlencode($mentor['course_code']); ?>" class="btn btn-sm btn-primary mt-2">
                                                    <i class="bi bi-search"></i> View mentors
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Recent Sessions -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-clock-history"></i> Recent Sessions</h6>
                        <a href="sessions.php" class="btn btn-sm btn-secondary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($recent_sessions): ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Mentor</th>
                                            <th>Course</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Payment</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_sessions as $session): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($session['mentor_name']); ?></td>
                                                <td><?php echo htmlspecialchars($session['course_code']); ?></td>
                                                <td><?php echo date('d M Y', strtotime($session['session_date'])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $session['status'] === 'completed' ? 'success' : 'warning'; ?>">
                                                        <?php echo getSessionStatus($session['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $session['payment_status'] === 'released_to_mentor' ? 'success' : 'info'; ?>">
                                                        <?php echo getPaymentStatus($session['payment_status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center py-3">No sessions yet. <a href="search.php">Find a mentor now</a></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    $assistant_role = 'mentee';
    $assistant_has_subscription = $has_subscription;
    include dirname(__DIR__, 2) . '/frontend/includes/dashboard_ai_assistant.php';
    ?>
</body>
</html>