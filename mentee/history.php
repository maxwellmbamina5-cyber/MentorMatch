<?php
// mentee/history.php
require_once '../includes/functions.php';
requireMentee();

$user = getCurrentUser();

// Get all sessions with feedback
$stmt = $pdo->prepare("
    SELECT s.*, u.name as mentor_name, u.email as mentor_email,
           f.rating, f.comments as feedback_comments
    FROM sessions s
    JOIN users u ON s.mentor_id = u.id
    LEFT JOIN feedback f ON s.id = f.session_id
    WHERE s.mentee_id = ?
    ORDER BY s.session_date DESC
");
$stmt->execute([$user['id']]);
$history = $stmt->fetchAll();

// Get summary
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM sessions WHERE mentee_id = ?");
$stmt->execute([$user['id']]);
$total_sessions = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM sessions WHERE mentee_id = ? AND status = 'completed'");
$stmt->execute([$user['id']]);
$completed_sessions = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT SUM(total_amount) as total FROM sessions WHERE mentee_id = ? AND payment_status = 'paid_to_admin'");
$stmt->execute([$user['id']]);
$total_spent = $stmt->fetchColumn() ?? 0;

// Get course breakdown
$stmt = $pdo->prepare("
    SELECT course_code, COUNT(*) as count
    FROM sessions
    WHERE mentee_id = ?
    GROUP BY course_code
    ORDER BY count DESC
");
$stmt->execute([$user['id']]);
$course_breakdown = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History - Mentee</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/mentee_nav.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3 col-lg-2 mb-3">
                <?php include '../includes/mentee_sidebar.php'; ?>
            </div>
            
            <div class="col-md-9 col-lg-10">
                <h3><i class="bi bi-clock-history"></i> History</h3>
                <p class="text-muted">View your session history and learning journey.</p>
                
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Sessions</h5>
                                <h2><?php echo $total_sessions; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Completed</h5>
                                <h2><?php echo $completed_sessions; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <h5 class="card-title">Total Spent</h5>
                                <h2><?php echo formatCurrency($total_spent); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Courses</h5>
                                <h2><?php echo count($course_breakdown); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($course_breakdown): ?>
                    <div class="card shadow mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Course Breakdown</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($course_breakdown as $course): ?>
                                    <div class="col-md-3 mb-2">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <h5><?php echo htmlspecialchars($course['course_code']); ?></h5>
                                                <span class="badge bg-primary"><?php echo $course['count']; ?> sessions</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="mb-0">Session History</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($history): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Mentor</th>
                                            <th>Course</th>
                                            <th>Date</th>
                                            <th>Duration</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Rating</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($history as $session): ?>
                                        <tr>
                                            <td>#<?php echo $session['id']; ?></td>
                                            <td><?php echo htmlspecialchars($session['mentor_name']); ?></td>
                                            <td><?php echo htmlspecialchars($session['course_code']); ?></td>
                                            <td><?php echo date('d M Y', strtotime($session['session_date'])); ?></td>
                                            <td><?php echo $session['duration_minutes']; ?> min</td>
                                            <td><?php echo formatCurrency($session['total_amount']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $session['status'] === 'completed' ? 'success' : 
                                                        ($session['status'] === 'cancelled' ? 'danger' : 'warning'); 
                                                ?>">
                                                    <?php echo getSessionStatus($session['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($session['rating']): ?>
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <span class="text-<?php echo $i <= $session['rating'] ? 'warning' : 'secondary'; ?>">
                                                            <i class="bi bi-star-fill"></i>
                                                        </span>
                                                    <?php endfor; ?>
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
                            <p class="text-muted">No session history yet. <a href="search.php">Find a mentor now</a></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>