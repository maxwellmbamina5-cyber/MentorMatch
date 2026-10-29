<?php
// mentee/sessions.php
require_once '../includes/functions.php';
requireMentee();

$user = getCurrentUser();
$filter = $_GET['filter'] ?? 'all';

// Build query based on filter
$query = "
    SELECT s.*, u.name as mentor_name, u.email as mentor_email, u.phone as mentor_phone
    FROM sessions s
    JOIN users u ON s.mentor_id = u.id
    WHERE s.mentee_id = ?
";

if ($filter === 'upcoming') {
    $query .= " AND s.session_date > NOW() AND s.status IN ('pending', 'confirmed')";
} elseif ($filter === 'completed') {
    $query .= " AND s.status = 'completed'";
} elseif ($filter === 'pending') {
    $query .= " AND s.status = 'pending'";
} elseif ($filter === 'cancelled') {
    $query .= " AND s.status = 'cancelled'";
}

$query .= " ORDER BY s.session_date " . ($filter === 'completed' ? 'DESC' : 'ASC');

$stmt = $pdo->prepare($query);
$stmt->execute([$user['id']]);
$sessions = $stmt->fetchAll();

// Get counts for tabs
$stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE mentee_id = ? AND status IN ('pending', 'confirmed') AND session_date > NOW()");
$stmt->execute([$user['id']]);
$upcoming_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE mentee_id = ? AND status = 'pending'");
$stmt->execute([$user['id']]);
$pending_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE mentee_id = ? AND status = 'completed'");
$stmt->execute([$user['id']]);
$completed_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE mentee_id = ? AND status = 'cancelled'");
$stmt->execute([$user['id']]);
$cancelled_count = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Sessions - Mentee</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/mobile.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/mentee_nav.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3 col-lg-2 mb-3">
                <?php include '../includes/mentee_sidebar.php'; ?>
            </div>
            
            <div class="col-md-9 col-lg-10">
                <h3><i class="bi bi-calendar-check"></i> My Sessions</h3>
                <p class="text-muted">View and manage all your booked sessions.</p>
                
                <!-- Tabs -->
                <ul class="nav nav-tabs mb-4">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $filter === 'all' ? 'active' : ''; ?>" href="sessions.php?filter=all">
                            All <span class="badge bg-secondary"><?php echo count($sessions); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $filter === 'upcoming' ? 'active' : ''; ?>" href="sessions.php?filter=upcoming">
                            Upcoming <span class="badge bg-primary"><?php echo $upcoming_count; ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $filter === 'pending' ? 'active' : ''; ?>" href="sessions.php?filter=pending">
                            Pending <span class="badge bg-warning"><?php echo $pending_count; ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $filter === 'completed' ? 'active' : ''; ?>" href="sessions.php?filter=completed">
                            Completed <span class="badge bg-success"><?php echo $completed_count; ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $filter === 'cancelled' ? 'active' : ''; ?>" href="sessions.php?filter=cancelled">
                            Cancelled <span class="badge bg-danger"><?php echo $cancelled_count; ?></span>
                        </a>
                    </li>
                </ul>
                
                <!-- Sessions Table -->
                <div class="card shadow">
                    <div class="card-body">
                        <?php if ($sessions): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Mentor</th>
                                            <th>Course</th>
                                            <th>Date & Time</th>
                                            <th>Duration</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Payment</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sessions as $session): ?>
                                        <tr>
                                            <td>#<?php echo $session['id']; ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($session['mentor_name']); ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($session['mentor_email']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($session['course_code']); ?></td>
                                            <td><?php echo date('d M Y h:i A', strtotime($session['session_date'])); ?></td>
                                            <td><?php echo $session['duration_minutes']; ?> min</td>
                                            <td><?php echo formatCurrency($session['total_amount']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $session['status'] === 'completed' ? 'success' : 
                                                        ($session['status'] === 'confirmed' ? 'primary' : 
                                                        ($session['status'] === 'pending' ? 'warning' : 'danger')); 
                                                ?>">
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
                                                <?php if ($session['status'] === 'pending'): ?>
                                                    <a href="payment.php?session_id=<?php echo $session['id']; ?>" 
                                                       class="btn btn-sm btn-success">
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
                            <div class="text-center py-4">
                                <i class="bi bi-calendar-x display-1 text-muted"></i>
                                <p class="text-muted mt-3">No sessions found. <a href="search.php">Find a mentor now</a></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>