<?php
// mentor/earnings.php
require_once dirname(__DIR__, 2) . '/backend/includes/functions.php';
requireMentor();

$user = getCurrentUser();

// Get total earnings
$stmt = $pdo->prepare("SELECT SUM(mentor_payout) as total 
                       FROM sessions 
                       WHERE mentor_id = ? AND payment_status = 'released_to_mentor'");
$stmt->execute([$user['id']]);
$total_earnings = $stmt->fetchColumn() ?? 0;

// Get pending earnings (paid to admin but not yet released)
$stmt = $pdo->prepare("SELECT SUM(mentor_payout) as total 
                       FROM sessions 
                       WHERE mentor_id = ? AND payment_status = 'paid_to_admin'");
$stmt->execute([$user['id']]);
$pending_earnings = $stmt->fetchColumn() ?? 0;

// Get total hours
$stmt = $pdo->prepare("SELECT SUM(duration_minutes) / 60.0 as total 
                       FROM sessions 
                       WHERE mentor_id = ? AND status = 'completed'");
$stmt->execute([$user['id']]);
$total_hours = $stmt->fetchColumn() ?? 0;

// Get earnings by course
$stmt = $pdo->prepare("
    SELECT course_code, 
           COUNT(*) as sessions,
           SUM(mentor_payout) as total,
           SUM(duration_minutes) / 60.0 as hours
    FROM sessions 
    WHERE mentor_id = ? AND payment_status = 'released_to_mentor'
    GROUP BY course_code
");
$stmt->execute([$user['id']]);
$earnings_by_course = $stmt->fetchAll();

// Get monthly earnings (last 6 months)
$monthly_earnings = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $stmt = $pdo->prepare("
        SELECT SUM(mentor_payout) as total 
        FROM sessions 
        WHERE mentor_id = ? 
          AND payment_status = 'released_to_mentor'
          AND TO_CHAR(payout_date, 'YYYY-MM') = ?
    ");
    $stmt->execute([$user['id'], $month]);
    $monthly_earnings[$month] = $stmt->fetchColumn() ?? 0;
}

// Get recent payouts
$stmt = $pdo->prepare("
    SELECT s.*, u.name as mentee_name, s.course_code
    FROM sessions s
    JOIN users u ON s.mentee_id = u.id
    WHERE s.mentor_id = ? AND s.payment_status = 'released_to_mentor'
    ORDER BY s.payout_date DESC
    LIMIT 10
");
$stmt->execute([$user['id']]);
$recent_payouts = $stmt->fetchAll();

// Check if mentor is eligible for certificate
$stmt = $pdo->query("SELECT * FROM semesters WHERE status = 'active' ORDER BY id DESC LIMIT 1");
$active_semester = $stmt->fetch();

$certificate_eligible = false;
if ($active_semester) {
    $hours = getMentorHours($user['id'], $active_semester['id']);
    $certificate_eligible = $hours >= MIN_MENTOR_HOURS;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Earnings - Mentor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include dirname(__DIR__, 2) . '/frontend/includes/mentor_nav.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3 col-lg-2 mb-3">
                <?php include dirname(__DIR__, 2) . '/frontend/includes/mentor_sidebar.php'; ?>
            </div>
            
            <div class="col-md-9 col-lg-10">
                <h3><i class="bi bi-wallet"></i> My Earnings</h3>
                <p class="text-muted">Track your mentorship earnings and performance.</p>
                
                <?php if ($certificate_eligible): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-award"></i> 
                        Congratulations! You've earned <?php echo MIN_MENTOR_HOURS; ?>+ hours this semester. 
                        <a href="certificate.php" class="alert-link">Claim your certificate now</a>
                    </div>
                <?php endif; ?>
                
                <!-- Stats Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Earned</h5>
                                <h2><?php echo formatCurrency($total_earnings); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <h5 class="card-title">Pending</h5>
                                <h2><?php echo formatCurrency($pending_earnings); ?></h2>
                                <small>Awaiting admin release</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Hours</h5>
                                <h2><?php echo round($total_hours, 1); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Avg Rate</h5>
                                <h2><?php 
                                    $avg_rate = $total_hours > 0 ? $total_earnings / $total_hours : 0;
                                    echo formatCurrency($avg_rate); 
                                ?>/hr</h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row g-3 mb-4">
                    <div class="col-md-8">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5 class="mb-0">Earnings Trend</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="earningsChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5 class="mb-0">Earnings by Course</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($earnings_by_course): ?>
                                    <canvas id="courseChart"></canvas>
                                    <div class="mt-3">
                                        <?php foreach ($earnings_by_course as $course): ?>
                                            <div class="d-flex justify-content-between">
                                                <span><?php echo htmlspecialchars($course['course_code']); ?></span>
                                                <span><?php echo formatCurrency($course['total']); ?></span>
                                            </div>
                                            <div class="progress mb-2">
                                                <div class="progress-bar" style="width: <?php echo ($total_earnings > 0) ? ($course['total'] / $total_earnings * 100) : 0; ?>%"></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No earnings data yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Payouts -->
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Payouts</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($recent_payouts): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Mentee</th>
                                            <th>Course</th>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Released</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_payouts as $payout): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($payout['mentee_name']); ?></td>
                                            <td><?php echo htmlspecialchars($payout['course_code']); ?></td>
                                            <td><?php echo date('d M Y', strtotime($payout['session_date'])); ?></td>
                                            <td><?php echo formatCurrency($payout['mentor_payout']); ?></td>
                                            <td><?php echo date('d M Y', strtotime($payout['payout_date'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No payouts yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Earnings Chart
        const ctx = document.getElementById('earningsChart').getContext('2d');
        const chartData = <?php echo json_encode(array_values($monthly_earnings)); ?>;
        const chartLabels = <?php echo json_encode(array_keys($monthly_earnings)); ?>;
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Earnings',
                    data: chartData,
                    backgroundColor: 'rgba(40, 167, 69, 0.2)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 3,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'K' + value;
                            }
                        }
                    }
                }
            }
        });
        
        <?php if ($earnings_by_course): ?>
        // Course Chart
        const ctx2 = document.getElementById('courseChart').getContext('2d');
        const courseData = <?php echo json_encode(array_column($earnings_by_course, 'total')); ?>;
        const courseLabels = <?php echo json_encode(array_column($earnings_by_course, 'course_code')); ?>;
        
        new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: courseLabels,
                datasets: [{
                    data: courseData,
                    backgroundColor: ['#0d6efd', '#28a745', '#ffc107', '#dc3545', '#17a2b8', '#6f42c1']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>