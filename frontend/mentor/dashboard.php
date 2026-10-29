<?php
// mentor/dashboard.php
require_once dirname(__DIR__, 2) . '/backend/includes/functions.php';
requireMentor();

$user = getCurrentUser();
$has_subscription = hasActiveSubscription($user['id']);

// Get active semester
$stmt = $pdo->query("SELECT * FROM semesters WHERE status = 'active' ORDER BY id DESC LIMIT 1");
$active_semester = $stmt->fetch();

// Get statistics
$stats = [];

// Total sessions
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM sessions WHERE mentor_id = ?");
$stmt->execute([$user['id']]);
$stats['total_sessions'] = $stmt->fetchColumn();

// Completed sessions
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM sessions WHERE mentor_id = ? AND status = 'completed'");
$stmt->execute([$user['id']]);
$stats['completed_sessions'] = $stmt->fetchColumn();

// Pending sessions
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM sessions WHERE mentor_id = ? AND status IN ('pending', 'confirmed')");
$stmt->execute([$user['id']]);
$stats['pending_sessions'] = $stmt->fetchColumn();

// Paid bookings that need the mentor's response
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM sessions
    WHERE mentor_id = ? AND status = 'pending' AND payment_status = 'paid_to_admin'
");
$stmt->execute([$user['id']]);
$new_session_requests = $stmt->fetchColumn();

// Total earnings (released payments)
$stmt = $pdo->prepare("SELECT SUM(mentor_payout) as total FROM sessions WHERE mentor_id = ? AND payment_status = 'released_to_mentor'");
$stmt->execute([$user['id']]);
$stats['total_earnings'] = $stmt->fetchColumn() ?? 0;

// Total hours
$stmt = $pdo->prepare("SELECT SUM(duration_minutes) / 60.0 as total_hours FROM sessions WHERE mentor_id = ? AND status = 'completed'");
$stmt->execute([$user['id']]);
$stats['total_hours'] = $stmt->fetchColumn() ?? 0;

// Check if mentor has rates set
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM mentor_rates WHERE mentor_id = ?");
$stmt->execute([$user['id']]);
$has_rates = $stmt->fetchColumn() > 0;

// Check if mentor has certificate
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM certificates WHERE mentor_id = ?");
$stmt->execute([$user['id']]);
$has_certificate = $stmt->fetchColumn() > 0;

// Upcoming sessions
$stmt = $pdo->prepare("
    SELECT s.*, u.name as mentee_name, u.email as mentee_email
    FROM sessions s
    JOIN users u ON s.mentee_id = u.id
    WHERE s.mentor_id = ? AND s.status IN ('pending', 'confirmed') AND s.session_date > NOW()
    ORDER BY s.session_date ASC LIMIT 5
");
$stmt->execute([$user['id']]);
$upcoming_sessions = $stmt->fetchAll();

// Recent sessions
$stmt = $pdo->prepare("
    SELECT s.*, u.name as mentee_name
    FROM sessions s
    JOIN users u ON s.mentee_id = u.id
    WHERE s.mentor_id = ?
    ORDER BY s.created_at DESC LIMIT 5
");
$stmt->execute([$user['id']]);
$recent_sessions = $stmt->fetchAll();

// Check if eligible for certificate
$certificate_eligible = false;
if ($active_semester) {
    $hours = getMentorHours($user['id'], $active_semester['id']);
    $certificate_eligible = $hours >= MIN_MENTOR_HOURS && !$has_certificate;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentor Dashboard - MentorMatch</title>
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
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
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
        .rating-stars {
            color: #ffc107;
            font-size: 0.9rem;
        }
        .quick-action-btn {
            padding: 15px;
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
            font-size: 1.8rem;
            display: block;
            margin-bottom: 5px;
        }
        .feedback-card {
            border-left: 4px solid #ffc107;
            transition: all 0.3s ease;
        }
        .feedback-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        @media (max-width: 768px) {
            .quick-action-btn { padding: 10px; }
            .quick-action-btn i { font-size: 1.2rem; }
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
                <!-- Welcome Banner -->
                <div class="welcome-banner">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3><i class="bi bi-person-circle"></i> Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</h3>
                            <small>Here's your mentorship performance overview.</small>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="d-flex justify-content-end gap-3">
                                <div class="bg-white bg-opacity-25 p-2 rounded">
                                    <div class="small">Active Semester</div>
                                    <div class="fw-bold"><?php echo $active_semester ? htmlspecialchars($active_semester['semester_name']) : 'N/A'; ?></div>
                                </div>
                                <?php if ($certificate_eligible): ?>
                                    <div class="bg-warning text-dark p-2 rounded">
                                        <div class="small">Certificate</div>
                                        <div class="fw-bold">Eligible!</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (!$has_rates): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> 
                        <strong>Action Required:</strong> You haven't set your rates yet. 
                        <a href="rates.php" class="alert-link">Set your rates now</a> to start receiving bookings.
                    </div>
                <?php endif; ?>

                <?php if ($new_session_requests > 0): ?>
                    <div class="alert alert-info d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
                        <span>
                            <i class="bi bi-chat-dots"></i>
                            <strong>New session requests:</strong> <?php echo $new_session_requests; ?> paid booking<?php echo (int)$new_session_requests === 1 ? ' is' : 's are'; ?> waiting for your response.
                        </span>
                        <a href="sessions.php?filter=requests" class="alert-link">Review requests <i class="bi bi-arrow-right"></i></a>
                    </div>
                <?php endif; ?>
                
                <?php if ($certificate_eligible): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-award"></i> 
                        <strong>Congratulations!</strong> You've earned <?php echo MIN_MENTOR_HOURS; ?>+ hours this semester. 
                        <a href="certificate.php" class="alert-link">Claim your certificate now</a>
                    </div>
                <?php endif; ?>

                <?php if (!$has_subscription): ?>
                    <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span><strong>Subscription required:</strong> Your profile remains hidden from mentee search until your subscription is active. Open MentorMatch AI for details.</span>
                        <a class="btn btn-primary btn-sm" href="../subscription.php">View subscription — ZMW 250/month</a>
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
                                <h6 class="card-title">Hours</h6>
                                <h2 class="mb-0"><?php echo round($stats['total_hours'], 1); ?></h2>
                                <small>Total mentoring hours</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-warning text-dark">
                            <div class="card-body position-relative">
                                <i class="bi bi-currency-dollar stat-icon"></i>
                                <h6 class="card-title">Earnings</h6>
                                <h2 class="mb-0"><?php echo formatCurrency($stats['total_earnings']); ?></h2>
                                <small><?php echo $stats['pending_sessions']; ?> sessions pending</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body position-relative">
                                <i class="bi bi-eye stat-icon"></i>
                                <h6 class="card-title">Profile Visibility</h6>
                                <h2 class="mb-0"><?php echo $has_subscription ? 'Visible' : 'Hidden'; ?></h2>
                                <small><?php echo $has_subscription ? 'Shown in mentee search' : 'Subscription inactive'; ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="row g-2 mb-4">
                    <div class="col-6 col-md-3">
                        <a href="sessions.php" class="quick-action-btn">
                            <i class="bi bi-calendar-check text-primary"></i>
                            <small>My Sessions</small>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="availability.php" class="quick-action-btn">
                            <i class="bi bi-clock text-success"></i>
                            <small>Availability</small>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="rates.php" class="quick-action-btn">
                            <i class="bi bi-currency-dollar text-warning"></i>
                            <small>My Rates</small>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="earnings.php" class="quick-action-btn">
                            <i class="bi bi-wallet text-info"></i>
                            <small>Earnings</small>
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
                                            <th>Mentee</th>
                                            <th>Course</th>
                                            <th>Date & Time</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($upcoming_sessions as $session): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($session['mentee_name']); ?></td>
                                                <td><?php echo htmlspecialchars($session['course_code']); ?></td>
                                                <td><?php echo date('d M Y h:i A', strtotime($session['session_date'])); ?></td>
                                                <td><?php echo formatCurrency($session['total_amount']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $session['status'] === 'confirmed' ? 'success' : ($session['payment_status'] === 'paid_to_admin' ? 'warning' : 'secondary'); ?>">
                                                        <?php echo getSessionStatus($session['status']); ?>
                                                    </span>
                                                    <?php if ($session['status'] === 'pending' && $session['payment_status'] === 'paid_to_admin'): ?>
                                                        <small class="d-block text-muted">Response needed</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="session_details.php?id=<?php echo $session['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center py-3">No upcoming sessions. Share your availability to get bookings!</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="row g-3">
                    <!-- Recent Sessions -->
                    <div class="col-md-6">
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
                                                    <th>Mentee</th>
                                                    <th>Course</th>
                                                    <th>Date</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_sessions as $session): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($session['mentee_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($session['course_code']); ?></td>
                                                        <td><?php echo date('d M Y', strtotime($session['session_date'])); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $session['status'] === 'completed' ? 'success' : 'warning'; ?>">
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
                    
                    <!-- Feedback privacy notice -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body d-flex align-items-center gap-3">
                                <i class="bi bi-shield-lock fs-3 text-primary" aria-hidden="true"></i>
                                <p class="mb-0 text-muted">Mentee ratings and written feedback are private and are not displayed to mentors.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    $assistant_role = 'mentor';
    $assistant_has_subscription = $has_subscription;
    include dirname(__DIR__, 2) . '/frontend/includes/dashboard_ai_assistant.php';
    ?>
</body>
</html>